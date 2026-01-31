<?php
/**
 * Supplementary Payroll AJAX Handler
 * Handles bulk/individual entry, staging, and execution
 */
require_once '../../includes/functions.php';
require_once '../../includes/payroll_engine.php';
require_login();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        
        case 'add_bulk':
            // Add same bonus to all employees matching filter
            $department = trim($input['department'] ?? '');
            $category = $input['category'] ?? '';
            $bonus_name = trim($input['bonus_name'] ?? '');
            $amount = floatval($input['amount'] ?? 0);
            $month = intval($input['month']);
            $year = intval($input['year']);
            $session_id = $input['session_id'];
            $notes = trim($input['notes'] ?? '');
            
            if (!$bonus_name || $amount <= 0) {
                throw new Exception("Bonus name and amount are required");
            }
            
            // Build employee query with filters
            $sql = "SELECT e.id FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    WHERE e.company_id = ? 
                    AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')";
            $params = [$company_id];
            
            if (!empty($department)) {
                $sql .= " AND d.name = ?";
                $params[] = $department;
            }
            if (!empty($category) && is_numeric($category)) {
                $sql .= " AND e.salary_category_id = ?";
                $params[] = intval($category);
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($employees)) {
                throw new Exception("No employees match the selected filters");
            }
            
            // Insert staging entries
            $ins = $pdo->prepare("INSERT INTO supplementary_entries 
                (company_id, employee_id, bonus_name, amount, notes, payroll_month, payroll_year, session_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $added = 0;
            foreach ($employees as $emp_id) {
                $ins->execute([$company_id, $emp_id, $bonus_name, $amount, $notes, $month, $year, $session_id, $user_id]);
                $added++;
            }
            
            echo json_encode(['status' => true, 'added' => $added, 'message' => "Added $added entries"]);
            break;
            
        case 'add_individual':
            // Add bonus for single employee
            $employee_id = intval($input['employee_id']);
            $bonus_name = trim($input['bonus_name'] ?? '');
            $amount = floatval($input['amount'] ?? 0);
            $month = intval($input['month']);
            $year = intval($input['year']);
            $session_id = $input['session_id'];
            $notes = trim($input['notes'] ?? '');
            
            if (!$employee_id || !$bonus_name || $amount <= 0) {
                throw new Exception("Employee, bonus name, and amount are required");
            }
            
            $ins = $pdo->prepare("INSERT INTO supplementary_entries 
                (company_id, employee_id, bonus_name, amount, notes, payroll_month, payroll_year, session_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$company_id, $employee_id, $bonus_name, $amount, $notes, $month, $year, $session_id, $user_id]);
            
            echo json_encode(['status' => true, 'message' => 'Entry added']);
            break;
            
        case 'get_staging':
            // Fetch current staging entries
            $session_id = $input['session_id'];
            $month = intval($input['month']);
            $year = intval($input['year']);
            
            $stmt = $pdo->prepare("SELECT se.*, e.first_name, e.last_name, e.payroll_id, d.name as department
                FROM supplementary_entries se
                JOIN employees e ON se.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE se.session_id = ? AND se.company_id = ?
                AND se.payroll_month = ? AND se.payroll_year = ?
                ORDER BY e.last_name, e.first_name");
            $stmt->execute([$session_id, $company_id, $month, $year]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $total_amount = 0;
            foreach ($entries as $e) {
                $total_amount += floatval($e['amount']);
            }
            
            echo json_encode([
                'status' => true, 
                'entries' => $entries,
                'count' => count($entries),
                'total_amount' => $total_amount
            ]);
            break;
            
        case 'remove_entry':
            // Remove single staging entry
            $entry_id = intval($input['entry_id']);
            $stmt = $pdo->prepare("DELETE FROM supplementary_entries WHERE id = ? AND company_id = ?");
            $stmt->execute([$entry_id, $company_id]);
            echo json_encode(['status' => true, 'message' => 'Entry removed']);
            break;
            
        case 'clear_staging':
            // Clear all staging entries for session
            $session_id = $input['session_id'];
            $stmt = $pdo->prepare("DELETE FROM supplementary_entries WHERE session_id = ? AND company_id = ?");
            $stmt->execute([$session_id, $company_id]);
            echo json_encode(['status' => true, 'message' => 'Staging cleared']);
            break;
            
        case 'execute':
            // Run supplementary payroll from staging
            $session_id = $input['session_id'];
            $month = intval($input['month']);
            $year = intval($input['year']);
            
            // Fetch staging entries grouped by employee
            $stmt = $pdo->prepare("SELECT se.employee_id, SUM(se.amount) as total_bonus,
                    GROUP_CONCAT(CONCAT(se.bonus_name, ': ', se.amount) SEPARATOR '; ') as bonus_details
                FROM supplementary_entries se
                WHERE se.session_id = ? AND se.company_id = ?
                AND se.payroll_month = ? AND se.payroll_year = ?
                GROUP BY se.employee_id");
            $stmt->execute([$session_id, $company_id, $month, $year]);
            $grouped = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($grouped)) {
                throw new Exception("No entries to process");
            }
            
            // Determine supplementary run number
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_runs 
                WHERE company_id = ? AND period_month = ? AND period_year = ? 
                AND payroll_type LIKE 'supplementary%'");
            $stmt->execute([$company_id, $month, $year]);
            $supp_count = intval($stmt->fetchColumn()) + 1;
            $payroll_type = "supplementary_$supp_count";
            
            // Get statutory settings for PAYE
            $stmt = $pdo->prepare("SELECT * FROM statutory_settings WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['enable_paye' => 1];
            
            $pdo->beginTransaction();
            
            // Create payroll run
            $stmt = $pdo->prepare("INSERT INTO payroll_runs (company_id, period_month, period_year, payroll_type, status)
                VALUES (?, ?, ?, ?, 'draft')");
            $stmt->execute([$company_id, $month, $year, $payroll_type]);
            $run_id = $pdo->lastInsertId();
            
            $ins_entry = $pdo->prepare("INSERT INTO payroll_entries 
                (payroll_run_id, employee_id, gross_salary, total_allowances, total_deductions, net_pay)
                VALUES (?, ?, ?, 0, ?, ?)");
            $ins_snapshot = $pdo->prepare("INSERT INTO payroll_snapshots (payroll_entry_id, snapshot_json) VALUES (?, ?)");
            
            foreach ($grouped as $emp) {
                $bonus_amount = floatval($emp['total_bonus']);
                
                // Calculate PAYE on bonus only (annualize, calculate, then monthly)
                $paye = 0;
                if ($settings['enable_paye']) {
                    $annual_bonus = $bonus_amount * 12;
                    // Simplified PAYE for bonus-only: No reliefs except basic exemption
                    $paye = calculate_paye($annual_bonus, 0, 0, 0, $year) / 12;
                }
                
                $net_pay = $bonus_amount - $paye;
                
                $ins_entry->execute([$run_id, $emp['employee_id'], $bonus_amount, $paye, $net_pay]);
                $entry_id = $pdo->lastInsertId();
                
                // Create snapshot
                $snapshot = [
                    'type' => 'supplementary',
                    'bonus_total' => $bonus_amount,
                    'bonus_details' => $emp['bonus_details'],
                    'statutory' => [
                        'paye' => $paye
                    ]
                ];
                $ins_snapshot->execute([$entry_id, json_encode($snapshot)]);
            }
            
            // Clear staging
            $stmt = $pdo->prepare("DELETE FROM supplementary_entries WHERE session_id = ? AND company_id = ?");
            $stmt->execute([$session_id, $company_id]);
            
            $pdo->commit();
            
            echo json_encode([
                'status' => true, 
                'run_id' => $run_id,
                'payroll_type' => $payroll_type,
                'employees_processed' => count($grouped),
                'message' => 'Supplementary payroll generated successfully'
            ]);
            break;
            
        case 'search_employees':
            // Search employees for individual entry
            $query = trim($input['query'] ?? '');
            
            if (strlen($query) < 2) {
                echo json_encode(['status' => true, 'employees' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT e.id, e.first_name, e.last_name, e.payroll_id, d.name as department
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.company_id = ?
                AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')
                AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.payroll_id LIKE ?)
                ORDER BY e.last_name, e.first_name
                LIMIT 20");
            $search = "%$query%";
            $stmt->execute([$company_id, $search, $search, $search]);
            
            echo json_encode(['status' => true, 'employees' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        case 'get_sheet':
            // Get generated payroll sheet by run_id
            $run_id = intval($input['run_id']);
            
            $stmt = $pdo->prepare("SELECT pe.*, 
                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                    e.payroll_id,
                    d.name as department
                FROM payroll_entries pe
                JOIN employees e ON pe.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE pe.payroll_run_id = ?
                ORDER BY e.last_name, e.first_name");
            $stmt->execute([$run_id]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $gross = 0; $deductions = 0; $net = 0;
            foreach ($entries as $e) {
                $gross += floatval($e['gross_salary']);
                $deductions += floatval($e['total_deductions']);
                $net += floatval($e['net_pay']);
            }
            
            echo json_encode([
                'status' => true, 
                'entries' => $entries,
                'totals' => [
                    'gross' => $gross,
                    'deductions' => $deductions,
                    'net' => $net
                ]
            ]);
            break;
            
        case 'get_runs':
            // Get supplementary payroll runs for this period
            $month = intval($input['month']);
            $year = intval($input['year']);
            
            $stmt = $pdo->prepare("SELECT pr.*, 
                    (SELECT COUNT(*) FROM payroll_entries WHERE payroll_run_id = pr.id) as employee_count,
                    (SELECT SUM(net_pay) FROM payroll_entries WHERE payroll_run_id = pr.id) as total_net
                FROM payroll_runs pr
                WHERE pr.company_id = ? AND pr.period_month = ? AND pr.period_year = ?
                AND pr.payroll_type LIKE 'supplementary%'
                ORDER BY pr.created_at DESC");
            $stmt->execute([$company_id, $month, $year]);
            
            echo json_encode(['status' => true, 'runs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        case 'save_sheet_edits':
            // Save edited payroll entries with audit trail
            $run_id = intval($input['run_id']);
            $entries = $input['entries'];
            
            if (empty($entries)) {
                throw new Exception("No entries to save");
            }
            
            // Verify run belongs to this company
            $stmt = $pdo->prepare("SELECT id FROM payroll_runs WHERE id = ? AND company_id = ?");
            $stmt->execute([$run_id, $company_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Invalid payroll run");
            }
            
            $pdo->beginTransaction();
            
            // Get user name for audit
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $user_name = $user ? $user['name'] : 'Unknown User';
            
            // Update each entry
            $update = $pdo->prepare("UPDATE payroll_entries 
                SET gross_salary = ?, total_deductions = ?, net_pay = ?, updated_at = NOW()
                WHERE id = ? AND payroll_run_id = ?");
            
            foreach ($entries as $entry) {
                $update->execute([
                    floatval($entry['gross_salary']),
                    floatval($entry['total_deductions']),
                    floatval($entry['net_pay']),
                    intval($entry['id']),
                    $run_id
                ]);
            }
            
            // Update run's updated_at for audit trail
            $stmt = $pdo->prepare("UPDATE payroll_runs SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$run_id]);
            
            $pdo->commit();
            
            echo json_encode([
                'status' => true, 
                'message' => 'Changes saved',
                'edited_by' => $user_name
            ]);
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
