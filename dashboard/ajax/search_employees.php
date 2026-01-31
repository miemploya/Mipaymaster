<?php
/**
 * AJAX: Search Employees
 * Returns employees matching search query for autocomplete
 */
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'];
$input = json_decode(file_get_contents('php://input'), true);

$query = trim($input['query'] ?? '');

if (strlen($query) < 1) {
    echo json_encode(['status' => true, 'employees' => []]);
    exit;
}

try {
    $search = '%' . $query . '%';
    
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.payroll_id,
               d.name as department_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ? 
        AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')
        AND (
            e.first_name LIKE ? 
            OR e.last_name LIKE ? 
            OR e.payroll_id LIKE ?
            OR CONCAT(e.first_name, ' ', e.last_name) LIKE ?
        )
        ORDER BY e.first_name, e.last_name
        LIMIT 10
    ");
    $stmt->execute([$company_id, $search, $search, $search, $search]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = array_map(function($emp) {
        return [
            'id' => $emp['id'],
            'name' => $emp['first_name'] . ' ' . $emp['last_name'],
            'payroll_id' => $emp['payroll_id'],
            'department' => $emp['department_name'] ?? 'No Dept'
        ];
    }, $employees);
    
    echo json_encode(['status' => true, 'employees' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
