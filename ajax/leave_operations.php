<?php
require_once '../includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid Request']);
    exit;
}

require_login();

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

    // Action: Approve Leave (Admin/HR)
    } elseif ($action === 'approve_leave') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
        
        $id = $_POST['leave_id'];
        $stmt = $pdo->prepare("UPDATE leave_requests SET status='approved' WHERE id=? AND company_id=?");
        $stmt->execute([$id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Leave approved.']);

    // Action: Reject Leave (Admin/HR)
    } elseif ($action === 'reject_leave') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) throw new Exception("Unauthorized.");
         
        $id = $_POST['leave_id'];
        $stmt = $pdo->prepare("UPDATE leave_requests SET status='rejected' WHERE id=? AND company_id=?");
        $stmt->execute([$id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Leave rejected.']);
        
    } else {
        throw new Exception("Unknown Action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
