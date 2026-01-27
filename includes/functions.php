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
 * Check if user is logged in (Boolean)
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}


/**
 * Check if user is logged in (Redirect if not)
 */
function require_login() {
    // Check if user_id exists in session
    if (!isset($_SESSION['user_id'])) {
        redirect('/Mipaymaster/auth/login.php');
    }
    
    // CRITICAL: Validate session integrity - if ANY core session variable is missing, force re-login
    // This should NEVER happen in normal operation, but protects against session corruption
    if (empty($_SESSION['company_id']) || empty($_SESSION['role'])) {
        // Session is corrupted or incomplete - force logout and re-login
        session_destroy();
        redirect('/Mipaymaster/auth/login.php');
    }
    
    // Session is valid and complete - no hydration needed
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
/**
 * Log an audit trail event
 */
function log_audit($company_id, $user_id, $action, $details = null) {
    global $pdo;
    
    // Get IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (company_id, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$company_id, $user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        // Fail silently or log to file? Audit failure shouldn't crash app flow generally.
        error_log("Audit Log Failed: " . $e->getMessage());
    }
}
?>
