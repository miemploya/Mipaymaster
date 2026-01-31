<?php
/**
 * AJAX Handler: Shift Management CRUD Operations
 * Handles: list, create, update, delete, get_employees, assign_employees
 */
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

try {
    $company_id = $_SESSION['company_id'];
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // List all shifts with employee count
            $stmt = $pdo->prepare("
                SELECT s.*, 
                    (SELECT COUNT(*) FROM employee_attendance_assignments ea 
                     WHERE ea.shift_id = s.id AND ea.is_active = 1) as employee_count
                FROM attendance_shifts s 
                WHERE s.company_id = ? AND s.is_active = 1
                ORDER BY s.name
            ");
            $stmt->execute([$company_id]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch schedules/hours for each shift based on type
            foreach ($shifts as &$shift) {
                $shift_type = $shift['shift_type'] ?? 'fixed';
                
                if ($shift_type === 'fixed') {
                    // 7-day schedule
                    $stmt_sched = $pdo->prepare("
                        SELECT * FROM attendance_shift_schedules 
                        WHERE shift_id = ? ORDER BY day_of_week
                    ");
                    $stmt_sched->execute([$shift['id']]);
                    $shift['schedules'] = $stmt_sched->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Daily hours for rotational/weekly/monthly
                    $stmt_hours = $pdo->prepare("SELECT * FROM attendance_shift_daily_hours WHERE shift_id = ?");
                    $stmt_hours->execute([$shift['id']]);
                    $shift['daily_hours'] = $stmt_hours->fetch(PDO::FETCH_ASSOC);
                }
            }
            
            // Get default attendance policy for this company
            $stmt_policy = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
            $stmt_policy->execute([$company_id]);
            $default_policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
            
            // Count employees NOT assigned to any shift
            $stmt_unassigned = $pdo->prepare("
                SELECT COUNT(*) as cnt FROM employees e
                WHERE e.company_id = ? AND e.employment_status = 'Active'
                AND e.id NOT IN (
                    SELECT employee_id FROM employee_attendance_assignments 
                    WHERE is_active = 1
                )
            ");
            $stmt_unassigned->execute([$company_id]);
            $unassigned_count = $stmt_unassigned->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
            
            // Total active employees
            $stmt_total = $pdo->prepare("SELECT COUNT(*) as cnt FROM employees WHERE company_id = ? AND employment_status = 'Active'");
            $stmt_total->execute([$company_id]);
            $total_employees = $stmt_total->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
            
            echo json_encode([
                'status' => true, 
                'data' => $shifts,
                'default_policy' => $default_policy,
                'unassigned_count' => intval($unassigned_count),
                'total_employees' => intval($total_employees)
            ]);
            break;
            
        case 'get_default_employees':
            // Get all employees NOT assigned to any shift (using default schedule)
            $stmt = $pdo->prepare("
                SELECT e.id, e.payroll_id, e.first_name, e.last_name, e.department, e.job_title
                FROM employees e
                WHERE e.company_id = ? AND e.employment_status = 'Active'
                AND e.id NOT IN (
                    SELECT employee_id FROM employee_attendance_assignments 
                    WHERE is_active = 1
                )
                ORDER BY e.first_name, e.last_name
            ");
            $stmt->execute([$company_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => true, 'data' => $employees]);
            break;
            
        case 'create':
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $shift_type = $input['shift_type'] ?? 'fixed';
            $schedules = $input['schedules'] ?? [];
            $daily_hours = $input['daily_hours'] ?? [];
            $weeks_on = intval($input['weeks_on'] ?? 1);
            $weeks_off = intval($input['weeks_off'] ?? 1);
            
            if (empty($name)) {
                throw new Exception('Shift name is required.');
            }
            
            // Validate shift_type
            $valid_types = ['fixed', 'rotational', 'weekly', 'monthly'];
            if (!in_array($shift_type, $valid_types)) {
                $shift_type = 'fixed';
            }
            
            $pdo->beginTransaction();
            
            // Insert shift with type
            $stmt = $pdo->prepare("
                INSERT INTO attendance_shifts (company_id, name, description, shift_type, weeks_on, weeks_off) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$company_id, $name, $description, $shift_type, $weeks_on, $weeks_off]);
            $shift_id = $pdo->lastInsertId();
            
            if ($shift_type === 'fixed') {
                // Insert 7-day schedules
                $stmt_sched = $pdo->prepare("
                    INSERT INTO attendance_shift_schedules 
                    (shift_id, day_of_week, is_working_day, check_in_time, check_out_time, grace_period_minutes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $day_names = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                for ($day = 0; $day <= 6; $day++) {
                    $day_key = $day_names[$day];
                    $is_working = isset($schedules[$day_key]['enabled']) ? (int)$schedules[$day_key]['enabled'] : 0;
                    $check_in = $schedules[$day_key]['check_in'] ?? null;
                    $check_out = $schedules[$day_key]['check_out'] ?? null;
                    $grace = intval($schedules[$day_key]['grace'] ?? 15);
                    
                    $stmt_sched->execute([$shift_id, $day, $is_working, $check_in, $check_out, $grace]);
                }
            } else {
                // Insert daily hours for rotational/weekly/monthly shifts
                $check_in = $daily_hours['check_in'] ?? '08:00';
                $check_out = $daily_hours['check_out'] ?? ($shift_type === 'rotational' ? '08:00' : '17:00');
                $grace = intval($daily_hours['grace'] ?? 15);
                
                $stmt_hours = $pdo->prepare("
                    INSERT INTO attendance_shift_daily_hours (shift_id, check_in_time, check_out_time, grace_period_minutes)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_hours->execute([$shift_id, $check_in, $check_out, $grace]);
            }
            
            $pdo->commit();
            echo json_encode(['status' => true, 'message' => 'Shift created successfully.', 'shift_id' => $shift_id]);
            break;
            
        case 'update':
            $shift_id = intval($input['shift_id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $shift_type = $input['shift_type'] ?? 'fixed';
            $schedules = $input['schedules'] ?? [];
            $daily_hours = $input['daily_hours'] ?? [];
            $weeks_on = intval($input['weeks_on'] ?? 1);
            $weeks_off = intval($input['weeks_off'] ?? 1);
            
            if (!$shift_id || empty($name)) {
                throw new Exception('Shift ID and name are required.');
            }
            
            // Verify ownership and get current type
            $stmt = $pdo->prepare("SELECT id, shift_type FROM attendance_shifts WHERE id = ? AND company_id = ?");
            $stmt->execute([$shift_id, $company_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new Exception('Shift not found.');
            }
            
            $pdo->beginTransaction();
            
            // Update shift with type
            $stmt = $pdo->prepare("
                UPDATE attendance_shifts 
                SET name = ?, description = ?, shift_type = ?, weeks_on = ?, weeks_off = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $shift_type, $weeks_on, $weeks_off, $shift_id]);
            
            if ($shift_type === 'fixed') {
                // Update 7-day schedules
                $stmt_sched = $pdo->prepare("
                    INSERT INTO attendance_shift_schedules 
                    (shift_id, day_of_week, is_working_day, check_in_time, check_out_time, grace_period_minutes)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_working_day = VALUES(is_working_day), 
                        check_in_time = VALUES(check_in_time), check_out_time = VALUES(check_out_time), 
                        grace_period_minutes = VALUES(grace_period_minutes)
                ");
                
                $day_names = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                for ($day = 0; $day <= 6; $day++) {
                    $day_key = $day_names[$day];
                    $is_working = isset($schedules[$day_key]['enabled']) ? (int)$schedules[$day_key]['enabled'] : 0;
                    $check_in = $schedules[$day_key]['check_in'] ?? null;
                    $check_out = $schedules[$day_key]['check_out'] ?? null;
                    $grace = intval($schedules[$day_key]['grace'] ?? 15);
                    
                    $stmt_sched->execute([$shift_id, $day, $is_working, $check_in, $check_out, $grace]);
                }
            } else {
                // Update daily hours for rotational/weekly/monthly
                $check_in = $daily_hours['check_in'] ?? '08:00';
                $check_out = $daily_hours['check_out'] ?? ($shift_type === 'rotational' ? '08:00' : '17:00');
                $grace = intval($daily_hours['grace'] ?? 15);
                
                $stmt_hours = $pdo->prepare("
                    INSERT INTO attendance_shift_daily_hours (shift_id, check_in_time, check_out_time, grace_period_minutes)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE check_in_time = VALUES(check_in_time), 
                        check_out_time = VALUES(check_out_time), grace_period_minutes = VALUES(grace_period_minutes)
                ");
                $stmt_hours->execute([$shift_id, $check_in, $check_out, $grace]);
            }
            
            $pdo->commit();
            echo json_encode(['status' => true, 'message' => 'Shift updated successfully.']);
            break;
            
        case 'delete':
            $shift_id = intval($input['shift_id'] ?? 0);
            if (!$shift_id) {
                throw new Exception('Shift ID is required.');
            }
            
            // Soft delete
            $stmt = $pdo->prepare("UPDATE attendance_shifts SET is_active = 0 WHERE id = ? AND company_id = ?");
            $stmt->execute([$shift_id, $company_id]);
            
            // Deactivate employee assignments to this shift
            $stmt = $pdo->prepare("UPDATE employee_attendance_assignments SET is_active = 0 WHERE shift_id = ?");
            $stmt->execute([$shift_id]);
            
            echo json_encode(['status' => true, 'message' => 'Shift deleted successfully.']);
            break;
            
        case 'get_shift':
            $shift_id = intval($input['shift_id'] ?? $_GET['shift_id'] ?? 0);
            if (!$shift_id) {
                throw new Exception('Shift ID is required.');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM attendance_shifts WHERE id = ? AND company_id = ?");
            $stmt->execute([$shift_id, $company_id]);
            $shift = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shift) {
                throw new Exception('Shift not found.');
            }
            
            // Get schedules or daily hours based on type
            $shift_type = $shift['shift_type'] ?? 'fixed';
            if ($shift_type === 'fixed') {
                $stmt_sched = $pdo->prepare("SELECT * FROM attendance_shift_schedules WHERE shift_id = ? ORDER BY day_of_week");
                $stmt_sched->execute([$shift_id]);
                $shift['schedules'] = $stmt_sched->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt_hours = $pdo->prepare("SELECT * FROM attendance_shift_daily_hours WHERE shift_id = ?");
                $stmt_hours->execute([$shift_id]);
                $shift['daily_hours'] = $stmt_hours->fetch(PDO::FETCH_ASSOC);
            }
            
            // Get assigned employees with cycle_start_date
            $stmt_emp = $pdo->prepare("
                SELECT e.id, e.first_name, e.last_name, e.payroll_id, d.name as department,
                       ea.cycle_start_date
                FROM employee_attendance_assignments ea
                JOIN employees e ON ea.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE ea.shift_id = ? AND ea.is_active = 1 AND e.status = 'active'
            ");
            $stmt_emp->execute([$shift_id]);
            $shift['employees'] = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => true, 'data' => $shift]);
            break;
            
        case 'get_unassigned_employees':
            // Get ALL active employees with their current shift assignment (if any)
            // This allows viewing and reassigning employees between shifts
            $current_shift_id = intval($input['shift_id'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT e.id, e.first_name, e.last_name, e.payroll_id, d.name as department,
                       ea.shift_id as current_shift_id,
                       s.name as current_shift_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN employee_attendance_assignments ea ON e.id = ea.employee_id AND ea.is_active = 1
                LEFT JOIN attendance_shifts s ON ea.shift_id = s.id
                WHERE e.company_id = ? AND e.employment_status = 'Active'
                ORDER BY e.first_name, e.last_name
            ");
            $stmt->execute([$company_id]);
            $all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Separate into already on this shift vs available
            $on_this_shift = [];
            $available = [];
            
            foreach ($all_employees as $emp) {
                if ($current_shift_id && intval($emp['current_shift_id']) === $current_shift_id) {
                    $on_this_shift[] = $emp;
                } else {
                    $available[] = $emp;
                }
            }
            
            echo json_encode([
                'status' => true, 
                'data' => $available,
                'on_this_shift' => $on_this_shift,
                'all_employees' => $all_employees
            ]);
            break;
            
        case 'assign_employees':
            $shift_id = intval($input['shift_id'] ?? 0);
            $employee_ids = $input['employee_ids'] ?? [];
            $cycle_start_date = $input['cycle_start_date'] ?? date('Y-m-d'); // Default to today
            
            if (!$shift_id) {
                throw new Exception('Shift ID is required.');
            }
            
            // Validate cycle_start_date format
            if ($cycle_start_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycle_start_date)) {
                $cycle_start_date = date('Y-m-d');
            }
            
            $pdo->beginTransaction();
            
            // Remove current assignments for this shift
            $stmt = $pdo->prepare("UPDATE employee_attendance_assignments SET is_active = 0 WHERE shift_id = ?");
            $stmt->execute([$shift_id]);
            
            // Add new assignments with cycle_start_date
            $stmt_ins = $pdo->prepare("
                INSERT INTO employee_attendance_assignments 
                (employee_id, attendance_mode, shift_id, cycle_start_date, effective_from, is_active)
                VALUES (?, 'shift', ?, ?, CURDATE(), 1)
                ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), attendance_mode = 'shift', 
                    cycle_start_date = VALUES(cycle_start_date), is_active = 1
            ");
            
            foreach ($employee_ids as $emp_id) {
                // First deactivate any existing assignment for this employee
                $stmt_deact = $pdo->prepare("UPDATE employee_attendance_assignments SET is_active = 0 WHERE employee_id = ?");
                $stmt_deact->execute([$emp_id]);
                
                // Then create new assignment with cycle start
                $stmt_ins->execute([$emp_id, $shift_id, $cycle_start_date]);
            }
            
            $pdo->commit();
            echo json_encode(['status' => true, 'message' => count($employee_ids) . ' employees assigned to shift.']);
            break;
            
        case 'remove_from_shift':
            $employee_id = intval($input['employee_id'] ?? 0);
            if (!$employee_id) {
                throw new Exception('Employee ID is required.');
            }
            
            $stmt = $pdo->prepare("UPDATE employee_attendance_assignments SET is_active = 0 WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            
            echo json_encode(['status' => true, 'message' => 'Employee removed from shift.']);
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
