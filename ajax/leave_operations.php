<?php
require_once '../includes/functions.php';
require_once '../includes/leave_schema.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid Request']);
    exit;
}

require_login();

// Ensure leave tables have all required columns
ensureLeaveSchema($pdo);

try {
    $company_id = $_SESSION['company_id'];
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // Action
    $action = $_POST['action'] ?? '';

    // Action: Request Leave (Employee)
    if ($action === 'request_leave') {
        // ... (existing code) ...
        // 1. Identify Employee
        $emp_id = $_POST['employee_id'] ?? null;
        if ($role === 'employee') {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $emp_id = $stmt->fetchColumn();
            if (!$emp_id) throw new Exception("Employee profile not found.");
        }

        // 2. Validate Inputs
        $type = $_POST['leave_type'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $reason = $_POST['reason'];

        if (empty($start) || empty($end) || empty($type)) throw new Exception("All fields are required.");
        
        $d1 = new DateTime($start);
        $d2 = new DateTime($end);
        if ($d1 > $d2) throw new Exception("Start date cannot be after end date.");

        $stmt = $pdo->prepare("INSERT INTO leave_requests (company_id, employee_id, leave_type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$company_id, $emp_id, $type, $start, $end, $reason]);

        echo json_encode(['status' => true, 'message' => 'Leave request submitted successfully.']);
    
    // Action: Fetch Leaves (Admin/HR)
    } elseif ($action === 'fetch_leaves') {
        // Access Check
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");

        $filter = $_POST['filter'] ?? 'pending';
        $sql = "SELECT l.*, e.first_name, e.last_name, e.email 
                FROM leave_requests l 
                JOIN employees e ON l.employee_id = e.id 
                WHERE l.company_id = ?";
        
        if ($filter === 'pending') $sql .= " AND l.status = 'pending'";
        elseif ($filter === 'approved') $sql .= " AND l.status = 'approved'";
        elseif ($filter === 'rejected') $sql .= " AND l.status = 'rejected'";
        // history = all

        $sql .= " ORDER BY l.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$company_id]);
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'leaves' => $leaves]);

    // Action: Approve Leave (Admin/HR) - WITH BALANCE DEDUCTION
    } elseif ($action === 'approve_leave') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $id = $_POST['leave_id'];
        
        // Start transaction for atomicity
        $pdo->beginTransaction();
        
        try {
            // Fetch leave request details
            $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ? AND company_id = ?");
            $stmt->execute([$id, $company_id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) throw new Exception("Leave request not found.");
            if ($leave['status'] !== 'pending') throw new Exception("Leave already processed.");
            
            // Calculate days
            $start = new DateTime($leave['start_date']);
            $end = new DateTime($leave['end_date']);
            $days_count = $start->diff($end)->days + 1; // Include both start and end
            
            $employee_id = $leave['employee_id'];
            $leave_type = $leave['leave_type'];
            $year = date('Y');
            
            // Get leave type ID
            $stmt = $pdo->prepare("SELECT id FROM leave_types WHERE company_id = ? AND name = ?");
            $stmt->execute([$company_id, $leave_type]);
            $leave_type_id = $stmt->fetchColumn();
            
            if ($leave_type_id) {
                // Check balance
                $stmt = $pdo->prepare("SELECT balance_days, used_days FROM employee_leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                $stmt->execute([$employee_id, $leave_type_id, $year]);
                $balance = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $available = ($balance['balance_days'] ?? 0) - ($balance['used_days'] ?? 0);
                
                if ($available < $days_count) {
                    throw new Exception("Insufficient leave balance. Available: $available days, Requested: $days_count days.");
                }
            }
            
            // Update leave request FIRST (this ensures columns exist before deducting balance)
            $stmt = $pdo->prepare("UPDATE leave_requests SET status='approved', days_count=?, approved_by=?, approved_at=NOW() WHERE id=? AND company_id=?");
            $stmt->execute([$days_count, $user_id, $id, $company_id]);
            
            // Deduct balance AFTER successful update
            if ($leave_type_id && isset($balance) && $balance) {
                $stmt = $pdo->prepare("UPDATE employee_leave_balances SET used_days = used_days + ?, updated_at = NOW() WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                $stmt->execute([$days_count, $employee_id, $leave_type_id, $year]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            log_audit($company_id, $user_id, 'APPROVE_LEAVE', "Approved {$days_count}-day {$leave_type} leave for employee ID {$employee_id}");
            
            echo json_encode(['status' => true, 'message' => "Leave approved. {$days_count} days deducted from balance."]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e; // Re-throw to be caught by outer handler
        }

    // Action: Reject Leave (Admin/HR)
    } elseif ($action === 'reject_leave') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
         
        $id = $_POST['leave_id'];
        $stmt = $pdo->prepare("UPDATE leave_requests SET status='rejected' WHERE id=? AND company_id=?");
        $stmt->execute([$id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Leave rejected.']);
    
    // Action: Get Employee Leave Balances
    } elseif ($action === 'get_balance') {
        $emp_id = $_POST['employee_id'] ?? null;
        
        if ($role === 'employee') {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $emp_id = $stmt->fetchColumn();
        }
        
        if (!$emp_id) throw new Exception("Employee not found.");
        
        $year = date('Y');
        $stmt = $pdo->prepare("
            SELECT lt.name as leave_type, lb.balance_days, lb.used_days, lb.carry_over_days,
                   (lb.balance_days + lb.carry_over_days - lb.used_days) as available
            FROM employee_leave_balances lb
            JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = ? AND lb.year = ?
            ORDER BY lt.name
        ");
        $stmt->execute([$emp_id, $year]);
        $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'balances' => $balances]);
    
    // Action: Get Leave Types for Company
    } elseif ($action === 'get_leave_types') {
        $stmt = $pdo->prepare("SELECT id, name, is_system FROM leave_types WHERE company_id = ? AND is_active = 1 ORDER BY is_system DESC, name ASC");
        $stmt->execute([$company_id]);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'types' => $types]);
    
    // Action: Add Custom Leave Type
    } elseif ($action === 'add_leave_type') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) throw new Exception("Leave type name is required.");
        
        // Sentence case
        $name = ucfirst(strtolower($name));
        
        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM leave_types WHERE company_id = ? AND name = ?");
        $stmt->execute([$company_id, $name]);
        if ($stmt->fetch()) throw new Exception("This leave type already exists.");
        
        $stmt = $pdo->prepare("INSERT INTO leave_types (company_id, name, is_system) VALUES (?, ?, 0)");
        $stmt->execute([$company_id, $name]);
        
        echo json_encode(['status' => true, 'message' => "Leave type '$name' added.", 'id' => $pdo->lastInsertId()]);
    
    // Action: Save Leave Policy for Category
    } elseif ($action === 'save_policy') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $category_id = $_POST['category_id'] ?: null; // null = all categories
        $leave_type_id = $_POST['leave_type_id'];
        $days_per_year = intval($_POST['days_per_year'] ?? 0);
        $carry_over = isset($_POST['carry_over']) ? 1 : 0;
        $max_carry = intval($_POST['max_carry_over_days'] ?? 0);
        $year = date('Y');
        
        // Upsert policy
        $stmt = $pdo->prepare("
            INSERT INTO leave_policies (company_id, category_id, leave_type_id, days_per_year, carry_over_allowed, max_carry_over_days)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE days_per_year = VALUES(days_per_year), carry_over_allowed = VALUES(carry_over_allowed), max_carry_over_days = VALUES(max_carry_over_days), updated_at = NOW()
        ");
        $stmt->execute([$company_id, $category_id, $leave_type_id, $days_per_year, $carry_over, $max_carry]);
        
        // Auto-sync employee balances for this policy
        $initialized = 0;
        $updated = 0;
        
        // Get employees affected by this policy
        $empSql = "SELECT id FROM employees WHERE company_id = ?";
        $params = [$company_id];
        if ($category_id) {
            $empSql .= " AND salary_category_id = ?";
            $params[] = $category_id;
        }
        $stmt = $pdo->prepare($empSql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($employees as $emp_id) {
            // Check if balance already exists
            $stmt = $pdo->prepare("SELECT id, used_days FROM employee_leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
            $stmt->execute([$emp_id, $leave_type_id, $year]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                // Create new balance
                $stmt = $pdo->prepare("INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance_days, used_days, year) VALUES (?, ?, ?, 0, ?)");
                $stmt->execute([$emp_id, $leave_type_id, $days_per_year, $year]);
                $initialized++;
            } else {
                // Always update balance_days to match new policy (preserves used_days)
                $stmt = $pdo->prepare("UPDATE employee_leave_balances SET balance_days = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$days_per_year, $existing['id']]);
                $updated++;
            }
        }
        
        $msg = 'Policy saved.';
        if ($initialized > 0) {
            $msg .= " Created balances for $initialized employee(s).";
        }
        if ($updated > 0) {
            $msg .= " Updated balances for $updated employee(s).";
        }
        
        echo json_encode(['status' => true, 'message' => $msg]);
    
    // Action: Get Policies
    } elseif ($action === 'get_policies') {
        $stmt = $pdo->prepare("
            SELECT lp.*, lt.name as leave_type_name, sc.name as category_name
            FROM leave_policies lp
            JOIN leave_types lt ON lp.leave_type_id = lt.id
            LEFT JOIN salary_categories sc ON lp.category_id = sc.id
            WHERE lp.company_id = ?
            ORDER BY sc.name, lt.name
        ");
        $stmt->execute([$company_id]);
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'policies' => $policies]);
    
    // Action: Delete Leave Policy
    } elseif ($action === 'delete_policy') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $policy_id = intval($_POST['policy_id'] ?? 0);
        if (!$policy_id) throw new Exception("Policy ID required.");
        
        // Get policy details before deletion
        $stmt = $pdo->prepare("SELECT * FROM leave_policies WHERE id = ? AND company_id = ?");
        $stmt->execute([$policy_id, $company_id]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$policy) throw new Exception("Policy not found.");
        
        $leave_type_id = $policy['leave_type_id'];
        $category_id = $policy['category_id'];
        $year = date('Y');
        
        // Get affected employees
        $empSql = "SELECT id FROM employees WHERE company_id = ?";
        $params = [$company_id];
        if ($category_id) {
            $empSql .= " AND salary_category_id = ?";
            $params[] = $category_id;
        }
        $stmt = $pdo->prepare($empSql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Set balance_days to 0 for affected employees (preserves used_days as history)
        $affected = 0;
        foreach ($employees as $emp_id) {
            $stmt = $pdo->prepare("UPDATE employee_leave_balances SET balance_days = 0, updated_at = NOW() WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
            $stmt->execute([$emp_id, $leave_type_id, $year]);
            if ($stmt->rowCount() > 0) $affected++;
        }
        
        // Delete the policy
        $stmt = $pdo->prepare("DELETE FROM leave_policies WHERE id = ? AND company_id = ?");
        $stmt->execute([$policy_id, $company_id]);
        
        $msg = "Policy deleted.";
        if ($affected > 0) {
            $msg .= " Reset balances for $affected employee(s).";
        }
        
        echo json_encode(['status' => true, 'message' => $msg]);
    
    // Action: Initialize Employee Balances (based on policy)
    } elseif ($action === 'init_balances') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $year = date('Y');
        
        // Get all active employees with their categories
        $stmt = $pdo->prepare("SELECT id, salary_category_id FROM employees WHERE company_id = ? AND employment_status = 'active'");
        $stmt->execute([$company_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $initialized = 0;
        foreach ($employees as $emp) {
            // Get policies for this category (or all-category policies)
            $stmt = $pdo->prepare("
                SELECT lp.leave_type_id, lp.days_per_year
                FROM leave_policies lp
                WHERE lp.company_id = ? AND (lp.category_id = ? OR lp.category_id IS NULL)
                ORDER BY lp.category_id DESC
            ");
            $stmt->execute([$company_id, $emp['salary_category_id']]);
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($policies as $pol) {
                // Insert or update balance
                $stmt_bal = $pdo->prepare("
                    INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance_days, year)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE balance_days = VALUES(balance_days)
                ");
                $stmt_bal->execute([$emp['id'], $pol['leave_type_id'], $pol['days_per_year'], $year]);
            }
            $initialized++;
        }
        
        echo json_encode(['status' => true, 'message' => "Initialized balances for $initialized employees."]);
    
    // Action: Delete Custom Leave Type (non-system only)
    } elseif ($action === 'delete_leave_type') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $type_id = intval($_POST['type_id'] ?? 0);
        if (!$type_id) throw new Exception("Invalid leave type ID.");
        
        // Check if it's a system type
        $stmt = $pdo->prepare("SELECT is_system FROM leave_types WHERE id = ? AND company_id = ?");
        $stmt->execute([$type_id, $company_id]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$type) throw new Exception("Leave type not found.");
        if ($type['is_system'] == 1) throw new Exception("Cannot delete system leave types.");
        
        // Check if type is used in any policies or balances
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_policies WHERE leave_type_id = ?");
        $stmt->execute([$type_id]);
        if ($stmt->fetchColumn() > 0) throw new Exception("Cannot delete. This leave type is used in policies.");
        
        // Soft delete (mark inactive)
        $stmt = $pdo->prepare("UPDATE leave_types SET is_active = 0 WHERE id = ? AND company_id = ?");
        $stmt->execute([$type_id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Leave type deleted.']);
    
    // Action: Update Leave Policy
    } elseif ($action === 'update_policy') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $policy_id = intval($_POST['policy_id'] ?? 0);
        $days_per_year = intval($_POST['days_per_year'] ?? 0);
        $max_carry = intval($_POST['max_carry_over_days'] ?? 0);
        
        if (!$policy_id) throw new Exception("Invalid policy ID.");
        
        $stmt = $pdo->prepare("UPDATE leave_policies SET days_per_year = ?, max_carry_over_days = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
        $stmt->execute([$days_per_year, $max_carry, $policy_id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Policy updated.']);
    
    // Action: Delete Leave Policy
    } elseif ($action === 'delete_policy') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $policy_id = intval($_POST['policy_id'] ?? 0);
        if (!$policy_id) throw new Exception("Invalid policy ID.");
        
        $stmt = $pdo->prepare("DELETE FROM leave_policies WHERE id = ? AND company_id = ?");
        $stmt->execute([$policy_id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Policy deleted.']);
        
    } else {
        throw new Exception("Unknown Action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
