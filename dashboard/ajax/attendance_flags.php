<?php
/**
 * AJAX Endpoint: Mark Absent Employees
 * Creates "Absent" records for active employees who haven't checked in for a date.
 * Also handles clearing review flags.
 */
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // ACTION 1: Mark absent employees for a specific date
    if ($action === 'mark_absent') {
        $date = $input['date'] ?? date('Y-m-d', strtotime('-1 day')); // Default: yesterday
        
        // Get all active employees
        $stmt_emp = $pdo->prepare("
            SELECT id, first_name, last_name 
            FROM employees 
            WHERE company_id = ? AND employment_status = 'active'
        ");
        $stmt_emp->execute([$company_id]);
        $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
        
        // Get absent deduction amount from policy (optional)
        $stmt_policy = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
        $stmt_policy->execute([$company_id]);
        $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
        
        // Default absent deduction (could be made configurable)
        $absent_deduction = floatval($policy['absent_deduction_amount'] ?? 0);
        
        $marked = 0;
        foreach ($employees as $emp) {
            // Check if they have a record for this date
            $stmt_check = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND date = ?");
            $stmt_check->execute([$emp['id'], $date]);
            
            if (!$stmt_check->fetch()) {
                // No record - mark as Absent
                $stmt_ins = $pdo->prepare("
                    INSERT INTO attendance_logs 
                    (company_id, employee_id, date, status, requires_review, review_reason, 
                     absent_deduction_amount, final_deduction_amount, method_used, created_at, updated_at)
                    VALUES (?, ?, ?, 'Absent', 1, 'Auto-marked: no check-in', ?, ?, 'system', NOW(), NOW())
                ");
                $stmt_ins->execute([$company_id, $emp['id'], $date, $absent_deduction, $absent_deduction]);
                $marked++;
            }
        }
        
        log_audit($company_id, $user_id, 'MARK_ABSENT', "Marked $marked employees as absent for $date");
        echo json_encode(['status' => true, 'message' => "Marked $marked employees as absent for $date"]);
        exit;
    }
    
    // ACTION 2: Clear review flag (admin reviewed)
    if ($action === 'clear_flag') {
        $log_id = $input['log_id'] ?? null;
        if (!$log_id) throw new Exception("Missing log ID");
        
        $stmt = $pdo->prepare("UPDATE attendance_logs SET requires_review = 0, review_reason = NULL, updated_at = NOW() WHERE id = ? AND company_id = ?");
        $stmt->execute([$log_id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Flag cleared']);
        exit;
    }
    
    // ACTION 3: Get flagged records
    if ($action === 'get_flagged') {
        $stmt = $pdo->prepare("
            SELECT al.*, e.first_name, e.last_name, e.payroll_id
            FROM attendance_logs al
            JOIN employees e ON al.employee_id = e.id
            WHERE al.company_id = ? AND al.requires_review = 1
            ORDER BY al.date DESC, e.last_name ASC
            LIMIT 50
        ");
        $stmt->execute([$company_id]);
        $flagged = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'data' => $flagged]);
        exit;
    }
    
    throw new Exception("Invalid action");
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
