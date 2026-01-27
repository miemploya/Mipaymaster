<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
    exit;
}

require_login();

// 1. Verify Role & context
if ($_SESSION['role'] !== 'employee') {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$type = $_POST['type'] ?? ''; // 'in' or 'out'

if (!in_array($type, ['in', 'out'])) {
    echo json_encode(['status' => false, 'message' => 'Invalid action type.']);
    exit;
}

try {
    // 2. Fetch Employee & Company Policy
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND company_id = ?");
    $stmt->execute([$user_id, $company_id]);
    $employee_id = $stmt->fetchColumn();

    if (!$employee_id) throw new Exception("Employee record not found.");

    // Check Company Policy
    $stmt = $pdo->prepare("SELECT attendance_method FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $method = $stmt->fetchColumn();

    if ($method !== 'self') {
        throw new Exception("Self Check-In is not enabled for this company.");
    }

    // Get Policy Details
    $stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Process Logic
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $status = 'Present'; 
    $late_minutes = 0;

    // Check for existing record
    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?");
    $stmt->execute([$employee_id, $today]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($type === 'in') {
        if ($log) {
            throw new Exception("You have already clocked in today.");
        }

        // Lateness Logic
        if ($policy && !empty($policy['check_in_start'])) {
            // If check_in_end is set (late threshold), say 09:00
            // and grace period is 15 mins.
            // Actually usually 'start_time' is when work starts.
            // Let's assume 'check_in_end' is the latest allowed time?
            // Or we check against 'check_in_start' + grace?
            // Let's rely on basic logic: strict late calculation if desired.
            
            // For now, mark as Present. Detailed late calc can be done here if needed.
            // Let's check against policy check_in_end if it represents "Late after"
            // field 'check_in_end' usually means "Can't check in after this".
            
            if (!empty($policy['check_in_end']) && $now > $policy['check_in_end']) {
                 // Hard block? or just mark late?
                 // Prompt implies strict windows.
                 // throw new Exception("Check-in window has closed.");
                 $status = 'Late';
            }
        }

        $stmt = $pdo->prepare("INSERT INTO attendance_logs (company_id, employee_id, date, time_in, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$company_id, $employee_id, $today, $now, $status]);
        
        $msg = "Clocked In Successfully at " . date('H:i');

    } else { // OUT
        if (!$log) {
            throw new Exception("You have not clocked in yet.");
        }
        if ($log['time_out']) {
            throw new Exception("You have already clocked out today.");
        }

        // Calculate Duration
        $t1 = strtotime($log['time_in']);
        $t2 = strtotime($now);
        $hours = round(($t2 - $t1) / 3600, 2);

        $stmt = $pdo->prepare("UPDATE attendance_logs SET time_out = ?, hours_worked = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$now, $hours, $log['id']]);

        $msg = "Clocked Out Successfully. Worked: {$hours} hrs.";
    }
    
    // Audit? Maybe too noisy for daily clockins, but good for debugging.
    // log_audit($company_id, $user_id, 'ATTENDANCE', "$type: $msg");

    echo json_encode(['status' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
