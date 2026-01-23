<?php
require_once '../../includes/functions.php';
require_once '../../includes/increment_manager.php';
require_login();

header('Content-Type: application/json');

if (!isset($_GET['employee_id'])) {
    echo json_encode(['error' => 'Missing Employee ID']);
    exit;
}

$employee_id = intval($_GET['employee_id']);
$company_id = $_SESSION['company_id'];

// Verify employee belongs to company
$stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND company_id = ?");
$stmt->execute([$employee_id, $company_id]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Invalid Employee']);
    exit;
}

// Fetch Increments
$stmt = $pdo->prepare("
    SELECT adj.*, u.email as approved_by_email 
    FROM employee_salary_adjustments adj
    LEFT JOIN users u ON adj.approved_by = u.id
    WHERE adj.employee_id = ?
    ORDER BY adj.effective_from DESC
");
$stmt->execute([$employee_id]);
$increments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format for JS
$data = array_map(function($inc) {
    return [
        'id' => $inc['id'],
        'type' => ucfirst($inc['adjustment_type']),
        'value' => $inc['adjustment_value'],
        'effective_date' => date('M d, Y', strtotime($inc['effective_from'])),
        'status' => ucfirst($inc['approval_status']),
        'reason' => $inc['reason'] ?? '-',
        'approved_by' => $inc['approved_by_email'] ?? '-'
    ];
}, $increments);

echo json_encode(['increments' => $data]);
?>
