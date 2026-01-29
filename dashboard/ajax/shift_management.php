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
            
            // Fetch schedules for each shift
            foreach ($shifts as &$shift) {
                $stmt_sched = $pdo->prepare("
                    SELECT * FROM attendance_shift_schedules 
                    WHERE shift_id = ? ORDER BY day_of_week
                ");
                $stmt_sched->execute([$shift['id']]);
                $shift['schedules'] = $stmt_sched->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode(['status' => true, 'data' => $shifts]);
            break;
            
        case 'create':
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $schedules = $input['schedules'] ?? [];
            
            if (empty($name)) {
                throw new Exception('Shift name is required.');
            }
            
            $pdo->beginTransaction();
            
            // Insert shift
            $stmt = $pdo->prepare("INSERT INTO attendance_shifts (company_id, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$company_id, $name, $description]);
            $shift_id = $pdo->lastInsertId();
            
            // Insert schedules (7 days)
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
            
            $pdo->commit();
            echo json_encode(['status' => true, 'message' => 'Shift created successfully.', 'shift_id' => $shift_id]);
            break;
            
        case 'update':
            $shift_id = intval($input['shift_id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $schedules = $input['schedules'] ?? [];
            
            if (!$shift_id || empty($name)) {
                throw new Exception('Shift ID and name are required.');
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM attendance_shifts WHERE id = ? AND company_id = ?");
            $stmt->execute([$shift_id, $company_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Shift not found.');
            }
            
            $pdo->beginTransaction();
            
            // Update shift
            $stmt = $pdo->prepare("UPDATE attendance_shifts SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $description, $shift_id]);
            
            // Update schedules
            $stmt_sched = $pdo->prepare("
                UPDATE attendance_shift_schedules 
                SET is_working_day = ?, check_in_time = ?, check_out_time = ?, grace_period_minutes = ?
                WHERE shift_id = ? AND day_of_week = ?
            ");
            
            $day_names = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
            for ($day = 0; $day <= 6; $day++) {
                $day_key = $day_names[$day];
                $is_working = isset($schedules[$day_key]['enabled']) ? (int)$schedules[$day_key]['enabled'] : 0;
                $check_in = $schedules[$day_key]['check_in'] ?? null;
                $check_out = $schedules[$day_key]['check_out'] ?? null;
                $grace = intval($schedules[$day_key]['grace'] ?? 15);
                
                $stmt_sched->execute([$is_working, $check_in, $check_out, $grace, $shift_id, $day]);
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
            
            // Get schedules
            $stmt_sched = $pdo->prepare("SELECT * FROM attendance_shift_schedules WHERE shift_id = ? ORDER BY day_of_week");
            $stmt_sched->execute([$shift_id]);
            $shift['schedules'] = $stmt_sched->fetchAll(PDO::FETCH_ASSOC);
            
            // Get assigned employees
            $stmt_emp = $pdo->prepare("
                SELECT e.id, e.first_name, e.last_name, e.payroll_id, d.name as department
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
            // Get employees not assigned to any shift (for shift mode assignment)
            $stmt = $pdo->prepare("
                SELECT e.id, e.first_name, e.last_name, e.payroll_id, d.name as department
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.company_id = ? AND e.status = 'active'
                AND e.id NOT IN (
                    SELECT employee_id FROM employee_attendance_assignments 
                    WHERE is_active = 1 AND attendance_mode = 'shift'
                )
                ORDER BY e.first_name
            ");
            $stmt->execute([$company_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => true, 'data' => $employees]);
            break;
            
        case 'assign_employees':
            $shift_id = intval($input['shift_id'] ?? 0);
            $employee_ids = $input['employee_ids'] ?? [];
            
            if (!$shift_id) {
                throw new Exception('Shift ID is required.');
            }
            
            $pdo->beginTransaction();
            
            // Remove current assignments for this shift
            $stmt = $pdo->prepare("UPDATE employee_attendance_assignments SET is_active = 0 WHERE shift_id = ?");
            $stmt->execute([$shift_id]);
            
            // Add new assignments
            $stmt_ins = $pdo->prepare("
                INSERT INTO employee_attendance_assignments (employee_id, attendance_mode, shift_id, effective_from, is_active)
                VALUES (?, 'shift', ?, CURDATE(), 1)
                ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), attendance_mode = 'shift', is_active = 1
            ");
            
            foreach ($employee_ids as $emp_id) {
                // First deactivate any existing assignment for this employee
                $stmt_deact = $pdo->prepare("UPDATE employee_attendance_assignments SET is_active = 0 WHERE employee_id = ?");
                $stmt_deact->execute([$emp_id]);
                
                // Then create new assignment
                $stmt_ins->execute([$emp_id, $shift_id]);
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
