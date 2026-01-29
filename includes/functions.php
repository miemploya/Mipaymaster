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
        
        // 5. Initialize Employee ID Settings
        // Generate default prefix from company name
        $words = explode(' ', $company_name);
        if (count($words) >= 2) {
            $prefix = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . (isset($words[2]) ? substr($words[2], 0, 1) : 'P'));
        } else {
            $prefix = strtoupper(substr($company_name, 0, 3));
        }
        $stmt = $pdo->prepare("INSERT INTO employee_id_settings (company_id, prefix, include_year, id_separator, number_padding, next_number, is_locked) VALUES (?, ?, 1, '-', 3, 1, 0)");
        $stmt->execute([$company_id, $prefix]);

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

/**
 * Get Employee ID Settings for a company
 */
function get_employee_id_settings($company_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM employee_id_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return defaults if not configured
    if (!$settings) {
        return [
            'prefix' => 'EMP',
            'include_year' => 1,
            'id_separator' => '-',
            'number_padding' => 3,
            'next_number' => 1,
            'is_locked' => 0
        ];
    }
    
    return $settings;
}

/**
 * Generate next Employee ID for a company
 * @param int $company_id
 * @param bool $increment - If true, increments the counter after generating
 * @return string - The generated Employee ID
 */
function generate_employee_id($company_id, $increment = true) {
    global $pdo;
    
    $settings = get_employee_id_settings($company_id);
    
    $prefix = $settings['prefix'];
    $separator = $settings['id_separator'];
    $include_year = $settings['include_year'];
    $padding = $settings['number_padding'];
    $next_number = $settings['next_number'];
    
    // Build the ID
    $parts = [$prefix];
    
    if ($include_year) {
        $parts[] = date('Y');
    }
    
    $parts[] = str_pad($next_number, $padding, '0', STR_PAD_LEFT);
    
    $employee_id = implode($separator, $parts);
    
    // Increment counter if requested
    if ($increment) {
        $stmt = $pdo->prepare("UPDATE employee_id_settings SET next_number = next_number + 1 WHERE company_id = ?");
        $stmt->execute([$company_id]);
    }
    
    return $employee_id;
}

/**
 * Get preview of next Employee IDs (for UI display)
 * @param int $company_id
 * @param int $count - Number of preview IDs to generate
 * @return array - Array of preview IDs
 */
function get_employee_id_preview($company_id, $count = 3) {
    global $pdo;
    
    $settings = get_employee_id_settings($company_id);
    
    $prefix = $settings['prefix'];
    $separator = $settings['id_separator'];
    $include_year = $settings['include_year'];
    $padding = $settings['number_padding'];
    $next_number = $settings['next_number'];
    
    $previews = [];
    
    for ($i = 0; $i < $count; $i++) {
        $parts = [$prefix];
        
        if ($include_year) {
            $parts[] = date('Y');
        }
        
        $parts[] = str_pad($next_number + $i, $padding, '0', STR_PAD_LEFT);
        
        $previews[] = implode($separator, $parts);
    }
    
    return $previews;
}

/**
 * Update Employee ID Settings for a company
 */
function update_employee_id_settings($company_id, $prefix, $include_year, $separator, $padding, $lock = false) {
    global $pdo;
    
    // Check if settings exist
    $stmt = $pdo->prepare("SELECT id FROM employee_id_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        $stmt = $pdo->prepare("
            UPDATE employee_id_settings 
            SET prefix = ?, include_year = ?, id_separator = ?, number_padding = ?, is_locked = ?
            WHERE company_id = ?
        ");
        $stmt->execute([$prefix, $include_year ? 1 : 0, $separator, $padding, $lock ? 1 : 0, $company_id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO employee_id_settings (company_id, prefix, include_year, id_separator, number_padding, is_locked)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$company_id, $prefix, $include_year ? 1 : 0, $separator, $padding, $lock ? 1 : 0]);
    }
    
    return true;
}

/**
 * Initialize Employee ID Settings for a new company
 * Called during company registration
 */
function init_employee_id_settings($company_id, $company_name) {
    global $pdo;
    
    // Generate default prefix from company name
    $words = explode(' ', $company_name);
    if (count($words) >= 2) {
        $prefix = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . (isset($words[2]) ? substr($words[2], 0, 1) : 'P'));
    } else {
        $prefix = strtoupper(substr($company_name, 0, 3));
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO employee_id_settings (company_id, prefix, include_year, id_separator, number_padding, next_number, is_locked)
        VALUES (?, ?, 1, '-', 3, 1, 0)
    ");
    $stmt->execute([$company_id, $prefix]);
    
    return $prefix;
}
?>
