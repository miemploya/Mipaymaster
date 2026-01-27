<?php
// Check existing employee users and their credentials
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "ERROR: Not logged in. Please login first.\n";
    exit;
}

$company_id = $_SESSION['company_id'];

echo "=== EMPLOYEE USERS DEBUG ===\n\n";

// Get all users with role='employee'
$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.role, u.company_id
                       FROM users u 
                       WHERE u.company_id = ? AND u.role = 'employee'");
$stmt->execute([$company_id]);
$employee_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users with role='employee' in company $company_id:\n";
if (empty($employee_users)) {
    echo "  NONE FOUND!\n\n";
} else {
    foreach ($employee_users as $u) {
        echo "  - ID: {$u['id']} | {$u['first_name']} {$u['last_name']} | Email: {$u['email']} | Username: " . ($u['username'] ?? 'NULL') . "\n";
    }
    echo "\n";
}

// Get all employees and their linked user accounts
echo "Employees and their linked user accounts:\n";
$stmt = $pdo->prepare("
    SELECT e.id as emp_id, e.first_name, e.last_name, e.payroll_id, e.user_id,
           u.email as user_email, u.username, u.role as user_role
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.company_id = ?
");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($employees as $emp) {
    echo "  - Emp ID: {$emp['emp_id']} | {$emp['first_name']} {$emp['last_name']} | Payroll ID: {$emp['payroll_id']}\n";
    if ($emp['user_id']) {
        echo "    -> Linked to User ID: {$emp['user_id']} | Email: {$emp['user_email']} | Username: " . ($emp['username'] ?? 'NULL') . " | Role: {$emp['user_role']}\n";
    } else {
        echo "    -> NO USER ACCOUNT LINKED\n";
    }
}

echo "\n=== RECOMMENDATION ===\n";
if (empty($employee_users)) {
    echo "No users with role='employee' exist.\n";
    echo "The linked users have roles like 'super_admin' or others.\n";
    echo "To fix: UPDATE users SET role = 'employee' WHERE id IN (4,5,6,7,8)\n";
} else {
    echo "Use one of the employee accounts above to test the Staff Portal.\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
