<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
    exit;
}

require_login();

// Permission Check (Only Admin/HR)
// Permission Check (Only Admin/HR)
if (!in_array($_SESSION['role'], ['super_admin', 'company_admin', 'hr_manager'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
$username = clean_input($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$employee_id || empty($username) || empty($password)) {
    echo json_encode(['status' => false, 'message' => 'All fields are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch Employee Details
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ?");
    $stmt->execute([$employee_id, $_SESSION['company_id']]);
    $employee = $stmt->fetch();

    if (!$employee) {
        throw new Exception("Employee not found.");
    }

    if ($employee['user_id']) {
        throw new Exception("This employee already has a linked user account.");
    }

    // 2. Check if Username/Email exists
    // We use the employee's existing email for the user account if available, else null?
    // Actually, distinct users table. 
    // If employee has an email in employees table, we should use it?
    // Or we rely on username.
    // Let's use employee email if valid, else fallback? 
    // The prompt implies we log in with User Name OR Email. 
    // So we should try to sync the email.
    
    $email_to_use = $employee['email'];
    if (empty($email_to_use)) {
        // If no email, generates a dummy one or requires it?
        // Let's require unique username. Email can be null? 
        // Schema for users.email is NOT NULL usually.
        // Let's check schema... users.email is likely NOT NULL.
        // If no email, we might need a dummy: username@company.local
        $email_to_use = strtolower($username) . '@' . $_SESSION['company_id'] . '.local';
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email_to_use]);
    if ($stmt->fetch()) {
        throw new Exception("Username or Email already exists.");
    }

    // 3. Create User
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (company_id, first_name, last_name, email, username, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'employee', 'active', NOW())");
    $stmt->execute([
        $_SESSION['company_id'],
        $employee['first_name'],
        $employee['last_name'],
        $email_to_use,
        $username,
        $password_hash
    ]);
    $new_user_id = $pdo->lastInsertId();

    // 4. Link to Employee
    $stmt = $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?");
    $stmt->execute([$new_user_id, $employee_id]);

    $pdo->commit();
    
    // Log Audit
    log_audit($_SESSION['company_id'], $_SESSION['user_id'], 'CREATE_USER', "Created user account for Employee #$employee_id ($username)");

    echo json_encode(['status' => true, 'message' => 'User account created successfully.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
