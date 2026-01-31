<?php
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];
$notifications = [];

try {
    // 1. Recent Payroll Runs (last 30 days)
    $stmt = $pdo->prepare("
        SELECT id, month, year, status, created_at 
        FROM payroll_runs 
        WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$company_id]);
    $payrollRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
    
    foreach ($payrollRuns as $run) {
        $statusLabel = $run['status'] === 'approved' ? 'Completed' : 'Draft';
        $notifications[] = [
            'id' => 'payroll_' . $run['id'],
            'type' => 'payroll',
            'icon' => 'wallet',
            'color' => $run['status'] === 'approved' ? 'green' : 'amber',
            'title' => 'Payroll ' . $statusLabel,
            'message' => $months[$run['month']] . ' ' . $run['year'] . ' payroll ' . 
                        ($run['status'] === 'approved' ? 'has been processed successfully.' : 'is pending approval.'),
            'action' => 'View',
            'action_url' => 'payroll.php?view=sheet&month=' . $run['month'] . '&year=' . $run['year'],
            'timestamp' => $run['created_at']
        ];
    }
    
    // 2. Pending Leave Requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM leave_requests 
        WHERE company_id = ? AND status = 'pending'
    ");
    $stmt->execute([$company_id]);
    $pendingLeaves = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($pendingLeaves > 0) {
        $notifications[] = [
            'id' => 'leave_pending',
            'type' => 'leave',
            'icon' => 'calendar-check',
            'color' => 'amber',
            'title' => 'Approval Required',
            'message' => $pendingLeaves . ' leave request' . ($pendingLeaves > 1 ? 's are' : ' is') . ' pending your approval.',
            'action' => 'Review',
            'action_url' => 'leaves.php',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // 3. Pending Loan Requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM loans 
        WHERE company_id = ? AND status = 'pending'
    ");
    $stmt->execute([$company_id]);
    $pendingLoans = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($pendingLoans > 0) {
        $notifications[] = [
            'id' => 'loan_pending',
            'type' => 'loan',
            'icon' => 'banknote',
            'color' => 'blue',
            'title' => 'Loan Approval Needed',
            'message' => $pendingLoans . ' loan application' . ($pendingLoans > 1 ? 's require' : ' requires') . ' approval.',
            'action' => 'Review',
            'action_url' => 'loans.php',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // 4. Employees with Missing Info
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM employees 
        WHERE company_id = ? AND status = 'active' AND (salary_category_id IS NULL OR salary_category_id = 0)
    ");
    $stmt->execute([$company_id]);
    $missingCategory = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($missingCategory > 0) {
        $notifications[] = [
            'id' => 'employee_missing',
            'type' => 'warning',
            'icon' => 'alert-triangle',
            'color' => 'red',
            'title' => 'Action Required',
            'message' => $missingCategory . ' employee' . ($missingCategory > 1 ? 's have' : ' has') . ' no salary category assigned.',
            'action' => 'Fix',
            'action_url' => 'employees.php',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // 5. Upcoming Loan Repayments (within 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM loans 
        WHERE company_id = ? AND status = 'active' 
        AND next_deduction_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$company_id]);
    $upcomingRepayments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($upcomingRepayments > 0) {
        $notifications[] = [
            'id' => 'loan_repayment',
            'type' => 'info',
            'icon' => 'clock',
            'color' => 'brand',
            'title' => 'Upcoming Deductions',
            'message' => $upcomingRepayments . ' loan repayment' . ($upcomingRepayments > 1 ? 's' : '') . ' due within 7 days.',
            'action' => 'View',
            'action_url' => 'loans.php',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // Sort by timestamp (newest first) and limit
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    $notifications = array_slice($notifications, 0, 10);
    
    echo json_encode([
        'status' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage(),
        'notifications' => []
    ]);
}
?>
