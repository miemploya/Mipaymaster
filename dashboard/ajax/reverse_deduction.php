<?php
/**
 * AJAX: Reverse Deduction
 * Reverses a deduction on an attendance record and logs to audit trail
 */
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$employee_id = (int)($input['employee_id'] ?? 0);
$record_date = $input['record_date'] ?? '';
$reason = trim($input['reason'] ?? '');

if (!$employee_id || !$record_date) {
    echo json_encode(['status' => false, 'message' => 'Employee ID and date required']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['status' => false, 'message' => 'Reversal reason is required']);
    exit;
}

try {
    // Verify the record exists and belongs to the company's employee
    $stmt = $pdo->prepare("
        SELECT al.id, al.employee_id, al.date, al.status, al.auto_deduction_amount, al.final_deduction_amount,
               e.first_name, e.last_name
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        WHERE al.employee_id = ? AND al.date = ? AND e.company_id = ?
    ");
    $stmt->execute([$employee_id, $record_date, $company_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        echo json_encode(['status' => false, 'message' => 'Record not found']);
        exit;
    }
    
    if ($record['final_deduction_amount'] == 0 && $record['auto_deduction_amount'] == 0) {
        echo json_encode(['status' => false, 'message' => 'No deduction to reverse on this record']);
        exit;
    }
    
    $original_amount = $record['final_deduction_amount'] > 0 ? $record['final_deduction_amount'] : $record['auto_deduction_amount'];
    
    // Update the record
    $stmt_update = $pdo->prepare("
        UPDATE attendance_logs 
        SET deduction_reversed = 1,
            final_deduction_amount = 0,
            reversal_reason = ?,
            reversed_by = ?,
            reversed_at = NOW()
        WHERE id = ?
    ");
    $stmt_update->execute([$reason, $user_id, $record['id']]);
    
    // Log to audit trail
    if (function_exists('log_audit')) {
        log_audit($pdo, $company_id, $user_id, 'deduction_reversed', 
            "Reversed deduction of ₦" . number_format($original_amount, 2) . 
            " for " . $record['first_name'] . " " . $record['last_name'] . 
            " on " . $record_date . ". Reason: " . $reason,
            ['employee_id' => $employee_id, 'date' => $record_date, 'amount' => $original_amount]
        );
    }
    
    echo json_encode([
        'status' => true, 
        'message' => 'Deduction reversed successfully',
        'reversed_amount' => $original_amount
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
