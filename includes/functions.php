<?php
require_once dirname(__DIR__) . '/config/db.php';

// Start Session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitize Input
 */
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Redirect Helper
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Check if user is logged in
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        redirect('/Mipaymaster/auth/login.php');
    }
    // Robustness: Restore company_id if missing but user_id exists
    if (!isset($_SESSION['company_id']) || !$_SESSION['company_id']) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT company_id, role, first_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'];
        }
    }
}

/**
 * Flash Message Helper
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success' or 'error'
        'text' => $message
    ];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $class = $msg['type'] === 'success' ? 'alert-success' : 'alert-error';
        echo "<div class='alert {$class}'>{$msg['text']}</div>";
        unset($_SESSION['flash_message']);
    }
}

/**
 * Create Company & User Transaction
 * Registers a new company and the first user (Admin)
 */
function register_company_and_user($company_name, $email, $password, $first_name, $last_name) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();

        // 1. Create Company
        $stmt = $pdo->prepare("INSERT INTO companies (name, email) VALUES (?, ?)");
        $stmt->execute([$company_name, $email]);
        $company_id = $pdo->lastInsertId();

        // 2. Create User
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (company_id, first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?, 'super_admin')");
        $stmt->execute([$company_id, $first_name, $last_name, $email, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // 3. Create Default Salary Categories
        $categories = ['Junior Staff', 'Senior Staff', 'Management'];
        $stmt = $pdo->prepare("INSERT INTO salary_categories (company_id, name) VALUES (?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute([$company_id, $cat]);
        }
        
        // 4. Create Default Statutory Settings
        $stmt = $pdo->prepare("INSERT INTO statutory_settings (company_id) VALUES (?)");
        $stmt->execute([$company_id]);

        $pdo->commit();
        return ['status' => true, 'user_id' => $user_id, 'role' => 'super_admin', 'company_id' => $company_id];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => false, 'message' => $e->getMessage()];
    }
}
?>
