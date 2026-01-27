<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'] ?? 0;
$success_msg = '';
$error_msg = '';

// Session Flash Messages (PRG Pattern)
if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (isset($_GET['error'])) {
    $error_msg = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_msg = htmlspecialchars($_GET['success']);
}

// --- ENSURE DATABASE TABLES EXIST (Auto-Migration) ---
// [LEGACY DDL DEPRECATED - V2 SCHEMA IS LOCKED]
/*
try {
    // Salary Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        base_gross_amount DECIMAL(15,2) DEFAULT 0.00,
        basic_perc DECIMAL(5,2) DEFAULT 40.00,
        housing_perc DECIMAL(5,2) DEFAULT 30.00,
        transport_perc DECIMAL(5,2) DEFAULT 20.00,
        other_perc DECIMAL(5,2) DEFAULT 10.00,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (company_id)
    )");
    
    // Check for 'is_active' vs 'active' column standardisation
    $cols = $pdo->query("SHOW COLUMNS FROM salary_categories LIKE 'is_active'")->fetchAll();
    if (count($cols) == 0) {
        // Check if 'active' exists
        $cols_legacy = $pdo->query("SHOW COLUMNS FROM salary_categories LIKE 'active'")->fetchAll();
        if (count($cols_legacy) > 0) {
            $pdo->exec("ALTER TABLE salary_categories CHANGE COLUMN active is_active TINYINT(1) DEFAULT 1");
        } else {
            $pdo->exec("ALTER TABLE salary_categories ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }
    }

    // Salary Components Table (NEW - Single Source of Truth)
    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_components (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        type ENUM('basic', 'system', 'allowance') DEFAULT 'allowance',
        calculation_method VARCHAR(50) DEFAULT 'fixed',
        default_percentage DECIMAL(5,2) DEFAULT 0.00,
        amount DECIMAL(15,2) DEFAULT 0.00,
        percentage DECIMAL(5,2) DEFAULT 0.00,
        base_component_id INT DEFAULT NULL,
        percentage_base VARCHAR(50) DEFAULT 'basic',
        is_taxable TINYINT(1) DEFAULT 1,
        is_pensionable TINYINT(1) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        is_custom TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (company_id),
        UNIQUE KEY unique_name_company (company_id, name)
    )");
    
    // Check for is_custom in salary_components
    $cols = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'is_custom'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE salary_components ADD COLUMN is_custom TINYINT(1) DEFAULT 0 AFTER is_active");
    
    // Seed System Components if not exist
    $check_sys = $pdo->prepare("SELECT COUNT(*) FROM salary_components WHERE company_id = ? AND type IN ('basic', 'system')");
    $check_sys->execute([$company_id]);
    if ($check_sys->fetchColumn() == 0) {
        // Basic
        $pdo->prepare("INSERT INTO salary_components (company_id, name, type, calculation_method, percentage_base, default_percentage, is_taxable, is_pensionable, is_active) VALUES (?, 'Basic Salary', 'basic', 'percentage', 'gross', 50.00, 1, 1, 1)")->execute([$company_id]);
        // Housing
        $pdo->prepare("INSERT INTO salary_components (company_id, name, type, calculation_method, percentage_base, default_percentage, is_taxable, is_pensionable, is_active) VALUES (?, 'Housing Allowance', 'system', 'percentage', 'gross', 30.00, 1, 1, 1)")->execute([$company_id]);
        // Transport
        $pdo->prepare("INSERT INTO salary_components (company_id, name, type, calculation_method, percentage_base, default_percentage, is_taxable, is_pensionable, is_active) VALUES (?, 'Transport Allowance', 'system', 'percentage', 'gross', 20.00, 1, 1, 1)")->execute([$company_id]);
        // Other (Optional system default or just leave for custom)
    }
    
    // PATCH: Enforce User Requested Defaults for Existing Records (If 0)
    // PATCH: Enforce User Requested Defaults for Existing Records (If 0)
    $pdo->prepare("UPDATE salary_components SET default_percentage = 50.00, calculation_method = 'percentage', percentage_base = 'gross' WHERE company_id = ? AND name = 'Basic Salary'")->execute([$company_id]);
    $pdo->prepare("UPDATE salary_components SET default_percentage = 30.00, calculation_method = 'percentage', percentage_base = 'gross' WHERE company_id = ? AND name = 'Housing Allowance'")->execute([$company_id]);
    $pdo->prepare("UPDATE salary_components SET default_percentage = 20.00, calculation_method = 'percentage', percentage_base = 'gross' WHERE company_id = ? AND name = 'Transport Allowance'")->execute([$company_id]);

    // Statutory Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS statutory_settings (
        company_id INT PRIMARY KEY,
        enable_paye TINYINT(1) DEFAULT 1,
        enable_pension TINYINT(1) DEFAULT 1,
        pension_employer_perc DECIMAL(5,2) DEFAULT 10.00,
        pension_employee_perc DECIMAL(5,2) DEFAULT 8.00,
        enable_nhis TINYINT(1) DEFAULT 0,
        enable_nhf TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Payroll Behaviours Table (NEW)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_behaviours (
        company_id INT PRIMARY KEY,
        prorate_new_hires TINYINT(1) DEFAULT 1,
        email_payslips TINYINT(1) DEFAULT 0,
        password_protect_payslips TINYINT(1) DEFAULT 0,
        enable_overtime TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Departments Table (NEW - AI UX PROMPT)
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (company_id)
    )");

    // Ensure employees have department_id
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'department_id'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN department_id INT DEFAULT NULL AFTER department");

    // Payroll Items Table (NEW - BONUS & DEDUCTIONS)
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        type ENUM('bonus', 'deduction') NOT NULL,
        calculation_method ENUM('fixed', 'percentage') DEFAULT 'fixed',
        amount DECIMAL(15,2) DEFAULT 0.00,
        percentage DECIMAL(5,2) DEFAULT 0.00,
        percentage_base VARCHAR(50) DEFAULT 'basic',
        is_taxable TINYINT(1) DEFAULT 1,
        is_pensionable TINYINT(1) DEFAULT 1,
        is_recurring TINYINT(1) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        is_custom TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (company_id)
    )");
    
    // Check for is_custom column update
    $cols = $pdo->query("SHOW COLUMNS FROM payroll_items LIKE 'is_custom'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE payroll_items ADD COLUMN is_custom TINYINT(1) DEFAULT 0 AFTER is_active");

    // Check for logo_url column in companies
    $cols = $pdo->query("SHOW COLUMNS FROM companies LIKE 'logo_url'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE companies ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL AFTER email");
    
    // Check for calculation columns in salary_components
    $cols = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'calculation_method'")->fetchAll();
    if (count($cols) == 0) {
        // methods: 'fixed', 'percentage_gross', 'percentage_basic', 'percentage_component'
        $pdo->exec("ALTER TABLE salary_components 
            ADD COLUMN calculation_method VARCHAR(50) DEFAULT 'fixed' AFTER type,
            ADD COLUMN percentage DECIMAL(5,2) DEFAULT 0 AFTER amount,
            ADD COLUMN base_component_id INT DEFAULT NULL AFTER percentage
        ");
    }

} catch (PDOException $e) {
    // Siently fail or log, but usually better to proceed if possible
    error_log("DB Migration Error: " . $e->getMessage());
}
*/

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? '';
    
    // Support JSON requests (e.g. from saveAllComponents)
    $json_input = null;
    if (empty($tab) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $json_input = json_decode(file_get_contents('php://input'), true);
        $tab = $json_input['tab'] ?? '';
    }

    // 1. UPDATE PROFILE
    if ($tab === 'profile') {
        $name = clean_input($_POST['name']);
        $email = clean_input($_POST['email']);
        $phone = clean_input($_POST['phone']);
        $address = clean_input($_POST['address']);
        $currency = clean_input($_POST['currency']);
        $tax_jurisdiction = clean_input($_POST['tax_jurisdiction']);

        if (empty($name) || empty($email)) {
             $error_msg = "Company Name and Email are required.";
        } else {
            try {
                // Handle Logo Upload
                $logo_sql = "";
                $params = [$name, $email, $phone, $address, $currency, $tax_jurisdiction];
                
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $allowTypes = ['jpg', 'png', 'jpeg', 'gif'];
                    $fileName = basename($_FILES['logo']['name']);
                    $targetDir = "../uploads/logos/";
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    
                    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($fileType, $allowTypes)) {
                        $newFileName = 'logo_' . $company_id . '_' . time() . '.' . $fileType;
                        $targetFilePath = $targetDir . $newFileName;
                        
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFilePath)) {
                            $logo_sql = ", logo_url=?";
                            $params[] = $newFileName;
                        }
                    }
                }

                $params[] = $company_id;
                $stmt = $pdo->prepare("UPDATE companies SET name=?, email=?, phone=?, address=?, currency=?, tax_jurisdiction=? $logo_sql WHERE id=?");
                $stmt->execute($params);
                
                $_SESSION['flash_success'] = "Company profile updated.";
                log_audit($company_id, $_SESSION['user_id'], 'UPDATE_COMPANY', "Updated company profile: $name");
                header("Location: company.php?tab=profile");
                exit;
            } catch (PDOException $e) { $error_msg = "Error: " . $e->getMessage(); }
        }
    }
    
    // 2. DEPARTMENTS (NEW)
    elseif ($tab === 'department_save') {
        $dept_id = $_POST['dept_id'] ?? null;
        $name = clean_input($_POST['dept_name']);
        $code = clean_input($_POST['dept_code']); 
        $active = isset($_POST['dept_active']) ? 1 : 0;

        if (empty($name)) {
            $error_msg = "Department name is required.";
        } else {
            try {
                if ($dept_id) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE departments SET name=?, code=?, is_active=? WHERE id=? AND company_id=?");
                    $stmt->execute([$name, $code, $active, $dept_id, $company_id]);
                    $_SESSION['flash_success'] = "Department updated.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO departments (company_id, name, code, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$company_id, $name, $code, $active]);
                    $_SESSION['flash_success'] = "Department created.";
                    log_audit($company_id, $_SESSION['user_id'], 'CREATE_DEPARTMENT', "Created department: $name");
                }
                if ($dept_id) log_audit($company_id, $_SESSION['user_id'], 'UPDATE_DEPARTMENT', "Updated department: $name");
                header("Location: company.php?tab=departments");
                exit;
            } catch (PDOException $e) { $error_msg = "Error saving department: " . $e->getMessage(); }
        }
    }
    elseif ($tab === 'department_delete') {
        $dept_id = $_POST['dept_id'];
        try {
            // Check for employees
            $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND company_id = ?");
            $chk->execute([$dept_id, $company_id]);
            if ($chk->fetchColumn() > 0) {
                $error_msg = "Cannot delete department with assigned employees.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id=? AND company_id=?");
                $stmt->execute([$dept_id, $company_id]);
                $_SESSION['flash_success'] = "Department deleted.";
                log_audit($company_id, $_SESSION['user_id'], 'DELETE_DEPARTMENT', "Deleted department ID: $dept_id");
                header("Location: company.php?tab=departments");
                exit;
            }
        } catch (PDOException $e) { $error_msg = "Error deleting department: " . $e->getMessage(); }
    }
    elseif ($tab === 'department_toggle') {
        $dept_id = $_POST['dept_id'];
        // Just toggle active status
        try {
             $stmt = $pdo->prepare("UPDATE departments SET is_active = NOT is_active WHERE id=? AND company_id=?");
             $stmt->execute([$dept_id, $company_id]);
             $_SESSION['flash_success'] = "Department status updated.";
             header("Location: company.php?tab=departments");
             exit;
        } catch (PDOException $e) { $error_msg = "Error updating status: " . $e->getMessage(); }
    }
    
    // 3. PAYROLL ITEMS (NEW)
    elseif ($tab === 'item_save') {
        $item_id = $_POST['item_id'] ?? null;
        $name = clean_input($_POST['item_name']);
        $type = $_POST['item_type'] ?? 'bonus'; // 'bonus' or 'deduction'
        $method = $_POST['item_method'] ?? 'fixed';
        
        $amount = floatval($_POST['item_amount'] ?? 0);
        $percentage = floatval($_POST['item_percentage'] ?? 0);
        
        $base = $_POST['item_base'] ?? 'gross';
        $active = isset($_POST['item_active']) ? 1 : 0;
        $taxable = isset($_POST['item_taxable']) ? 1 : 0;
        $pensionable = isset($_POST['item_pensionable']) ? 1 : 0;
        
        $table = ($type === 'deduction') ? 'payroll_deduction_types' : 'payroll_bonus_types';

        if (empty($name)) {
             $error_msg = "Item name is required.";
        } else {
             try {
                if ($item_id) {
                    $stmt = $pdo->prepare("UPDATE $table SET name=?, calculation_mode=?, amount=?, percentage=?, percentage_base=?, is_taxable=?, is_pensionable=?, is_active=? WHERE id=? AND company_id=?");
                    $stmt->execute([$name, $method, $amount, $percentage, $base, $taxable, $pensionable, $active, $item_id, $company_id]);
                    $_SESSION['flash_success'] = "Item updated.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO $table (company_id, name, calculation_mode, amount, percentage, percentage_base, is_taxable, is_pensionable, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$company_id, $name, $method, $amount, $percentage, $base, $taxable, $pensionable, $active]);
                    $_SESSION['flash_success'] = "Item created.";
                    log_audit($company_id, $_SESSION['user_id'], 'CREATE_PAYROLL_ITEM', "Created payroll item: $name");
                }
                if ($item_id) log_audit($company_id, $_SESSION['user_id'], 'UPDATE_PAYROLL_ITEM', "Updated payroll item: $name");
                header("Location: company.php?tab=items&item_tab=" . $type); 
                exit;
             } catch (PDOException $e) { $error_msg = "Error saving item: " . $e->getMessage(); }
        }
    }

    elseif ($tab === 'item_delete') {
         $id = $_POST['item_id'];
         $type = $_POST['item_type'] ?? 'bonus';
         $table = ($type === 'deduction') ? 'payroll_deduction_types' : 'payroll_bonus_types';
         
         try {
             $stmt = $pdo->prepare("DELETE FROM $table WHERE id=? AND company_id=?");
             $stmt->execute([$id, $company_id]);
             $_SESSION['flash_success'] = "Item deleted.";
             log_audit($company_id, $_SESSION['user_id'], 'DELETE_PAYROLL_ITEM', "Deleted payroll item ID: $id");
             header("Location: company.php?tab=items&item_tab=" . $type);
             exit;
         } catch (PDOException $e) { $error_msg = "Error deleting item: " . $e->getMessage(); }
    }
    elseif ($tab === 'item_toggle') {
         $id = $_POST['item_id'];
         // Need to find which table. Since ID is ambiguous, we ideally need type. 
         // Assuming type is passed or we try both? The UI sends item_id only previously.
         // We should update UI to send type, but for now let's try both or check via SELECT.
         // Actually, let's assume we can pass 'item_type' hidden input from UI toggle form?
         // Looking at toggleItem JS: it sends item_id only. We need to fix JS too.
         // For now, let's try Bonus first, then Deduction.
         
         try {
             $updated = false;
             $stmt = $pdo->prepare("UPDATE payroll_bonus_types SET is_active = NOT is_active WHERE id=? AND company_id=?");
             $stmt->execute([$id, $company_id]);
             if ($stmt->rowCount() > 0) $updated = true;
             
             if (!$updated) {
                 $stmt = $pdo->prepare("UPDATE payroll_deduction_types SET is_active = NOT is_active WHERE id=? AND company_id=?");
                 $stmt->execute([$id, $company_id]);
             }
             
             $_SESSION['flash_success'] = "Item status updated.";
             header("Location: company.php?tab=items");
             exit;
         } catch (PDOException $e) { $error_msg = "Error updating status: " . $e->getMessage(); }
    }

    // 4. SALARY COMPONENTS (NEW) - JSON ENDPOINT (FIX 5)
    elseif ($tab === 'component_save_json') {
        header('Content-Type: application/json');
        
        $comp_id = $_POST['comp_id'] ?? null;
        $name = clean_input($_POST['comp_name']);
        $method = $_POST['comp_method'] ?? 'fixed';
        $amount = floatval($_POST['comp_amount'] ?? 0);
        $percentage = floatval($_POST['comp_perc'] ?? 0);
        $base = $_POST['comp_base'] ?? 'gross';
        
        $perc_base = 'gross'; // FIX 4: Force gross, no recalculation
        $base_comp_id = null;
        if (is_numeric($base)) {
            $base_comp_id = (int)$base;
            $perc_base = 'component';
        }
        
        $is_taxable = isset($_POST['comp_taxable']) ? 1 : 0;
        $is_pensionable = isset($_POST['comp_pensionable']) ? 1 : 0;
        $is_active = isset($_POST['comp_active']) ? 1 : 0;
        $is_custom = isset($_POST['comp_custom']) ? 1 : 0;

        try {
            if ($comp_id) {
                // Update
                $stmt = $pdo->prepare("UPDATE salary_components SET 
                    calculation_method=?, amount=?, percentage=?, percentage_base=?, base_component_id=?, 
                    is_taxable=?, is_pensionable=?, is_active=? 
                    WHERE id=? AND company_id=?");
                $stmt->execute([$method, $amount, $percentage, $perc_base, $base_comp_id, $is_taxable, $is_pensionable, $is_active, $comp_id, $company_id]);
                
                // Fetch updated component
                $stmt = $pdo->prepare("SELECT * FROM salary_components WHERE id=? AND company_id=?");
                $stmt->execute([$comp_id, $company_id]);
                $c = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Insert (New Component) - Logic ported from component_save
                $chk = $pdo->prepare("SELECT id FROM salary_components WHERE company_id=? AND name=?");
                $chk->execute([$company_id, $name]);
                if($chk->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Component with this name already exists.']);
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO salary_components (company_id, name, type, calculation_method, amount, percentage, percentage_base, base_component_id, is_taxable, is_pensionable, is_active, is_custom) VALUES (?, ?, 'allowance', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_id, $name, $method, $amount, $percentage, $perc_base, $base_comp_id, $is_taxable, $is_pensionable, $is_active, $is_custom]);
                $new_id = $pdo->lastInsertId();
                
                // Fetch newly created component
                $stmt = $pdo->prepare("SELECT * FROM salary_components WHERE id=? AND company_id=?");
                $stmt->execute([$new_id, $company_id]);
                $c = $stmt->fetch(PDO::FETCH_ASSOC);
            }
                
            if ($c) {
                // Return component in same format as components_json
                $component = [
                    'id' => (int)$c['id'],
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'method' => $c['calculation_method'] ?? 'fixed',
                    'amount' => floatval($c['amount'] ?? 0),
                    'percentage' => floatval($c['percentage'] ?? 0) != 0 ? floatval($c['percentage']) : floatval($c['default_percentage'] ?? 0),
                    'base' => ($c['percentage_base'] ?? '') == 'component' ? ($c['base_component_id'] ?? null) : ($c['percentage_base'] ?? 'basic'),
                    'default_percentage' => floatval($c['default_percentage']),
                    'taxable' => (bool)$c['is_taxable'],
                    'pensionable' => (bool)$c['is_pensionable'],
                    'active' => (bool)$c['is_active'],
                    'system' => in_array($c['type'], ['basic','system']),
                    'is_overridden' => (floatval($c['percentage'] ?? 0) != 0)
                ];
                
                echo json_encode(['success' => true, 'component' => $component]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Component not found after save']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    // 4. SALARY COMPONENTS (NEW)
    elseif ($tab === 'component_save') {
        $comp_id = $_POST['comp_id'] ?? null;
        $name = clean_input($_POST['comp_name']);
        
        $method = $_POST['comp_method'] ?? 'fixed'; // fixed | percentage
        $amount = floatval($_POST['comp_amount'] ?? 0);
        $percentage = floatval($_POST['comp_perc'] ?? 0);
        $base = $_POST['comp_base'] ?? 'basic'; // basic | gross | {id}
        
        $perc_base = 'gross'; // Force gross base for all components
        // Preserve $base_comp_id logic if numeric base is provided (for future use)
        if (is_numeric($base)) {
            $base_comp_id = (int)$base;
            $perc_base = 'component';
        }
        
        // DEBUG LOGGING
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Component Update: ID=$comp_id, Method=$method, Perc=$percentage, Base=$perc_base, Type=" . ($_POST['comp_type']??'unknown') . "\n", FILE_APPEND);

        $is_taxable = isset($_POST['comp_taxable']) ? 1 : 0;
        $is_pensionable = isset($_POST['comp_pensionable']) ? 1 : 0;
        $is_active = isset($_POST['comp_active']) ? 1 : 0; 
        $is_custom = isset($_POST['comp_custom']) ? 1 : 0;

        try {
            // Lazy Schema Check
            $cols = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'amount'")->fetchAll();
            if (count($cols) == 0) $pdo->exec("ALTER TABLE salary_components ADD COLUMN amount DECIMAL(15,2) DEFAULT 0 AFTER default_percentage");
            
            $cols = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'percentage_base'")->fetchAll();
            if (count($cols) == 0) $pdo->exec("ALTER TABLE salary_components ADD COLUMN percentage_base VARCHAR(50) DEFAULT 'basic' AFTER base_component_id");

            // 7️⃣ BACKEND VALIDATION: Check 100% Cap
            // 7️⃣ BACKEND VALIDATION: Check 100% Cap (DISABLED FOR DEBUGGING/RESET)
            /*
            if ($method === 'percentage' && $perc_base === 'gross') {
                $sql = "SELECT SUM(
                            CASE 
                                WHEN type IN ('basic','system') THEN COALESCE(NULLIF(percentage, 0), default_percentage, 0)
                                WHEN calculation_method='percentage' AND percentage_base='gross' THEN percentage
                                ELSE 0
                            END
                        ) as total_perc 
                        FROM salary_components 
                        WHERE company_id = ? AND is_active = 1";
                
                $params_chk = [$company_id];
                if ($comp_id) {
                    $sql .= " AND id != ?";
                    $params_chk[] = $comp_id;
                }
                
                $chk_stmt = $pdo->prepare($sql);
                $chk_stmt->execute($params_chk);
                $current_total = floatval($chk_stmt->fetchColumn() ?: 0);
                
                // LOG
                file_put_contents('debug_log.txt', " - Struct Check: Current=$current_total, Adding=$percentage\n", FILE_APPEND);

                if (($current_total + $percentage) > 100) {
                     $error_msg = "Validation Error: Total salary structure cannot exceed 100%. Curr: {$current_total}%, New: {$percentage}%.";
                     file_put_contents('debug_log.txt', " - Validation ERROR: Total > 100\n", FILE_APPEND);
                     header("Location: company.php?tab=components&error=" . urlencode($error_msg));
                     exit;
                }
            }
            */

            if ($comp_id) {
                // Update
                $stmt = $pdo->prepare("UPDATE salary_components SET 
                    calculation_method=?, amount=?, percentage=?, percentage_base=?, base_component_id=?, 
                    is_taxable=?, is_pensionable=?, is_active=? 
                    WHERE id=? AND company_id=?");
                $stmt->execute([$method, $amount, $percentage, $perc_base, $base_comp_id, $is_taxable, $is_pensionable, $is_active, $comp_id, $company_id]);
                file_put_contents('debug_log.txt', " - Update Success\n", FILE_APPEND);
                $_SESSION['flash_success'] = "Component updated.";
            } else {
                // Check dup name
                $chk = $pdo->prepare("SELECT id FROM salary_components WHERE company_id=? AND name=?");
                $chk->execute([$company_id, $name]);
                if($chk->fetch()) {
                     $error_msg = "Component with this name already exists.";
                     header("Location: company.php?tab=components&error=" . urlencode($error_msg));
                     exit;
                }

                $stmt = $pdo->prepare("INSERT INTO salary_components (company_id, name, type, calculation_method, amount, percentage, percentage_base, base_component_id, is_taxable, is_pensionable, is_active, is_custom) VALUES (?, ?, 'allowance', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_id, $name, $method, $amount, $percentage, $perc_base, $base_comp_id, $is_taxable, $is_pensionable, $is_active, $is_custom]);
                $_SESSION['flash_success'] = "Component added.";
            }
            header("Location: company.php?tab=components");
            exit;
        } catch (PDOException $e) { $error_msg = "Error savings component: " . $e->getMessage(); }
    }
    
    // NEW: Save Salary Components (Exactly 100% Rule)
    elseif ($tab === 'save_salary_components') {
        header('Content-Type: application/json');
        
        $input = $json_input ?? json_decode(file_get_contents('php://input'), true);
        $components = $input['components'] ?? [];
        
        if (empty($components)) {
            echo json_encode(['success' => false, 'error' => 'No components provided']);
            exit;
        }
        
        try {
            // Validate exactly 100% rule
            $total = 0;
            foreach ($components as $comp) {
                if ($comp['method'] === 'percentage' && $comp['active']) {
                    $total += floatval($comp['percentage'] ?? 0);
                }
            }
            
            // Allow 0.01% tolerance for floating point precision
            if (abs($total - 100.0) > 0.01) {
                echo json_encode([
                    'success' => false, 
                    'error' => "Salary structure must total exactly 100%. Current: " . number_format($total, 2) . "%"
                ]);
                exit;
            }
            
            // Update components
            $stmt = $pdo->prepare("UPDATE salary_components SET 
                percentage=?, amount=?, is_taxable=?, is_pensionable=?, 
                is_active = CASE WHEN type IN ('basic','system') THEN 1 ELSE ? END
                WHERE id=? AND company_id=?");
            
            foreach ($components as $comp) {
                $stmt->execute([
                    floatval($comp['percentage'] ?? 0),
                    floatval($comp['amount'] ?? 0),
                    $comp['taxable'] ? 1 : 0,
                    $comp['pensionable'] ? 1 : 0,
                    $comp['active'] ? 1 : 0,
                    (int)$comp['id'],
                    $company_id
                ]);
            }
            
            // Fetch updated components
            $stmt = $pdo->prepare("SELECT * FROM salary_components WHERE company_id=? ORDER BY CASE WHEN type='basic' THEN 1 WHEN type='system' THEN 2 ELSE 3 END, name ASC");
            $stmt->execute([$company_id]);
            $updated = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for frontend
            $formatted = array_map(function($c) {
                return [
                    'id' => (int)$c['id'],
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'method' => $c['calculation_method'] ?? 'percentage',
                    'amount' => floatval($c['amount'] ?? 0),
                    'percentage' => floatval($c['percentage'] ?? 0),
                    'base' => $c['percentage_base'] ?? 'gross',
                    'taxable' => (bool)$c['is_taxable'],
                    'pensionable' => (bool)$c['is_pensionable'],
                    'active' => (bool)$c['is_active'],
                    'system' => in_array($c['type'], ['basic','system'])
                ];
            }, $updated);
            
            echo json_encode(['success' => true, 'components' => $formatted]);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    elseif ($tab === 'component_delete') {
        $comp_id = $_POST['comp_id'];
        try {
            // Check type first
            $chk = $pdo->prepare("SELECT type FROM salary_components WHERE id=? AND company_id=?");
            $chk->execute([$comp_id, $company_id]);
            $type = $chk->fetchColumn();
            
            if(in_array($type, ['basic','system'])) {
                $error_msg = "System components cannot be deleted.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM salary_components WHERE id=? AND company_id=?");
                $stmt->execute([$comp_id, $company_id]);
                $success_msg = "Component deleted.";
            }
        } catch (PDOException $e) { $error_msg = "Error deleting component: " . $e->getMessage(); }
    }
    
    // 3. UPDATE STATUTORY
    elseif ($tab === 'statutory') {
        $enable_paye = isset($_POST['enable_paye']) ? 1 : 0;
        $enable_pension = isset($_POST['enable_pension']) ? 1 : 0;
        $enable_nhis = isset($_POST['enable_nhis']) ? 1 : 0;
        $enable_nhf = isset($_POST['enable_nhf']) ? 1 : 0;
        $pension_employer = floatval($_POST['pension_employer_perc']);
        $pension_employee = floatval($_POST['pension_employee_perc']);

        try {
            // Check if settings exist first
            $check = $pdo->prepare("SELECT company_id FROM statutory_settings WHERE company_id = ?");
            $check->execute([$company_id]);
            if ($check->rowCount() == 0) {
                 $pdo->prepare("INSERT INTO statutory_settings (company_id) VALUES (?)")->execute([$company_id]);
            }
            
            $stmt = $pdo->prepare("UPDATE statutory_settings SET enable_paye=?, enable_pension=?, enable_nhis=?, enable_nhf=?, pension_employer_perc=?, pension_employee_perc=? WHERE company_id=?");
            $stmt->execute([$enable_paye, $enable_pension, $enable_nhis, $enable_nhf, $pension_employer, $pension_employee, $company_id]);
            $success_msg = "Statutory settings updated.";
        } catch (PDOException $e) { $error_msg = "Error updating settings: " . $e->getMessage(); }
    }

    // 4. UPDATE CATEGORY (Add/Edit)
    elseif ($tab === 'category_save') {
        $id = $_POST['cat_id'] ?? null;
        $name = clean_input($_POST['cat_name']);
        $gross = floatval($_POST['cat_gross']);
        // Structure for this category
        $basic_perc = floatval($_POST['cat_basic']);
        $housing_perc = floatval($_POST['cat_housing']);
        $transport_perc = floatval($_POST['cat_transport']);
        $other_perc = floatval($_POST['cat_other']);

        // Check 100%
        if (abs(($basic_perc + $housing_perc + $transport_perc + $other_perc) - 100) > 0.1) {
             $error_msg = "Total percentage must equal 100%.";
        } else {
            try {
                $pdo->beginTransaction();

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE salary_categories SET name=?, base_gross_amount=? WHERE id=? AND company_id=?");
                    $stmt->execute([$name, $gross, $id, $company_id]);
                    $cat_id = $id;
                    $success_msg = "Category updated.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO salary_categories (company_id, name, base_gross_amount) VALUES (?, ?, ?)");
                    $stmt->execute([$company_id, $name, $gross]);
                    $cat_id = $pdo->lastInsertId();
                    $success_msg = "Category created.";
                }

                // Resolve Component IDs
                $comps = ['Basic Salary' => $basic_perc, 'Housing Allowance' => $housing_perc, 'Transport Allowance' => $transport_perc];
                
                // Clear old breakdown
                $pdo->prepare("DELETE FROM salary_category_breakdown WHERE category_id=?")->execute([$cat_id]);

                // Insert Standard Components
                $get_comp = $pdo->prepare("SELECT id FROM salary_components WHERE company_id=? AND name=?");
                $ins_break = $pdo->prepare("INSERT INTO salary_category_breakdown (category_id, salary_component_id, percentage) VALUES (?, ?, ?)");

                foreach ($comps as $c_name => $c_perc) {
                    if ($c_perc > 0) {
                        $get_comp->execute([$company_id, $c_name]);
                        $c_id = $get_comp->fetchColumn();
                        if ($c_id) {
                            $ins_break->execute([$cat_id, $c_id, $c_perc]);
                        }
                    }
                }

                // Handle "Other"
                if ($other_perc > 0) {
                    // Find or Create "Other Allowance"
                    $get_comp->execute([$company_id, 'Other Allowance']);
                    $other_id = $get_comp->fetchColumn();
                    
                    if (!$other_id) {
                        $pdo->prepare("INSERT INTO salary_components (company_id, name, type, calculation_method, percentage_base, is_taxable, is_pensionable, is_active, is_custom) VALUES (?, 'Other Allowance', 'allowance', 'percentage', 'gross', 1, 1, 1, 1)")->execute([$company_id]);
                        $other_id = $pdo->lastInsertId();
                    }
                    $ins_break->execute([$cat_id, $other_id, $other_perc]);
                }

                $pdo->commit();

                // AUTO-REGENERATE DRAFT PAYROLLS
                // If a category changes, any DRAFT payroll for this company should be recalculated to reflect the new Gross.
                try {
                    $stmt_drafts = $pdo->prepare("SELECT period_month, period_year FROM payroll_runs WHERE company_id=? AND status='draft'");
                    $stmt_drafts->execute([$company_id]);
                    $drafts = $stmt_drafts->fetchAll(PDO::FETCH_ASSOC);

                    if ($drafts) {
                        require_once '../includes/payroll_engine.php';
                        foreach ($drafts as $d) {
                            run_monthly_payroll($company_id, $d['period_month'], $d['period_year'], $_SESSION['user_id']);
                        }
                        $success_msg .= " (Payroll drafts updated)";
                    }
                } catch (Exception $ex) {
                    // Fail silently on regen to avoid scaring user if main save worked
                    error_log("Payroll Regen Error: " . $ex->getMessage());
                }

            } catch (PDOException $e) { 
                $pdo->rollBack();
                $error_msg = "Error saving category: " . $e->getMessage(); 
            }
        }
    }
    
    // 5. DELETE CATEGORY
    elseif ($tab === 'category_delete') {
        $id = $_POST['cat_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM salary_categories WHERE id=? AND company_id=?");
            $stmt->execute([$id, $company_id]);
            $success_msg = "Category deleted.";
        } catch (PDOException $e) { $error_msg = "Error deleting category: " . $e->getMessage(); }
    }
    
    // 6. TOGGLE CATEGORY (NEW)
    elseif ($tab === 'category_toggle') {
        $id = $_POST['cat_id'];
        try {
            $stmt = $pdo->prepare("UPDATE salary_categories SET is_active = NOT is_active WHERE id=? AND company_id=?");
            $stmt->execute([$id, $company_id]);
            $_SESSION['flash_success'] = "Category status updated.";
            header("Location: company.php?tab=categories");
            exit;
        } catch (PDOException $e) { $error_msg = "Error updating status: " . $e->getMessage(); }
    }

    // 6. UPDATE BEHAVIOUR
    elseif ($tab === 'behaviour') {
        $prorate = isset($_POST['prorate_new_hires']) ? 1 : 0;
        $email_payslips = isset($_POST['email_payslips']) ? 1 : 0;
        $password_protect = isset($_POST['password_protect_payslips']) ? 1 : 0;
        $overtime = isset($_POST['enable_overtime']) ? 1 : 0;

        try {
            $check = $pdo->prepare("SELECT company_id FROM payroll_behaviours WHERE company_id = ?");
            $check->execute([$company_id]);
            if ($check->rowCount() == 0) {
                 $pdo->prepare("INSERT INTO payroll_behaviours (company_id) VALUES (?)")->execute([$company_id]);
            }
            $stmt = $pdo->prepare("UPDATE payroll_behaviours SET prorate_new_hires=?, email_payslips=?, password_protect_payslips=?, enable_overtime=? WHERE company_id=?");
            $stmt->execute([$prorate, $email_payslips, $password_protect, $overtime, $company_id]);
            $success_msg = "Behaviour settings updated.";
        } catch (PDOException $e) { $error_msg = "Error updating behaviours: " . $e->getMessage(); }
    }

    // 7. ATTENDANCE POLICY (NEW)
    elseif ($tab === 'attendance_save') {
        $method = $_POST['attendance_method'] ?? 'manual';
        // Policy fields
        $check_in_start = $_POST['check_in_start'] ?? '08:00';
        $check_in_end = $_POST['check_in_end'] ?? '09:00';
        $check_out_start = $_POST['check_out_start'] ?? '17:00';
        $check_out_end = $_POST['check_out_end'] ?? '18:00';
        $grace = intval($_POST['grace_period'] ?? 15);
        $enable_ip = isset($_POST['enable_ip']) ? 1 : 0;
        $require_supervisor = isset($_POST['require_supervisor']) ? 1 : 0;
        
        // Lateness deduction fields
        $lateness_enabled = isset($_POST['lateness_deduction_enabled']) ? 1 : 0;
        $lateness_amount = floatval($_POST['lateness_deduction_amount'] ?? 0);
        $lateness_type = $_POST['lateness_deduction_type'] ?? 'fixed';
        $per_minute_rate = floatval($_POST['lateness_per_minute_rate'] ?? 0);
        $max_deduction = floatval($_POST['max_lateness_deduction'] ?? 0);
        
        // Per-method lateness toggles
        $apply_manual = isset($_POST['lateness_apply_manual']) ? 1 : 0;
        $apply_self = isset($_POST['lateness_apply_self']) ? 1 : 0;
        $apply_biometric = isset($_POST['lateness_apply_biometric']) ? 1 : 0;
        
        try {
            $pdo->beginTransaction();
            
            // 1. Update Company Method
            $stmt = $pdo->prepare("UPDATE companies SET attendance_method = ? WHERE id = ?");
            $stmt->execute([$method, $company_id]);
            
            // 2. Update/Create Policy
            $check = $pdo->prepare("SELECT id FROM attendance_policies WHERE company_id = ?");
            $check->execute([$company_id]);
            if ($check->rowCount() == 0) {
                 $stmt = $pdo->prepare("INSERT INTO attendance_policies (company_id, check_in_start, check_in_end, check_out_start, check_out_end, grace_period_minutes, enable_ip_logging, require_supervisor_confirmation, lateness_deduction_enabled, lateness_deduction_amount, lateness_deduction_type, lateness_per_minute_rate, max_lateness_deduction, lateness_apply_manual, lateness_apply_self, lateness_apply_biometric) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                 $stmt->execute([$company_id, $check_in_start, $check_in_end, $check_out_start, $check_out_end, $grace, $enable_ip, $require_supervisor, $lateness_enabled, $lateness_amount, $lateness_type, $per_minute_rate, $max_deduction, $apply_manual, $apply_self, $apply_biometric]);
            } else {
                 $stmt = $pdo->prepare("UPDATE attendance_policies SET check_in_start=?, check_in_end=?, check_out_start=?, check_out_end=?, grace_period_minutes=?, enable_ip_logging=?, require_supervisor_confirmation=?, lateness_deduction_enabled=?, lateness_deduction_amount=?, lateness_deduction_type=?, lateness_per_minute_rate=?, max_lateness_deduction=?, lateness_apply_manual=?, lateness_apply_self=?, lateness_apply_biometric=? WHERE company_id=?");
                 $stmt->execute([$check_in_start, $check_in_end, $check_out_start, $check_out_end, $grace, $enable_ip, $require_supervisor, $lateness_enabled, $lateness_amount, $lateness_type, $per_minute_rate, $max_deduction, $apply_manual, $apply_self, $apply_biometric, $company_id]);
            }
            
            $pdo->commit();
            $_SESSION['flash_success'] = "Attendance policy updated successfully.";
            log_audit($company_id, $_SESSION['user_id'], 'UPDATE_ATTENDANCE_POLICY', "Updated attendance method to: $method");
            header("Location: company.php?tab=attendance");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Error updating attendance policy: " . $e->getMessage();
        }
    }
}

// --- FETCH DATA ---
// 1. Company
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();
if (!$company) {
    $company = [
        'name' => 'New Company',
        'email' => '',
        'phone' => '',
        'address' => '',
        'currency' => 'NGN',
        'tax_jurisdiction' => '',
        'logo_url' => ''
    ];
}
$company_name = $company['name'] ?? 'Company';

// 2. Statutory
$statutory = ['enable_paye'=>1, 'enable_pension'=>1, 'pension_employer_perc'=>10, 'pension_employee_perc'=>8, 'enable_nhis'=>0, 'enable_nhf'=>0];
try {
    $stmt = $pdo->prepare("SELECT * FROM statutory_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $statutory = $fetched;
    } else {
        $pdo->prepare("INSERT INTO statutory_settings (company_id) VALUES (?)")->execute([$company_id]);
    }
} catch (Exception $e) { /* ignore */ }

// 3. Behaviours
$behaviour = ['prorate_new_hires'=>1, 'email_payslips'=>0, 'password_protect_payslips'=>0, 'enable_overtime'=>0];
try {
    $stmt = $pdo->prepare("SELECT * FROM payroll_behaviours WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $behaviour = $fetched;
    } else {
        $pdo->prepare("INSERT INTO payroll_behaviours (company_id) VALUES (?)")->execute([$company_id]);
    }
} catch (Exception $e) { /* ignore */ }

// DEPARTMENTS (List for UI)
$departments_json = '[]';
try {
    // Fetch departments with employee count
    $stmt = $pdo->prepare("
        SELECT d.*, 
               (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id) as employee_count 
        FROM departments d 
        WHERE d.company_id = ? 
        ORDER BY d.name ASC
    ");
    $stmt->execute([$company_id]);
    $depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if($depts) {
        $departments_json = json_encode(array_map(function($d) {
            return [
                'id' => $d['id'],
                'name' => $d['name'],
                'code' => $d['code'],
                'active' => (bool)$d['is_active'],
                'employee_count' => (int)$d['employee_count']
            ];
        }, $depts));
    }
} catch (Exception $e) { /* ignore */ }


// PAYROLL ITEMS (List for UI)
// PAYROLL ITEMS (List for UI)
$items_json = '[]';
try {
    $items = [];
    
    // Bonuses
    $stmt = $pdo->prepare("SELECT *, 'bonus' as type FROM payroll_bonus_types WHERE company_id = ? ORDER BY name ASC");
    $stmt->execute([$company_id]);
    $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Deductions
    $stmt = $pdo->prepare("SELECT *, 'deduction' as type FROM payroll_deduction_types WHERE company_id = ? ORDER BY name ASC");
    $stmt->execute([$company_id]);
    $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items = array_merge($bonuses, $deductions);

    if($items) {
        $items_json = json_encode(array_map(function($i) {
            return [
                'id' => $i['id'],
                'name' => $i['name'],
                'type' => $i['type'],
                'method' => $i['calculation_mode'], // Note: schema column is calculation_mode
                'amount' => floatval($i['amount'] ?? 0), 
                'percentage' => floatval($i['percentage'] ?? 0), 
                'base' => $i['percentage_base'],
                'taxable' => (bool)($i['is_taxable'] ?? 1), 
                'pensionable' => (bool)($i['is_pensionable'] ?? 1), 
                'recurring' => 1, // Defaulting
                'active' => (bool)$i['is_active'],
                'custom' => 1 // All master list items considered custom/editable in this context
            ];
        }, $items));
    }
} catch (Exception $e) { /* ignore */ }

// MASTER LISTS (Bonuses & Deductions)
$master_bonuses = [
    "Sales Commission", "Productivity Bonus", "Attendance Bonus", "Service Charge Bonus", "Tips", "Unpaid Backlog Tip",
    "Unpaid Backlog Bonus", "Unpaid Backlog Commission", "Appraisal Bonus", "Overtime Pay", "Night Shift Allowance",
    "Weekend or Holiday Bonus", "Incentive Bonus", "Recognition Bonus", "Professional Certification Bonus", "Call-Out Bonus",
    "Supervisory Bonus", "Relief Bonus", "Employee of the Month", "Employee of the Year", "Holiday Bonus", "13th Month Bonus",
    "Quarterly Bonus", "Retention Bonus", "Joining Bonus", "Referral Commission", "Referral Bonus", "Project Completion Bonus",
    "Sign-On Bonus", "Loyalty/Anniversary Bonus", "Reallocation/Resettlement Allowance", "Expat Allowance", "Death Benefit Pay",
    "Maternity Gift or Bonus", "Marriage Bonus", "Birthday Bonus", "Special Recognition Award", "Training Reimbursement",
    "Promotion Adjustment", "Performance Grant", "Hardship Bonus", "Flight Ticket Bonus", "Tool/Equipment Allowance",
    "Shipboard Allowance", "Hostile Environment Premium", "Family Relocation Bonus", "Baby Delivery Support Bonus",
    "Back-to-School Bonus", "Mobilization Fee", "Demobilization Fee", "On-Call Bonus", "Daily Site Allowance",
    "Per Diem (Daily Living Expense)", "Milestone Bonus", "Fixed-Term Completion Bonus", "Hardware Stipend", "Tech Stack Allowance",
    "Coding/Production Bonus", "Bug Bounty Bonus", "Retention Tokens", "Court Appearance Bonus", "Client Billable Hour Bonus",
    "Professional Membership Fee Reimbursement", "Christmas Bonus", "Eid Bonus", "Black Friday Bonus", "Welcome Back Bonus",
    "Anniversary Celebration Bonus", "Campaign Success Bonus", "Festival Allowance", "Zero Disciplinary Case Bonus",
    "Punctuality Bonus", "Wellness Participation Bonus", "Internal Referral Bonus", "Idea Submission Bonus", "Culture Champion Bonus",
    "Signing Bonus (Executive)", "Board Attendance Fee", "Executive Car Grant", "Chairman's Gift Bonus", "Exit Appreciation Bonus",
    "Strategic Retention Allowance", "Other Bonus"
];



$master_allowances = [

    "Medical Allowance", "Utility Allowance", "Clothing / Wardrobe Allowance", "Leave Allowance",
    "Entertainment Allowance", "Responsibility Allowance", "Punctuality or Attendance Allowance", "Internet / Data Allowance",
    "Fuel / Transport Card Allowance", "Hazard Allowance", "Technical / Skill Allowance", "Domestic Staff Allowance",
    "Shift Allowance", "Professional Development Allowance", "Remote Work Allowance", "Field Allowance", "Vehicle Maintenance Allowance",
    "Risk Allowance", "Hardship Allowance", "Family Support Allowance", "Special Duty Allowance", "Child Education Allowance",
    "Confidentiality Allowance"
];

$master_deductions = [
    "Loan Repayment", "Unpaid Backlog Loan Repayment", "Salary Advance Recovery", "Staff Welfare Deductions", "Training Fees Recovery",
    "Staff Uniform", "Working Tools Deductions", "Lateness", "Absenteeism Deductions", "Damage to Company Property",
    "Lost Company Items Recovery", "Unapproved Expenses Recovery", "Misconduct Fines", "Disciplinary Charges",
    "Health Maintenance Organization (HMO) Premiums", "Court-Ordered Garnishments", "Insurance Premiums", "Mortgage Deductions",
    "Vehicle or Asset Financing Repayment", "Leave Without Pay (LWOP)", "Excess Leave Taken", "Training Bond Recovery",
    "Company Vehicle Usage Charges", "Phone Bill Reimbursement Deductions", "Accommodation Rent Deduction",
    "Utilities (Power, Water, Gas)", "Furniture Loan or Lease Repayment", "Laptop or Gadget Recovery", "Company Meal or Feeding Charges",
    "Internet or Wi-Fi Charges", "Official Attire/Uniform Deduction", "Fuel/Transport Card Repayment", "Advance Recovery",
    "Unretired Expense Claims", "Unpaid Tax Backlog Deduction", "Expatriate Work Permit Reimbursement",
    "Visa, Flight Ticket, Relocation Reimbursement", "Special HR Disciplinary Fines", "Staff Benevolent Fund",
    "End-of-Year Party Contribution", "Thrift Savings", "Esusu Deductions", "CSR or Fundraising Deductions",
    "Voluntary Religious Contributions", "Cooperative Society Contributions", "Christmas Contribution", "Ramadan Contribution",
    "Birthday Contribution", "Others"
];

// 4. Categories
$categories_json = '[]';
try {
    $stmt = $pdo->prepare("SELECT * FROM salary_categories WHERE company_id = ? ORDER BY name ASC");
    $stmt->execute([$company_id]);
    $categories_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if($categories_raw) {
        // Pre-fetch components map
        $comp_map = []; // id -> name
        $stmt_c = $pdo->prepare("SELECT id, name FROM salary_components WHERE company_id = ?");
        $stmt_c->execute([$company_id]);
        while($row = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
            $comp_map[$row['id']] = $row['name'];
        }

        // Fetch all breakdowns for this company's categories
        $stmt_b = $pdo->prepare("SELECT scb.* FROM salary_category_breakdown scb JOIN salary_categories sc ON scb.category_id = sc.id WHERE sc.company_id = ?");
        $stmt_b->execute([$company_id]);
        $all_breakdowns = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

        // Group breakdowns by category
        $breaks_by_cat = [];
        foreach($all_breakdowns as $row) {
            $breaks_by_cat[$row['category_id']][] = $row;
        }

        $categories_json = json_encode(array_map(function($c) use ($breaks_by_cat, $comp_map) {
            $my_breaks = $breaks_by_cat[$c['id']] ?? [];
            
            $struct = ['basic'=>0, 'housing'=>0, 'transport'=>0, 'other'=>0];
            
            foreach($my_breaks as $b) {
                $c_name = $comp_map[$b['salary_component_id']] ?? '';
                $perc = floatval($b['percentage']);
                
                if ($c_name === 'Basic Salary') $struct['basic'] += $perc;
                elseif ($c_name === 'Housing Allowance') $struct['housing'] += $perc;
                elseif ($c_name === 'Transport Allowance') $struct['transport'] += $perc;
                else $struct['other'] += $perc;
            }

            return [
                'id' => (int)$c['id'],
                'title' => $c['name'],
                'amount' => floatval($c['base_gross_amount']),
                'active' => (bool)($c['is_active'] ?? 1),
                'struct' => $struct
            ];
        }, $categories_raw));
    }
} catch (Exception $e) { /* ignore */ }

// 5. Components (NEW) - READ-ONLY FETCH (No Defaults Enforcement)
$components_json = '[]';
try {
    $stmt = $pdo->prepare("SELECT * FROM salary_components WHERE company_id = ? ORDER BY CASE WHEN type='basic' THEN 1 WHEN type='system' THEN 2 ELSE 3 END, name ASC");
    $stmt->execute([$company_id]);
    $comps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if($comps) {
        $components_json = json_encode(array_map(function($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'type' => $c['type'],
                'method' => $c['calculation_method'] ?? 'percentage',
                'amount' => floatval($c['amount'] ?? 0),
                'percentage' => floatval($c['percentage'] ?? 0) != 0 ? floatval($c['percentage']) : floatval($c['default_percentage'] ?? 0), // Fallback to default if 0
                'base' => $c['percentage_base'] ?? 'gross',
                'taxable' => (bool)$c['is_taxable'],
                'pensionable' => (bool)$c['is_pensionable'],
                'active' => (bool)$c['is_active'],
                'system' => in_array($c['type'], ['basic','system'])
            ];
        }, $comps), JSON_NUMERIC_CHECK);
    }
} catch(Exception $e) { /* ignore */ }

// 6. Attendance Policy (NEW)
$attendance_policy = [
    'method' => $company['attendance_method'] ?? 'manual',
    'check_in_start' => '08:00', 'check_in_end' => '09:00',
    'check_out_start' => '17:00','check_out_end' => '18:00',
    'grace_period' => 15, 'enable_ip' => 1, 'require_supervisor' => 1,
    'lateness_deduction_enabled' => 0,
    'lateness_deduction_amount' => 0,
    'lateness_deduction_type' => 'fixed',
    'lateness_per_minute_rate' => 0,
    'max_lateness_deduction' => 0,
    // Per-method lateness toggles
    'lateness_apply_manual' => 0,    // Manual: OFF by default (admin enters time)
    'lateness_apply_self' => 1,      // Self check-in: ON by default
    'lateness_apply_biometric' => 1  // Biometric: ON by default
];
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $pol = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pol) {
        $attendance_policy['check_in_start'] = substr($pol['check_in_start'], 0, 5);
        $attendance_policy['check_in_end'] = substr($pol['check_in_end'], 0, 5);
        $attendance_policy['check_out_start'] = substr($pol['check_out_start'], 0, 5);
        $attendance_policy['check_out_end'] = substr($pol['check_out_end'], 0, 5);
        $attendance_policy['grace_period'] = (int)$pol['grace_period_minutes'];
        $attendance_policy['enable_ip'] = (int)$pol['enable_ip_logging'];
        $attendance_policy['require_supervisor'] = (int)$pol['require_supervisor_confirmation'];
        $attendance_policy['lateness_deduction_enabled'] = (int)($pol['lateness_deduction_enabled'] ?? 0);
        $attendance_policy['lateness_deduction_amount'] = floatval($pol['lateness_deduction_amount'] ?? 0);
        $attendance_policy['lateness_deduction_type'] = $pol['lateness_deduction_type'] ?? 'fixed';
        $attendance_policy['lateness_per_minute_rate'] = floatval($pol['lateness_per_minute_rate'] ?? 0);
        $attendance_policy['max_lateness_deduction'] = floatval($pol['max_lateness_deduction'] ?? 0);
        // Per-method toggles
        $attendance_policy['lateness_apply_manual'] = (int)($pol['lateness_apply_manual'] ?? 0);
        $attendance_policy['lateness_apply_self'] = (int)($pol['lateness_apply_self'] ?? 1);
        $attendance_policy['lateness_apply_biometric'] = (int)($pol['lateness_apply_biometric'] ?? 1);
    }
} catch (Exception $e) { /* ignore */ }


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Setup - Mipaymaster</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('companySetup', () => ({
                // TABS
                currentTab: '<?php 
                    $t = $_GET['tab'] ?? 'profile'; 
                    echo in_array($t, ['profile','departments','items','components','categories','statutory','behaviour']) ? $t : 'profile';
                ?>',
                itemTab: '<?php echo $_GET['item_tab'] ?? 'bonus'; ?>',
                
                // STATE DATA
                departments: <?php echo $departments_json; ?>,
                payrollItems: <?php echo $items_json; ?>,
                salaryComponents: [],
                componentsLoaded: false,
                componentsSaving: false,
                categories: <?php echo $categories_json; ?>,
                
                // MASTER LISTS
                masterBonuses: <?php echo json_encode($master_bonuses); ?>,
                masterDeductions: <?php echo json_encode($master_deductions); ?>,
                masterAllowances: <?php echo json_encode($master_allowances); ?>,

                // STATUTORY & BEHAVIOUR (Reactive State)
                statutory: {
                    paye: <?php echo $statutory['enable_paye'] ? 'true' : 'false'; ?>,
                    pension: <?php echo $statutory['enable_pension'] ? 'true' : 'false'; ?>,
                    nhis: <?php echo $statutory['enable_nhis'] ? 'true' : 'false'; ?>,
                    nhf: <?php echo $statutory['enable_nhf'] ? 'true' : 'false'; ?>,
                    pension_employer: <?php echo $statutory['pension_employer_perc'] ?? 0; ?>,
                    pension_employee: <?php echo $statutory['pension_employee_perc'] ?? 0; ?>
                },
                behaviour: {
                    prorate: <?php echo $behaviour['prorate_new_hires'] ? 'true' : 'false'; ?>,
                    email: <?php echo $behaviour['email_payslips'] ? 'true' : 'false'; ?>,
                    password: <?php echo $behaviour['password_protect_payslips'] ? 'true' : 'false'; ?>,
                    overtime: <?php echo $behaviour['enable_overtime'] ? 'true' : 'false'; ?>
                },
                attendance: {
                    method: '<?php echo $attendance_policy['method']; ?>',
                    check_in_start: '<?php echo $attendance_policy['check_in_start']; ?>',
                    check_in_end: '<?php echo $attendance_policy['check_in_end']; ?>',
                    check_out_start: '<?php echo $attendance_policy['check_out_start']; ?>',
                    check_out_end: '<?php echo $attendance_policy['check_out_end']; ?>',
                    grace: <?php echo $attendance_policy['grace_period']; ?>,
                    ip: <?php echo $attendance_policy['enable_ip'] ? 'true' : 'false'; ?>,
                    supervisor: <?php echo $attendance_policy['require_supervisor'] ? 'true' : 'false'; ?>,
                    lateness_enabled: <?php echo $attendance_policy['lateness_deduction_enabled'] ? 'true' : 'false'; ?>,
                    lateness_type: '<?php echo $attendance_policy['lateness_deduction_type']; ?>',
                    lateness_amount: <?php echo $attendance_policy['lateness_deduction_amount']; ?>,
                    per_minute_rate: <?php echo $attendance_policy['lateness_per_minute_rate']; ?>,
                    max_deduction: <?php echo $attendance_policy['max_lateness_deduction']; ?>,
                    // Per-method lateness toggles
                    apply_manual: <?php echo $attendance_policy['lateness_apply_manual'] ? 'true' : 'false'; ?>,
                    apply_self: <?php echo $attendance_policy['lateness_apply_self'] ? 'true' : 'false'; ?>,
                    apply_biometric: <?php echo $attendance_policy['lateness_apply_biometric'] ? 'true' : 'false'; ?>
                },
                
                // FORMS
                deptForm: { id: null, name: '', code: '', active: true },
                itemForm: { id: null, name: '', type: 'bonus', method: 'fixed', amount: 0, percentage: 0, base: 'basic', taxable: true, pensionable: true, recurring: true, active: true, custom: 0 },
                catForm: { id: null, title: '', amount: 0, basic: 40, housing: 30, transport: 20, other: 10 },
                newComponent: { name: '', method: 'fixed', amount: 0, percentage: 0, base: 'basic', taxable: <?php echo $statutory['enable_paye'] ? 'true' : 'false'; ?>, pensionable: <?php echo $statutory['enable_pension'] ? 'true' : 'false'; ?>, custom: 0 },
                
                // UI STATE
                itemDrawerOpen: false,
                itemSearch: '',
                compSearch: '',
                compDrawerOpen: false,
                editMode: false,
                previewSalary: 500000,
                
                init() {
                    // Update URL on tab change
                    this.$watch('currentTab', (val) => {
                        const url = new URL(window.location);
                        url.searchParams.set('tab', val);
                        window.history.pushState({}, '', url);
                        setTimeout(() => lucide.createIcons(), 100);
                    });
                    
                    this.$watch('itemTab', (val) => {
                        const url = new URL(window.location);
                        url.searchParams.set('item_tab', val);
                        window.history.pushState({}, '', url);
                    });

                    // FIX: Load Components and Set Defaults
                    this.salaryComponents = window.__SALARY_COMPONENTS__ || [];
                    this.resetCatForm();
                    
                    // Initial Icon Load
                    setTimeout(() => lucide.createIcons(), 100);

                    // Load isolated state
                    if (!this.componentsLoaded && window.__SALARY_COMPONENTS__) {
                        this.salaryComponents = JSON.parse(JSON.stringify(window.__SALARY_COMPONENTS__));
                        this.componentsLoaded = true;
                    }
                },

                // GETTERS (COMPUTED)
                get totalAllocated() {
                    return this.salaryComponents
                        .filter(c => c.method === 'percentage' && c.active)
                        .reduce((sum, c) => sum + Number(c.percentage || 0), 0);
                },
                get totalRemaining() {
                    return 100 - this.totalAllocated;
                },
                get canSave() {
                    return Math.abs(this.totalAllocated - 100) < 0.01;
                },
                get filteredItems() {
                    return this.payrollItems.filter(i => i.type === this.itemTab);
                },
                get masterList() {
                    const list = this.itemTab === 'bonus' ? this.masterBonuses : this.masterDeductions;
                    if(!this.itemSearch) return [];
                    return list.filter(item => item.toLowerCase().includes(this.itemSearch.toLowerCase()));
                },
                get masterAllowanceList() {
                    if(!this.compSearch) return [];
                    return this.masterAllowances.filter(item => item.toLowerCase().includes(this.compSearch.toLowerCase()));
                },
                get otherComponents() {
                    return this.salaryComponents.filter(c => {
                        // Exclude self (if editing)
                        if (this.newComponent.id && c.id === this.newComponent.id) return false;
                        // Exclude inactive
                        if (!c.active) return false;
                        // Exclude those already based on other components (prevent circular chains depth > 1 for simplicity)
                        // Actually, simplified: just return all active components except self.
                        return true;
                    });
                },

                // SALARY COMPONENT METHODS
                openCompDrawer(comp = null) {
                    this.compDrawerOpen = true;
                    if (comp) {
                        this.newComponent = { 
                            ...comp, 
                            amount: parseFloat(comp.amount || 0),
                            percentage: parseFloat(comp.percentage || 0),
                            method: comp.method || 'fixed',
                            base: comp.base || 'basic'
                        };
                        this.compSearch = comp.name;
                    } else {
                        this.newComponent = { name: '', method: 'fixed', amount: 0, percentage: 0, base: 'basic', taxable: <?php echo $statutory['enable_paye'] ? 'true' : 'false'; ?>, pensionable: <?php echo $statutory['enable_pension'] ? 'true' : 'false'; ?>, custom: 0 };
                        this.compSearch = '';
                    }
                },
                selectMasterAllowance(name) {
                    this.newComponent.name = name;
                    this.newComponent.custom = 0;
                    this.compSearch = name;
                },
                addCustomAllowance() {
                    this.newComponent.name = this.compSearch;
                    this.newComponent.custom = 1;
                },
                saveAllComponents() {
                    if (!this.canSave) {
                        alert('Salary structure must total exactly 100%.\nCurrent: ' + this.totalAllocated.toFixed(2) + '%');
                        return;
                    }
                    this.componentsSaving = true;
                    fetch('company.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tab: 'save_salary_components', components: this.salaryComponents })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.componentsSaving = false;
                        if (data.success) {
                            this.salaryComponents = data.components;
                            window.__SALARY_COMPONENTS__ = data.components;
                            alert('✓ Salary structure saved successfully!');
                        } else {
                            alert('Error: ' + (data.error || 'Failed to save components'));
                        }
                    })
                    .catch(err => {
                        this.componentsSaving = false;
                        alert('Network error. Please try again.');
                    });
                },
                saveComponent(comp) {
                    // Bulk validation check
                    let total = 0;
                    this.salaryComponents.forEach(c => {
                        if(!c.active || c.id === comp.id) return;
                        if (c.method === 'percentage') total += parseFloat(c.percentage || 0);
                    });
                    total += parseFloat(comp.percentage || 0);
                    
                    if (total > 100) {
                        alert('Total salary structure cannot exceed 100%.\nCurrent total: ' + total.toFixed(2) + '%');
                        const original = window.__SALARY_COMPONENTS__.find(c => c.id === comp.id);
                        if (original) comp.percentage = original.percentage;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('tab', 'component_save_json');
                    formData.append('comp_id', comp.id);
                    formData.append('comp_name', comp.name);
                    formData.append('comp_method', comp.method || 'percentage');
                    formData.append('comp_amount', comp.amount || 0);
                    formData.append('comp_perc', comp.percentage || 0);
                    formData.append('comp_base', comp.base || 'gross');
                    formData.append('comp_type', comp.type);
                    formData.append('comp_custom', comp.system ? 0 : 1);
                    if (comp.taxable) formData.append('comp_taxable', '1');
                    if (comp.pensionable) formData.append('comp_pensionable', '1');
                    if (comp.active) formData.append('comp_active', '1');

                    fetch('company.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const index = this.salaryComponents.findIndex(c => c.id === comp.id);
                            if (index !== -1 && data.component) this.salaryComponents[index] = data.component;
                            window.__SALARY_COMPONENTS__ = [...this.salaryComponents];
                        } else {
                            alert('Error: ' + (data.error || 'Failed to save'));
                            const original = window.__SALARY_COMPONENTS__.find(c => c.id === comp.id);
                            if (original) Object.assign(comp, original);
                        }
                    })
                    .catch(err => alert('Network error.'));
                },
                deleteComponent(id) {
                    if(confirm('Delete this allowance?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `<input type='hidden' name='tab' value='component_delete'><input type='hidden' name='comp_id' value='${id}'>`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                },

                addComponent() {
                    if(!this.newComponent.name) return alert('Please enter or select a name');
                    
                    // Validation: Check duplicate name
                    if(this.salaryComponents.some(c => c.name.toLowerCase() === this.newComponent.name.toLowerCase())) {
                         return alert('This component already exists in your structure.');
                    }
            
                    // Percentage Validation
                    if (this.newComponent.method === 'percentage') {
                         const currentTotal = this.totalAllocated;
                         const newTotal = currentTotal + parseFloat(this.newComponent.percentage || 0);
                         if (this.newComponent.base === 'gross' && newTotal > 100) {
                             return alert('Total salary structure cannot exceed 100%.\nCurrent: ' + currentTotal.toFixed(2) + '%\nNew: ' + newTotal.toFixed(2) + '%');
                         }
                    }
            
                    this.componentsSaving = true;
                    const formData = new FormData();
                    formData.append('tab', 'component_save_json');
                    formData.append('comp_name', this.newComponent.name);
                    formData.append('comp_method', this.newComponent.method);
                    formData.append('comp_amount', this.newComponent.amount);
                    formData.append('comp_perc', this.newComponent.percentage);
                    formData.append('comp_base', this.newComponent.base);
                    formData.append('comp_type', 'allowance');
                    formData.append('comp_custom', this.newComponent.custom);
                    if (this.newComponent.taxable) formData.append('comp_taxable', '1');
                    if (this.newComponent.pensionable) formData.append('comp_pensionable', '1');
                    formData.append('comp_active', '1');
            
                    fetch('company.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        this.componentsSaving = false;
                        if (data.success) {
                            this.salaryComponents.push(data.component);
                            window.__SALARY_COMPONENTS__ = this.salaryComponents;
                            this.compDrawerOpen = false;
                            // Reset form
                            this.newComponent = { name: '', method: 'fixed', amount: 0, percentage: 0, base: 'basic', taxable: true, pensionable: true, custom: 0 };
                            // Clear search
                            this.compSearch = '';
                        } else {
                            alert('Error: ' + (data.error || 'Failed to add component'));
                        }
                    })
                    .catch(err => {
                        this.componentsSaving = false;
                        console.error(err);
                        alert('Network error.');
                    });
                },

                // ITEM METHODS
                openItemDrawer(type, item = null) {
                    this.itemDrawerOpen = true;
                    this.itemTab = type;
                    if (item) {
                        this.itemForm = { ...item };
                        this.itemSearch = item.name;
                    } else {
                        this.itemSearch = '';
                        this.itemForm = { id: null, name: '', type: type, method: 'fixed', amount: 0, percentage: 0, base: 'basic', taxable: true, pensionable: true, recurring: true, active: true, custom: 0 };
                    }
                },
                selectMasterItem(name) {
                    this.itemForm.name = name;
                    this.itemForm.custom = 0;
                    this.itemSearch = name;
                },
                addCustomItem() {
                    this.itemForm.name = this.itemSearch;
                    this.itemForm.custom = 1;
                },
                saveItem() {
                    if(!this.itemForm.name) return;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    let html = "<input type='hidden' name='tab' value='item_save'>";
                    if(this.itemForm.id) html += `<input type='hidden' name='item_id' value='${this.itemForm.id}'>`;
                    ['name','type','method','amount','percentage','base'].forEach(f => {
                         html += `<input type='hidden' name='item_${f}' value='${this.itemForm[f]}'>`;
                    });
                    html += `<input type='hidden' name='item_custom' value='${this.itemForm.custom ? 1 : 0}'>`;
                    ['taxable','pensionable','recurring','active'].forEach(f => {
                         if(this.itemForm[f]) html += `<input type='hidden' name='item_${f}' value='1'>`;
                    });
                    form.innerHTML = html;
                    document.body.appendChild(form);
                    form.submit();
                },
                deleteItem(id, type) {
                    if(confirm('Delete this payroll item?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `<input type='hidden' name='tab' value='item_delete'><input type='hidden' name='item_id' value='${id}'><input type='hidden' name='item_type' value='${type}'>`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                },
                toggleItem(id) {
                     const form = document.createElement('form');
                     form.method = 'POST';
                     form.innerHTML = `<input type='hidden' name='tab' value='item_toggle'><input type='hidden' name='item_id' value='${id}'>`;
                     document.body.appendChild(form);
                     form.submit();
                },
                calculatePreview(item) {
                    if (item.method === 'fixed') return item.amount;
                    const gross = this.previewSalary;
                    const basic = gross * 0.4;
                    const breakdown = {
                         basic: gross * 0.4,
                         housing: gross * 0.3,
                         transport: gross * 0.2,
                         other: gross * 0.1
                    };
                    const baseVal = item.base === 'gross' ? gross : (item.base === 'basic' ? breakdown.basic : (item.base === 'housing' ? breakdown.housing : (item.base === 'transport' ? breakdown.transport : breakdown.other)));
                    return (baseVal * (item.percentage / 100)).toFixed(2);
                },

                // DEPARTMENT METHODS
                editDepartment(dept) {
                    this.deptForm = { id: dept.id, name: dept.name, code: dept.code, active: dept.active };
                },
                resetDeptForm() {
                    this.deptForm = { id: null, name: '', code: '', active: true };
                },
                saveDepartment() {
                    if(!this.deptForm.name) return;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    let html = "<input type='hidden' name='tab' value='department_save'>";
                    if(this.deptForm.id) html += `<input type='hidden' name='dept_id' value='${this.deptForm.id}'>`;
                    html += `<input type='hidden' name='dept_name' value='${this.deptForm.name}'>`;
                    html += `<input type='hidden' name='dept_code' value='${this.deptForm.code}'>`;
                    if(this.deptForm.active) html += "<input type='hidden' name='dept_active' value='1'>";
                    form.innerHTML = html;
                    document.body.appendChild(form);
                    form.submit();
                },
                updateDeptInput() {
                    // 1. Sentence Case Name
                    let words = this.deptForm.name.toLowerCase().split(' ');
                    for (let i = 0; i < words.length; i++) {
                        if (words[i].length > 0) {
                            words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
                        }
                    }
                    this.deptForm.name = words.join(' ');
                    
                    // 2. Auto-Gen Code (First 3 chars, Uppercase)
                    let clean = this.deptForm.name.replace(/[^a-zA-Z]/g, '');
                    if(clean.length >= 0) {
                        this.deptForm.code = clean.substring(0, 3).toUpperCase();
                    }
                },
                deleteDepartment(id, count) {
                    if(count > 0) return alert('Cannot delete department with assigned employees.');
                    if(confirm('Delete this department?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `<input type='hidden' name='tab' value='department_delete'><input type='hidden' name='dept_id' value='${id}'>`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                },
                toggleDepartment(id) {
                     const form = document.createElement('form');
                     form.method = 'POST';
                     form.innerHTML = `<input type='hidden' name='tab' value='department_toggle'><input type='hidden' name='dept_id' value='${id}'>`;
                     document.body.appendChild(form);
                     form.submit();
                },

                // CATEGORY METHODS
                editCategory(cat) {
                    this.editMode = true;
                    this.catForm = { id: cat.id, title: cat.title, amount: cat.amount, ...cat.struct };
                },
                toggleCategory(id) {
                     const form = document.createElement('form');
                     form.method = 'POST';
                     form.innerHTML = `<input type='hidden' name='tab' value='category_toggle'><input type='hidden' name='cat_id' value='${id}'>`;
                     document.body.appendChild(form);
                     form.submit();
                },
                resetCatForm() {
                    this.editMode = false;
                    // Dynamic Defaults from System Components
                    const getPerc = (type) => {
                        const comp = this.salaryComponents.find(c => (c.type === type) || (type==='housing' && c.name.toLowerCase().includes('housing')) || (type==='transport' && c.name.toLowerCase().includes('transport')));
                        return comp ? Number(comp.percentage) : 0;
                    };
                    
                    const basic = getPerc('basic') || 50;
                    const housing = getPerc('system') || 30; // Fallback, though 'system' is broad. Better logic below.
                    
                    // Improved Lookup
                    let b = 50, h = 30, t = 20;
                    this.salaryComponents.forEach(c => {
                        const n = c.name.toLowerCase();
                        if(c.type === 'basic') b = Number(c.percentage);
                        else if(n.includes('housing')) h = Number(c.percentage);
                        else if(n.includes('transport')) t = Number(c.percentage);
                    });
                     
                    this.catForm = { id: null, title: '', amount: 0, basic: b, housing: h, transport: t, other: Math.max(0, 100 - (b+h+t)) };
                },
                saveCategory() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    let html = "<input type='hidden' name='tab' value='category_save'>";
                    if(this.catForm.id) html += `<input type='hidden' name='cat_id' value='${this.catForm.id}'>`;
                    html += `<input type='hidden' name='cat_name' value='${this.catForm.title}'>`;
                    html += `<input type='hidden' name='cat_gross' value='${this.catForm.amount}'>`;
                    ['basic','housing','transport','other'].forEach(v => {
                        html += `<input type='hidden' name='cat_${v}' value='${this.catForm[v]}'>`;
                    });
                    form.innerHTML = html;
                    document.body.appendChild(form);
                    form.submit();
                },
                prepareDelete(id) {
                    if(confirm('Are you sure?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = "<input type='hidden' name='tab' value='category_delete'><input type='hidden' name='cat_id' value='" + id + "'>";
                        document.body.appendChild(form);
                        form.submit();
                    }
                },
                updateCategoryTitle() {
                    // Auto Sentence Case
                    let words = this.catForm.title.toLowerCase().split(' ');
                    for (let i = 0; i < words.length; i++) {
                        if (words[i].length > 0) {
                            words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
                        }
                    }
                    this.catForm.title = words.join(' ');
                },
                checkBreakdownNotification() {
                    if (this.catForm.amount > 0) {
                        alert("Please check and edit the breakdown split % if necessary.");
                    }
                }
            }));
        });
    </script>
    
    <!-- FIX 1: Inject State (Moved to Body to prevent Quirks Mode) -->
    <script>
        window.__SALARY_COMPONENTS__ = <?php echo $components_json ?: '[]'; ?>;
    </script>

    <!-- Load Alpine AFTER component definitions -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        .form-input { @apply w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm; }
        .form-label { @apply block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1; }
        .tab-active { @apply bg-slate-900 dark:bg-brand-600 text-white shadow-sm; }
        .tab-inactive { @apply bg-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800; }
        .toolbar-hidden { display: none; }
        .toolbar-visible { display: block; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="companySetup()">

    <!-- DATA INITIALIZATION -->
    
    <!-- SIDEBAR -->
    <!-- A. LEFT SIDEBAR -->
    <?php $current_page = 'company'; include '../includes/dashboard_sidebar.php'; ?>

        <!-- NOTIFICATIONS PANEL (SLIDE-OVER) - Starts hidden -->
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800" style="visibility: hidden;">
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
                <div class="p-3 bg-brand-50 dark:bg-brand-900/10 rounded-lg border-l-4 border-brand-500">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Payroll Completed</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">January 2026 payroll has been processed successfully.</p>
                    <div class="mt-2 flex gap-2">
                        <button class="text-xs text-brand-600 font-medium hover:underline">View</button>
                    </div>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Approval Required</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">2 leave requests are pending your approval.</p>
                    <div class="mt-2 flex gap-2">
                        <button class="text-xs text-brand-600 font-medium hover:underline">Review</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- MAIN -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <!-- Header -->
            <?php $page_title = 'Company Setup'; include '../includes/dashboard_header.php'; ?>

            <!-- Horizontal Nav (Hidden by default) -->
            <div id="horizontal-nav" class="hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-6 py-2">
                <!-- Dynamic Nav Content -->
            </div>

            <!-- Collapsed Toolbar -->
            <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>



            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900">
                <div class="max-w-6xl mx-auto">
                    
                    <?php if ($success_msg): ?>
                        <div class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800"><?php echo $success_msg; ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800"><?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <!-- Tabs - Icon on Top Design -->
                    <div class="mb-8">
                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-2 p-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                            
                            <button @click="currentTab = 'profile'" :class="currentTab === 'profile' ? 'bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-brand-50 dark:hover:bg-slate-700 hover:text-brand-600 dark:hover:text-brand-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="building-2" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Profile</span>
                            </button>
                            
                            <button @click="currentTab = 'departments'" :class="currentTab === 'departments' ? 'bg-gradient-to-br from-violet-500 to-violet-700 text-white shadow-lg shadow-violet-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-violet-50 dark:hover:bg-slate-700 hover:text-violet-600 dark:hover:text-violet-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="users" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Departments</span>
                            </button>
                            
                            <button @click="currentTab = 'items'" :class="currentTab === 'items' ? 'bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg shadow-emerald-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-slate-700 hover:text-emerald-600 dark:hover:text-emerald-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Payroll Items</span>
                            </button>
                            
                            <button @click="currentTab = 'components'" :class="currentTab === 'components' ? 'bg-gradient-to-br from-cyan-500 to-cyan-700 text-white shadow-lg shadow-cyan-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-cyan-50 dark:hover:bg-slate-700 hover:text-cyan-600 dark:hover:text-cyan-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="layers" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Components</span>
                            </button>
                            
                            <button @click="currentTab = 'categories'" :class="currentTab === 'categories' ? 'bg-gradient-to-br from-amber-500 to-amber-700 text-white shadow-lg shadow-amber-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-amber-50 dark:hover:bg-slate-700 hover:text-amber-600 dark:hover:text-amber-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="list" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Categories</span>
                            </button>
                            
                            <button @click="currentTab = 'statutory'" :class="currentTab === 'statutory' ? 'bg-gradient-to-br from-rose-500 to-rose-700 text-white shadow-lg shadow-rose-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-rose-50 dark:hover:bg-slate-700 hover:text-rose-600 dark:hover:text-rose-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="scale" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Statutory</span>
                            </button>
                            
                            <button @click="currentTab = 'behaviour'" :class="currentTab === 'behaviour' ? 'bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-lg shadow-indigo-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-indigo-50 dark:hover:bg-slate-700 hover:text-indigo-600 dark:hover:text-indigo-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="sliders" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Behaviour</span>
                            </button>
                            
                            <button @click="currentTab = 'attendance'" :class="currentTab === 'attendance' ? 'bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-lg shadow-teal-500/30 scale-105' : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-teal-50 dark:hover:bg-slate-700 hover:text-teal-600 dark:hover:text-teal-400 hover:scale-102'" class="relative flex flex-col items-center justify-center gap-1.5 p-3 rounded-xl text-center transition-all duration-200 group">
                                <i data-lucide="clock" class="w-5 h-5"></i>
                                <span class="text-xs font-semibold">Attendance</span>
                            </button>
                            
                        </div>
                    </div>

                    <!-- TABBED CONTENT AREA -->
                    <div class="mb-12">
                        
                        <!-- TAB 7: ATTENDANCE POLICY (NEW) -->
                        <div x-show="currentTab === 'attendance'" x-cloak>
                            <form method="POST" class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                                <input type="hidden" name="tab" value="attendance_save">
                                
                                <div class="mb-8">
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Attendance Method Selection</h3>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Choose the primary way your company captures employee attendance.</p>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <!-- Manual -->
                                        <label class="cursor-pointer relative group">
                                            <input type="radio" name="attendance_method" value="manual" x-model="attendance.method" class="peer sr-only">
                                            <div class="p-6 rounded-xl border-2 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/10 transition-all h-full flex flex-col items-center text-center">
                                                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4 text-slate-500 peer-checked:text-brand-600 peer-checked:bg-brand-100 dark:peer-checked:bg-brand-900/30 group-hover:scale-110 transition-transform">
                                                    <i data-lucide="clipboard-list" class="w-6 h-6"></i>
                                                </div>
                                                <h4 class="font-bold text-slate-900 dark:text-white mb-2">Manual Entry</h4>
                                                <p class="text-xs text-slate-500 leading-relaxed">Admin/HR manually records attendance. No employee interaction. Best for small teams.</p>
                                            </div>
                                            <div class="absolute top-4 right-4 text-brand-600 opacity-0 peer-checked:opacity-100 transition-opacity"><i data-lucide="check-circle-2" class="w-6 h-6 fill-current"></i></div>
                                        </label>
                                        
                                        <!-- Self Check-In -->
                                        <label class="cursor-pointer relative group">
                                            <input type="radio" name="attendance_method" value="self" x-model="attendance.method" class="peer sr-only">
                                            <div class="p-6 rounded-xl border-2 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/10 transition-all h-full flex flex-col items-center text-center">
                                                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4 text-slate-500 peer-checked:text-brand-600 peer-checked:bg-brand-100 dark:peer-checked:bg-brand-900/30 group-hover:scale-110 transition-transform">
                                                    <i data-lucide="smartphone" class="w-6 h-6"></i>
                                                </div>
                                                <h4 class="font-bold text-slate-900 dark:text-white mb-2">Mobile Check-In</h4>
                                                <p class="text-xs text-slate-500 leading-relaxed">Employees clock in/out via dashboard. Requires validation rules & audit logging.</p>
                                            </div>
                                             <div class="absolute top-4 right-4 text-brand-600 opacity-0 peer-checked:opacity-100 transition-opacity"><i data-lucide="check-circle-2" class="w-6 h-6 fill-current"></i></div>
                                        </label>
                                        
                                        <!-- Biometric -->
                                        <label class="cursor-pointer relative group">
                                            <input type="radio" name="attendance_method" value="biometric" x-model="attendance.method" class="peer sr-only">
                                            <div class="p-6 rounded-xl border-2 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/10 transition-all h-full flex flex-col items-center text-center">
                                                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4 text-slate-500 peer-checked:text-brand-600 peer-checked:bg-brand-100 dark:peer-checked:bg-brand-900/30 group-hover:scale-110 transition-transform">
                                                    <i data-lucide="fingerprint" class="w-6 h-6"></i>
                                                </div>
                                                <h4 class="font-bold text-slate-900 dark:text-white mb-2">Biometric Sync</h4>
                                                <p class="text-xs text-slate-500 leading-relaxed">Syncs with biometric devices using API. Automated logging with minimal interaction.</p>
                                            </div>
                                             <div class="absolute top-4 right-4 text-brand-600 opacity-0 peer-checked:opacity-100 transition-opacity"><i data-lucide="check-circle-2" class="w-6 h-6 fill-current"></i></div>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Configuration Panels -->
                                <div class="bg-white dark:bg-slate-950">
                                    <div x-show="attendance.method === 'manual'" x-transition class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 text-center">
                                        <p class="text-sm text-slate-600 dark:text-slate-400">Manual entry is the default mode. No additional configuration is required.</p>
                                    </div>
                                    
                                    <div x-show="attendance.method === 'self'" x-transition class="space-y-6">
                                        <div class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <h4 class="font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4 text-brand-500"></i> Working Hours & Lateness</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label class="form-label">Earliest Check-In</label>
                                                    <input type="time" name="check_in_start" x-model="attendance.check_in_start" class="form-input">
                                                </div>
                                                <div>
                                                    <label class="form-label">Calculated Late After</label>
                                                    <input type="time" name="check_in_end" x-model="attendance.check_in_end" class="form-input">
                                                    <p class="text-xs text-amber-600 mt-1">Check-ins after this time are marked "Late".</p>
                                                </div>
                                                 <div>
                                                    <label class="form-label">Earliest Check-Out (Without Penalty)</label>
                                                    <input type="time" name="check_out_start" x-model="attendance.check_out_start" class="form-input">
                                                </div>
                                                <div>
                                                    <label class="form-label">Latest Check-Out</label>
                                                    <input type="time" name="check_out_end" x-model="attendance.check_out_end" class="form-input">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <h4 class="font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4 text-brand-500"></i> Validation & Controls</h4>
                                            <div class="space-y-4">
                                                 <label class="flex items-center justify-between p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-brand-500 transition-colors">
                                                    <div>
                                                        <span class="block text-sm font-bold text-slate-700 dark:text-slate-200">Grace Period</span>
                                                        <span class="text-xs text-slate-500">Allow check-ins within X minutes of start without penalty.</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" name="grace_period" x-model="attendance.grace" class="form-input w-20 text-center" min="0">
                                                        <span class="text-xs text-slate-500">mins</span>
                                                    </div>
                                                </label>
                                                
                                                <label class="flex items-center justify-between p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-brand-500 transition-colors">
                                                    <div>
                                                        <span class="block text-sm font-bold text-slate-700 dark:text-slate-200">Log IP Address & Device Info</span>
                                                        <span class="text-xs text-slate-500">Capture location metrics for audit trail.</span>
                                                    </div>
                                                    <input type="checkbox" name="enable_ip" value="1" x-model="attendance.ip" class="w-5 h-5 text-brand-600 rounded focus:ring-brand-500">
                                                </label>

                                                <label class="flex items-center justify-between p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-brand-500 transition-colors">
                                                    <div>
                                                        <span class="block text-sm font-bold text-slate-700 dark:text-slate-200">Supervisor Confirmation</span>
                                                        <span class="text-xs text-slate-500">Require supervisor approval before payroll impact.</span>
                                                    </div>
                                                    <input type="checkbox" name="require_supervisor" value="1" x-model="attendance.supervisor" class="w-5 h-5 text-brand-600 rounded focus:ring-brand-500">
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="attendance.method === 'biometric'" x-transition class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 text-center">
                                         <div class="flex justify-center mb-4"><i data-lucide="server" class="w-12 h-12 text-slate-400"></i></div>
                                         <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Biometric integrations are configured in the <a href="attendance.php?tab=biometrics" class="text-brand-600 font-bold hover:underline">Attendance Module</a>.</p>
                                         <p class="text-xs text-slate-500">Use this setting to declare Biometrics as the company's primary truth source for payroll.</p>
                                    </div>
                                    
                                    <!-- LATENESS DEDUCTION SECTION - Always Visible -->
                                    <div class="mt-6 p-6 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-800">
                                        <h4 class="font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2"><i data-lucide="alert-circle" class="w-5 h-5 text-amber-500"></i> Lateness Deduction Settings</h4>
                                        <div class="space-y-4">
                                            <label class="flex items-center justify-between p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-amber-400 transition-colors">
                                                <div>
                                                    <span class="block text-sm font-bold text-slate-700 dark:text-slate-200">Enable Lateness Deduction</span>
                                                    <span class="text-xs text-slate-500">Automatically deduct from salary when employees check in late.</span>
                                                </div>
                                                <input type="checkbox" name="lateness_deduction_enabled" value="1" x-model="attendance.lateness_enabled" class="w-5 h-5 text-amber-600 rounded focus:ring-amber-500">
                                            </label>
                                            
                                            <div x-show="attendance.lateness_enabled" x-transition class="space-y-4 pt-4 border-t border-amber-200 dark:border-amber-800">
                                                <div>
                                                    <label class="form-label">Deduction Type</label>
                                                    <select name="lateness_deduction_type" x-model="attendance.lateness_type" class="form-input">
                                                        <option value="fixed">Fixed Amount (per incident)</option>
                                                        <option value="per_minute">Per Minute Late</option>
                                                        <option value="percentage">Percentage of Daily Pay</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div x-show="attendance.lateness_type === 'fixed'">
                                                        <label class="form-label">Fixed Deduction Amount (₦)</label>
                                                        <input type="number" name="lateness_deduction_amount" x-model="attendance.lateness_amount" class="form-input" min="0" step="100">
                                                    </div>
                                                    
                                                    <div x-show="attendance.lateness_type === 'per_minute'">
                                                        <label class="form-label">Rate Per Minute Late (₦)</label>
                                                        <input type="number" name="lateness_per_minute_rate" x-model="attendance.per_minute_rate" class="form-input" min="0" step="10">
                                                    </div>
                                                    
                                                    <div x-show="attendance.lateness_type === 'percentage'">
                                                        <label class="form-label">Deduction Percentage (%)</label>
                                                        <input type="number" name="lateness_deduction_amount" x-model="attendance.lateness_amount" class="form-input" min="0" max="100" step="0.5">
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="form-label">Maximum Deduction Cap (₦)</label>
                                                        <input type="number" name="max_lateness_deduction" x-model="attendance.max_deduction" class="form-input" min="0" step="100" placeholder="No limit if empty">
                                                    </div>
                                                </div>
                                                
                                                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg border border-amber-200 dark:border-amber-700">
                                                    <p class="text-xs text-amber-700 dark:text-amber-300">
                                                        <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                                        Lateness is calculated from <span class="font-bold">Check-In Start + Grace Period</span>. Deductions are automatically applied and shown to employees during check-in.
                                                    </p>
                                                </div>
                                                
                                                <!-- Per-Method Lateness Toggles -->
                                                <div class="mt-4 p-4 bg-white dark:bg-slate-900 rounded-lg border border-amber-200 dark:border-amber-700">
                                                    <h5 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">Apply Lateness Deduction To:</h5>
                                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-amber-400 transition-colors" :class="{'bg-amber-50 border-amber-300': attendance.apply_manual}">
                                                            <input type="checkbox" name="lateness_apply_manual" value="1" x-model="attendance.apply_manual" class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500">
                                                            <span class="font-medium text-slate-700 dark:text-slate-200">Manual Entry</span>
                                                        </label>
                                                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-amber-400 transition-colors" :class="{'bg-amber-50 border-amber-300': attendance.apply_self}">
                                                            <input type="checkbox" name="lateness_apply_self" value="1" x-model="attendance.apply_self" class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500">
                                                            <span class="font-medium text-slate-700 dark:text-slate-200">Self Check-in</span>
                                                        </label>
                                                        <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-amber-400 transition-colors" :class="{'bg-amber-50 border-amber-300': attendance.apply_biometric}">
                                                            <input type="checkbox" name="lateness_apply_biometric" value="1" x-model="attendance.apply_biometric" class="w-4 h-4 text-amber-600 rounded focus:ring-amber-500">
                                                            <span class="font-medium text-slate-700 dark:text-slate-200">Biometric</span>
                                                        </label>
                                                    </div>
                                                    <p class="text-xs text-slate-500 mt-2">
                                                        <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                                        Only checked methods will apply automatic lateness deductions. Leave unchecked for methods with admin-verified times.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden inputs to always submit all fields regardless of conditional panels -->
                                    <input type="hidden" name="check_in_start" :value="attendance.check_in_start">
                                    <input type="hidden" name="check_in_end" :value="attendance.check_in_end">
                                    <input type="hidden" name="check_out_start" :value="attendance.check_out_start">
                                    <input type="hidden" name="check_out_end" :value="attendance.check_out_end">
                                    <input type="hidden" name="grace_period" :value="attendance.grace">
                                    <input type="hidden" name="enable_ip" :value="attendance.ip ? 1 : 0">
                                    <input type="hidden" name="require_supervisor" :value="attendance.supervisor ? 1 : 0">
                                    <input type="hidden" name="lateness_deduction_enabled" :value="attendance.lateness_enabled ? 1 : 0">
                                    <input type="hidden" name="lateness_deduction_type" :value="attendance.lateness_type">
                                    <input type="hidden" name="lateness_deduction_amount" :value="attendance.lateness_amount">
                                    <input type="hidden" name="lateness_per_minute_rate" :value="attendance.per_minute_rate">
                                    <input type="hidden" name="max_lateness_deduction" :value="attendance.max_deduction">
                                    <input type="hidden" name="lateness_apply_manual" :value="attendance.apply_manual ? 1 : 0">
                                    <input type="hidden" name="lateness_apply_self" :value="attendance.apply_self ? 1 : 0">
                                    <input type="hidden" name="lateness_apply_biometric" :value="attendance.apply_biometric ? 1 : 0">
                                    
                                    <div class="mt-8 flex justify-end">
                                        <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white font-bold rounded-lg shadow-lg shadow-brand-500/30 hover:bg-brand-700 transition-all flex items-center gap-2">
                                            <i data-lucide="save" class="w-4 h-4"></i> Save Policy
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 1: PROFILE -->
                        <div x-show="currentTab === 'profile'" x-cloak>
                        <!-- Profile Form -->
                        <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                            <input type="hidden" name="tab" value="profile">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-1 md:col-span-2 flex items-center gap-6 pb-6 border-b border-slate-100 dark:border-slate-800 mb-2">
                                    <div class="w-24 h-24 rounded-xl bg-slate-50 dark:bg-slate-900 border-2 border-dashed border-slate-300 dark:border-slate-700 flex items-center justify-center text-slate-400 overflow-hidden relative group">
                                        <?php if(!empty($company['logo_url'])): ?>
                                            <img src="../uploads/logos/<?php echo htmlspecialchars($company['logo_url']); ?>" class="w-full h-full object-contain">
                                        <?php else: ?>
                                            <i data-lucide="image" class="w-8 h-8"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-900 dark:text-white mb-2">Company Logo</label>
                                        <label class="cursor-pointer inline-flex items-center px-4 py-2 text-sm font-medium text-brand-600 bg-brand-50 hover:bg-brand-100 dark:bg-brand-900/20 dark:hover:bg-brand-900/40 rounded-lg transition-colors border border-brand-200 dark:border-brand-800">
                                            <i data-lucide="upload-cloud" class="w-4 h-4 mr-2"></i> Upload New Logo
                                            <input type="file" name="logo" class="hidden" accept="image/*">
                                        </label>
                                        <p class="text-xs text-slate-500 mt-2">Recommended: 200x200px PNG or JPG</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="form-label mb-2 block font-bold text-sm text-slate-700 dark:text-slate-300">Company Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($company['name']); ?>" class="form-input w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" required>
                                </div>
                                
                                <div>
                                    <label class="form-label mb-2 block font-bold text-sm text-slate-700 dark:text-slate-300">Phone Number</label>
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($company['phone']); ?>" class="form-input w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="form-label mb-2 block font-bold text-sm text-slate-700 dark:text-slate-300">Address</label>
                                    <textarea name="address" rows="3" class="form-input w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div>
                                    <label class="form-label mb-2 block font-bold text-sm text-slate-700 dark:text-slate-300">Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($company['email']); ?>" class="form-input w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" required>
                                </div>
                                
                                <div>
                                    <label class="form-label mb-2 block font-bold text-sm text-slate-700 dark:text-slate-300">Currency</label>
                                    <div class="relative">
                                        <select name="currency" class="form-input w-full px-4 py-3 rounded-lg border border-slate-300 dark:border-slate-700 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all appearance-none bg-white dark:bg-slate-900">
                                            <option value="NGN" <?php echo ($company['currency'] == 'NGN') ? 'selected' : ''; ?>>Nigerian Naira (₦)</option>
                                            <option value="USD" <?php echo ($company['currency'] == 'USD') ? 'selected' : ''; ?>>US Dollar ($)</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                                    </div>
                                </div>
                                <input type="hidden" name="tax_jurisdiction" value="<?php echo htmlspecialchars($company['tax_jurisdiction'] ?? ''); ?>">
                            </div>
                            <!-- Save Button (Fixed Size & Position) -->
                            <div class="mt-8 flex justify-end border-t border-slate-100 dark:border-slate-800 pt-6">
                                <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white text-sm font-bold rounded-lg shadow-sm hover:bg-brand-700 transition-all focus:ring-2 focus:ring-brand-500/20">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB 2: DEPARTMENTS (NEW) -->
                    <div x-show="currentTab === 'departments'" x-cloak>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <!-- Left: List -->
                            <div class="lg:col-span-2">
                                <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                        <h3 class="font-bold text-slate-900 dark:text-white">All Departments</h3>
                                        <span class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-500" x-text="departments.length + ' Total'"></span>
                                    </div>
                                    
                                    <template x-if="departments.length === 0">
                                        <div class="p-8 text-center text-slate-500 dark:text-slate-400">
                                            <i data-lucide="users" class="w-12 h-12 mx-auto mb-3 text-slate-300 dark:text-slate-700"></i>
                                            <p>No departments found. Create one to get started.</p>
                                        </div>
                                    </template>

                                    <table class="w-full text-left border-collapse" x-show="departments.length > 0">
                                        <thead>
                                            <tr class="border-b border-slate-100 dark:border-slate-800 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50">
                                                <th class="px-6 py-3 font-medium">Name</th>
                                                <th class="px-6 py-3 font-medium">Code</th>
                                                <th class="px-6 py-3 font-medium text-center">Employees</th>
                                                <th class="px-6 py-3 font-medium text-center">Status</th>
                                                <th class="px-6 py-3 font-medium text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <template x-for="dept in departments" :key="dept.id">
                                                <tr class="group hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                                    <td class="px-6 py-4">
                                                        <span class="font-medium text-slate-900 dark:text-white" x-text="dept.name"></span>
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                                        <span x-text="dept.code || '-'"></span>
                                                    </td>
                                                    <td class="px-6 py-4 text-center">
                                                        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200" x-text="dept.employee_count"></span>
                                                    </td>
                                                    <td class="px-6 py-4 text-center">
                                                        <button @click="toggleDepartment(dept.id)" 
                                                            :class="dept.active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                                                            class="px-2 py-1 rounded text-xs font-medium transition-colors hover:opacity-80">
                                                            <span x-text="dept.active ? 'Active' : 'Inactive'"></span>
                                                        </button>
                                                    </td>
                                                    <td class="px-6 py-4 text-right flex items-center justify-end gap-2">
                                                        <button @click="editDepartment(dept)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-brand-50 hover:text-brand-600 transition-colors text-xs font-bold">
                                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i> Edit
                                                        </button>
                                                        <button @click="deleteDepartment(dept.id, dept.employee_count)" 
                                                            :class="dept.employee_count > 0 ? 'opacity-50 cursor-not-allowed bg-slate-50 text-slate-400' : 'bg-slate-100 text-slate-600 hover:bg-red-50 hover:text-red-600 dark:bg-slate-800 dark:text-slate-300'" 
                                                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-md transition-colors text-xs font-bold"
                                                            :title="dept.employee_count > 0 ? 'Cannot delete: Has employees' : 'Delete Department'">
                                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Right: Form -->
                            <div class="lg:col-span-1">
                                <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 sticky top-6">
                                    <div class="flex items-center justify-between mb-6">
                                        <h3 class="font-bold text-slate-900 dark:text-white" x-text="deptForm.id ? 'Edit Department' : 'New Department'"></h3>
                                        <button x-show="deptForm.id" @click="resetDeptForm()" class="text-xs text-brand-600 hover:underline">Cancel</button>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="form-label">Department Name</label>
                                            <input type="text" x-model="deptForm.name" @input="updateDeptInput()" class="form-input" placeholder="e.g. Engineering">
                                        </div>
                                        <div>
                                            <label class="form-label">Code (Optional)</label>
                                            <input type="text" x-model="deptForm.code" class="form-input uppercase" placeholder="e.g. ENG">
                                        </div>
                                        <div class="flex items-center justify-between pt-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Active Status</label>
                                            <button @click="deptForm.active = !deptForm.active" :class="deptForm.active ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700'" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                                                <span :class="deptForm.active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                            </button>
                                        </div>
                                        
                                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                            <button @click="saveDepartment()" :disabled="!deptForm.name" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                                <span x-text="deptForm.id ? 'Update Department' : 'Create Department'"></span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Help Text -->
                                    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <div class="flex gap-2">
                                            <i data-lucide="info" class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5"></i>
                                            <p class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed">
                                                Departments segregate employees for reporting and payroll cost allocation. Every employee must belong to one department.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: PAYROLL ITEMS (NEW) -->
                    <div x-show="currentTab === 'items'" x-cloak>
                        <!-- Sub Tabs -->
                        <div class="flex items-center gap-4 mb-6">
                            <button @click="itemTab = 'bonus'" :class="itemTab === 'bonus' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'" class="px-4 py-2 rounded-lg text-sm font-bold transition-colors">Bonuses</button>
                            <button @click="itemTab = 'deduction'" :class="itemTab === 'deduction' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'" class="px-4 py-2 rounded-lg text-sm font-bold transition-colors">Deductions</button>
                            <div class="flex-1"></div>
                            <button @click="openItemDrawer(itemTab)" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                                <i data-lucide="plus" class="w-4 h-4"></i> <span x-text="itemTab === 'bonus' ? 'Add Bonus' : 'Add Deduction'"></span>
                            </button>
                        </div>

                        <!-- List -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <template x-for="item in filteredItems" :key="item.id">
                                <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-5 group hover:border-brand-500/50 transition-colors">
                                    <div class="flex flex-wrap justify-between items-start mb-4 gap-y-2">
                                        <div class="flex items-center gap-3">
                                            <div :class="item.type === 'bonus' ? 'bg-green-50 text-green-600 dark:bg-green-900/20' : 'bg-red-50 text-red-600 dark:bg-red-900/20'" class="w-10 h-10 rounded-lg flex items-center justify-center">
                                                <i :data-lucide="item.type === 'bonus' ? 'trending-up' : 'trending-down'" class="w-5 h-5"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-900 dark:text-white" x-text="item.name"></h4>
                                                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="item.method === 'fixed' ? 'Fixed Amount' : 'Percentage Based'"></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button @click="openItemDrawer(item.type, item)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-brand-50 hover:text-brand-600 transition-colors text-xs font-bold">
                                                <i data-lucide="edit-2" class="w-3.5 h-3.5"></i> Edit
                                            </button>
                                            <button @click="deleteItem(item.id, item.type)" class="flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-red-50 hover:text-red-600 transition-colors text-xs font-bold">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="text-2xl font-bold text-slate-900 dark:text-white">
                                            <span x-show="item.method === 'fixed'" x-text="'₦' + Number(item.amount).toLocaleString()"></span>
                                            <span x-show="item.method === 'percentage'" x-text="item.percentage + '%'"></span>
                                        </div>
                                        <div x-show="item.method === 'percentage'" class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            of <span class="capitalize" x-text="item.base + ' Salary'"></span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span x-show="item.taxable" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">Taxable</span>
                                        <span x-show="item.pensionable" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">Pensionable</span>
                                        <span x-show="item.recurring" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-300">Recurring</span>
                                        <button @click="toggleItem(item.id)" :class="item.active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'" class="ml-auto px-2 py-0.5 rounded text-[10px] font-bold uppercase" x-text="item.active ? 'Active' : 'Inactive'"></button>
                                    </div>
                                </div>
                            </template>
                            
                            <!-- Empty State -->
                            <div x-show="filteredItems.length === 0" class="col-span-full py-12 text-center border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                                <i data-lucide="inbox" class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-700 mb-3"></i>
                                <p class="text-slate-500 dark:text-slate-400">No items configured yet.</p>
                                <button @click="openItemDrawer(itemTab)" class="mt-4 text-sm font-medium text-brand-600 hover:underline">Create First Item</button>
                            </div>
                        </div>

                        <!-- DRAWER (Slide Over) -->
                        <div x-show="itemDrawerOpen" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="itemDrawerOpen = false" x-transition.opacity></div>
                            <div class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
                                <div class="w-screen max-w-md transform transition-transform bg-white dark:bg-slate-950 shadow-xl border-l border-slate-200 dark:border-slate-800 flex flex-col h-full" x-transition:enter="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="translate-x-0" x-transition:leave-end="translate-x-full">
                                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                        <h2 class="text-lg font-bold text-slate-900 dark:text-white" x-text="itemForm.id ? 'Edit Item' : 'New Item'"></h2>
                                        <button @click="itemDrawerOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-6 h-6"></i></button>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                                        <!-- Form -->
                                        <div class="relative">
                                            <label class="form-label">Item Name</label>
                                            <!-- Standard Locked or Custom -->
                                            <div class="relative">
                                                <input type="text" x-model="itemSearch" class="form-input pr-10" placeholder="Search Master List..."
                                                    :disabled="itemForm.id && !itemForm.custom"
                                                    @input="itemForm.name = ''; itemForm.custom = 0;">
                                                
                                                <!-- Icons: Search or Lock -->
                                                <div class="absolute right-3 top-3 text-slate-400">
                                                    <i x-show="itemForm.id && !itemForm.custom" data-lucide="lock" class="w-4 h-4"></i>
                                                    <i x-show="!itemForm.id || itemForm.custom" data-lucide="search" class="w-4 h-4"></i>
                                                </div>

                                                <!-- Autocomplete Dropdown -->
                                                <div x-show="itemSearch && !itemForm.name && masterList.length > 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                    <template x-for="mItem in masterList">
                                                        <button @click="selectMasterItem(mItem)" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                            <span x-text="mItem"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                                
                                                <!-- No Match -> Add Custom -->
                                                <div x-show="itemSearch && !itemForm.name && masterList.length === 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg p-3 text-center">
                                                    <p class="text-xs text-slate-500 mb-2">No standard item found.</p>
                                                    <button @click="addCustomItem()" class="px-3 py-1.5 bg-brand-50 text-brand-600 rounded text-xs font-bold hover:bg-brand-100 transition-colors">
                                                        Add "<span x-text="itemSearch"></span>" as Custom
                                                    </button>
                                                </div>
                                            </div>
                                            <p x-show="itemForm.custom" class="text-[10px] text-amber-600 dark:text-amber-400 font-bold mt-1 uppercase">Custom Item</p>
                                            <p x-show="!itemForm.custom && itemForm.name" class="text-[10px] text-green-600 dark:text-green-400 font-bold mt-1 uppercase">Standard Item</p>
                                        </div>
                                        
                                        <!-- Method Selection -->
                                        <div>
                                            <label class="form-label">Calculation Method</label>
                                            <div class="grid grid-cols-2 gap-4">
                                                <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors" :class="itemForm.method === 'fixed' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-slate-200 dark:border-slate-800'">
                                                    <input type="radio" x-model="itemForm.method" value="fixed" class="text-brand-600">
                                                    <span class="text-sm font-medium">Fixed Amount</span>
                                                </label>
                                                <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors" :class="itemForm.method === 'percentage' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-slate-200 dark:border-slate-800'">
                                                    <input type="radio" x-model="itemForm.method" value="percentage" class="text-brand-600">
                                                    <span class="text-sm font-medium">Percentage</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Dynamic Inputs -->
                                        <div x-show="itemForm.method === 'fixed'" x-transition>
                                            <label class="form-label">Amount (₦)</label>
                                            <input type="number" step="0.01" x-model="itemForm.amount" class="form-input">
                                        </div>
                                        
                                        <div x-show="itemForm.method === 'percentage'" x-transition class="space-y-4">
                                            <div>
                                                <label class="form-label">Percentage (%)</label>
                                                <input type="number" step="0.01" x-model="itemForm.percentage" class="form-input">
                                            </div>
                                            <div>
                                                <label class="form-label">Percentage Base</label>
                                                <select x-model="itemForm.base" class="form-input">
                                                    <option value="basic">Basic Salary</option>
                                                    <option value="housing">Housing Allowance</option>
                                                    <option value="transport">Transport Allowance</option>
                                                    <option value="gross">Gross Salary</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Toggles -->
                                        <div class="space-y-3 pt-2">
                                            <label class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Taxable</span>
                                                <input type="checkbox" x-model="itemForm.taxable" class="rounded text-brand-600 bg-slate-100 border-none w-5 h-5">
                                            </label>
                                            <label class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Pensionable</span>
                                                <input type="checkbox" x-model="itemForm.pensionable" class="rounded text-brand-600 bg-slate-100 border-none w-5 h-5">
                                            </label>
                                            <label class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Recurring</span>
                                                <input type="checkbox" x-model="itemForm.recurring" class="rounded text-brand-600 bg-slate-100 border-none w-5 h-5">
                                            </label>
                                        </div>
                                        
                                        <!-- Live Preview Box -->
                                        <div class="p-4 rounded-lg bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                            <p class="text-xs font-bold text-slate-500 uppercase mb-2">Live Preview (Sample Salary: ₦500k)</p>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-slate-600 dark:text-slate-400">Calculated Value:</span>
                                                <span class="text-xl font-bold text-slate-900 dark:text-white" x-text="'₦' + Number(calculatePreview(itemForm)).toLocaleString()"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-6 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                                        <button @click="saveItem()" class="w-full py-3 px-4 bg-brand-600 text-white rounded-lg font-bold shadow-lg hover:bg-brand-700 transition-colors">Save Item</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: SALARY COMPONENTS (REBUILT - NO AUTO-SAVE) -->
                    <div x-show="currentTab === 'components'" x-cloak x-transition.opacity>
                        <!-- Warning Banner -->
                        <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/30 rounded-lg p-4 mb-6 flex items-start gap-3">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-bold text-amber-800 dark:text-amber-400">Salary Structure Rules</p>
                                <p class="text-xs text-amber-700 dark:text-amber-500">Components must total exactly 100% for payroll accuracy. Changes apply to future payroll runs.</p>
                            </div>
                        </div>

                        <!-- Main Card -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800">
                            <!-- Header -->
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-white">Salary Structure</h3>
                                    <p class="text-xs text-slate-500 mt-1">Define default salary breakdown (must total exactly 100%)</p>
                                </div>
                                <button @click="openCompDrawer()" class="px-4 py-2 bg-brand-600 text-white text-sm font-bold rounded-lg hover:bg-brand-700 transition-colors flex items-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Add Allowance
                                </button>
                            </div>

                            <!-- 100% Validation Panel -->
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-10">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Total Allocated:</span>
                                    <span class="text-2xl font-bold" 
                                          :class="canSave ? 'text-green-600' : 'text-red-600'"
                                          x-text="totalAllocated.toFixed(2) + '%'"></span>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="relative h-4 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden mb-2">
                                    <div class="absolute h-full transition-all duration-300"
                                         :class="canSave ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-red-500 to-red-600'"
                                         :style="'width: ' + Math.min(totalAllocated, 100) + '%'"></div>
                                </div>
                                
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-600 dark:text-slate-400">
                                        Remaining: <strong x-text="totalRemaining.toFixed(2) + '%'"></strong>
                                    </span>
                                    <span x-show="!canSave" class="text-red-600 dark:text-red-400 font-medium flex items-center gap-1">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i> Must equal exactly 100%
                                    </span>
                                    <span x-show="canSave" class="text-green-600 dark:text-green-400 font-medium flex items-center gap-1">
                                        <i data-lucide="check-circle" class="w-3 h-3"></i> Valid structure
                                    </span>
                                </div>
                            </div>

                            <div class="p-6 space-y-8">
                                <!-- SECTION 1: SYSTEM LOCKED COMPONENTS -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                        <i data-lucide="lock" class="w-3 h-3"></i> System Structure (Mandatory)
                                    </h4>
                                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                        <table class="w-full text-sm">
                                            <thead class="bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Component</th>
                                                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Percentage</th>
                                                    <th class="px-4 py-3 text-center font-semibold text-slate-600 dark:text-slate-300">Settings</th>
                                                    <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                <template x-for="comp in salaryComponents.filter(c => c.system)" :key="comp.id">
                                                    <tr class="hover:bg-slate-100 dark:hover:bg-slate-800/50 transition-colors">
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center gap-2">
                                                                <span class="font-bold text-slate-800 dark:text-slate-200" x-text="comp.name"></span>
                                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">SYSTEM</span>
                                                            </div>
                                                            <div class="text-xs text-slate-400 mt-0.5">Basic Type: <span x-text="comp.base"></span></div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center gap-2">
                                                                <input type="number" step="0.01" min="0" max="100" 
                                                                    x-model.number="comp.percentage"
                                                                    class="w-20 px-2 py-1.5 text-sm font-bold text-right border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                                                                <span class="font-bold text-slate-500">%</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <div class="flex justify-center gap-2">
                                                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400" title="Taxable">Tax</span>
                                                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400" title="Pensionable">Pen</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-right">
                                                            <span class="text-xs font-bold text-slate-400 flex items-center justify-end gap-1">
                                                                <i data-lucide="shield" class="w-3 h-3"></i> Locked
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- SECTION 2: ADDITIONAL ALLOWANCES -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                                        <i data-lucide="layers" class="w-3 h-3"></i> Additional Allowances
                                    </h4>
                                    <div class="bg-white dark:bg-slate-950 rounded-lg border border-slate-200 dark:border-slate-800 overflow-hidden">
                                        <table class="w-full text-sm">
                                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Component</th>
                                                    <th class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400">Value</th>
                                                    <th class="px-4 py-3 text-center font-medium text-slate-500 dark:text-slate-400">Options</th>
                                                    <th class="px-4 py-3 text-center font-medium text-slate-500 dark:text-slate-400">Active</th>
                                                    <th class="px-4 py-3 text-right font-medium text-slate-500 dark:text-slate-400">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                <template x-for="comp in salaryComponents.filter(c => !c.system)" :key="comp.id">
                                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                                        <td class="px-4 py-3">
                                                            <div class="font-medium text-slate-900 dark:text-white" x-text="comp.name"></div>
                                                            <div class="text-xs text-slate-500" x-text="comp.method"></div>
                                                        </td>
                                                        <td class="px-4 py-3">
                                                            <div class="flex items-center gap-1">
                                                                <input x-show="comp.method === 'percentage'" 
                                                                       type="number" step="0.01" min="0" max="100"
                                                                       x-model.number="comp.percentage"
                                                                       class="w-20 px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white dark:bg-slate-800">
                                                                <span x-show="comp.method === 'percentage'" class="text-slate-400 text-xs">%</span>
                                                                       
                                                                <input x-show="comp.method === 'fixed'" 
                                                                       type="number" step="0.01" min="0"
                                                                       x-model.number="comp.amount"
                                                                       class="w-24 px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white dark:bg-slate-800">
                                                                <span x-show="comp.method === 'fixed'" class="text-slate-400 text-xs">₦</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <div class="flex justify-center gap-3">
                                                                <label class="flex items-center gap-1 text-xs cursor-pointer" title="Taxable">
                                                                    <input type="checkbox" x-model="comp.taxable" class="rounded text-brand-600 focus:ring-0 w-3.5 h-3.5"> Tax
                                                                </label>
                                                                <label class="flex items-center gap-1 text-xs cursor-pointer" title="Pensionable">
                                                                    <input type="checkbox" x-model="comp.pensionable" class="rounded text-brand-600 focus:ring-0 w-3.5 h-3.5"> Pen
                                                                </label>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                <input type="checkbox" x-model="comp.active" class="sr-only peer">
                                                                <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500 dark:peer-checked:bg-green-600"></div>
                                                            </label>
                                                        </td>
                                                        <td class="px-4 py-3 text-right">
                                                            <button @click="deleteComponent(comp.id)" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors" title="Delete Allowance">
                                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <tr x-show="salaryComponents.filter(c => !c.system).length === 0">
                                                    <td colspan="5" class="py-6 text-center text-slate-500 text-xs italic">
                                                        No additional allowances added.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Button -->
                            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 flex justify-end gap-3 sticky bottom-0 z-10">
                                <button type="button" @click="saveAllComponents()" 
                                        :disabled="!canSave || componentsSaving"
                                        :class="canSave && !componentsSaving ? 'bg-brand-600 hover:bg-brand-700 shadow-brand-500/20' : 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed text-slate-100'"
                                        class="px-6 py-2.5 text-white rounded-lg font-bold transition-all shadow-lg flex items-center gap-2">
                                    <svg x-show="componentsSaving" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <i x-show="!componentsSaving && canSave" data-lucide="save" class="w-4 h-4"></i>
                                    <span x-show="!componentsSaving">Save Structure</span>
                                    <span x-show="componentsSaving">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                        <!-- COMPONENT DRAWER -->
                        <div x-show="compDrawerOpen" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="compDrawerOpen = false" x-transition.opacity></div>
                            <div class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
                                <div class="w-screen max-w-md transform transition-transform bg-white dark:bg-slate-950 shadow-xl border-l border-slate-200 dark:border-slate-800 flex flex-col h-full" x-transition:enter="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="translate-x-0" x-transition:leave-end="translate-x-full">
                                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                        <h2 class="text-lg font-bold text-slate-900 dark:text-white" x-text="newComponent.id ? 'Edit Allowance' : 'New Allowance'"></h2>
                                        <button @click="compDrawerOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-6 h-6"></i></button>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                                        <!-- Form Content Copied from Previous Step -->
                                        <div class="relative">
                                        <label class="form-label">Allowance Name</label>
                                        <!-- Master List Combobox -->
                                        <div class="relative">
                                            <input type="text" x-model="compSearch" class="form-input pr-10" placeholder="Search Master List..."
                                                @input="newComponent.name = ''; newComponent.custom = 0;">
                                            
                                            <!-- Icons -->
                                            <div class="absolute right-3 top-3 text-slate-400">
                                                <i x-show="newComponent.name && !newComponent.custom" data-lucide="lock" class="w-4 h-4"></i>
                                                <i x-show="!newComponent.name || newComponent.custom" data-lucide="search" class="w-4 h-4"></i>
                                            </div>

                                            <!-- Dropdown -->
                                            <div x-show="compSearch && !newComponent.name && masterAllowanceList.length > 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                <template x-for="mItem in masterAllowanceList">
                                                    <button @click="selectMasterAllowance(mItem)" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                        <span x-text="mItem"></span>
                                                    </button>
                                                </template>
                                            </div>
                                            
                                            <!-- Custom Option -->
                                            <div x-show="compSearch && !newComponent.name && masterAllowanceList.length === 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg p-3 text-center">
                                                <p class="text-xs text-slate-500 mb-2">No standard allowance found.</p>
                                                <button @click="addCustomAllowance()" class="px-3 py-1.5 bg-brand-50 text-brand-600 rounded text-xs font-bold hover:bg-brand-100 transition-colors">
                                                    Add "<span x-text="compSearch"></span>" as Custom
                                                </button>
                                            </div>
                                        </div>
                                        <p x-show="newComponent.custom" class="text-[10px] text-amber-600 dark:text-amber-400 font-bold mt-1 uppercase">Custom Allowance</p>
                                        <p x-show="!newComponent.custom && newComponent.name" class="text-[10px] text-green-600 dark:text-green-400 font-bold mt-1 uppercase">Standard Allowance</p>
                                    </div>
                                    <input type="text" x-model="newComponent.name" class="hidden"> <!-- hidden binding -->
                                    
                                    <!-- Calculation Method -->
                                    <div>
                                        <label class="form-label mb-2">Calculation Method</label>
                                        <div class="grid grid-cols-2 gap-4">
                                            <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors" :class="newComponent.method === 'fixed' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-slate-200 dark:border-slate-800'">
                                                <input type="radio" x-model="newComponent.method" value="fixed" class="text-brand-600">
                                                <span class="text-sm font-medium">Fixed Amount</span>
                                            </label>
                                            <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors" :class="newComponent.method === 'percentage' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-slate-200 dark:border-slate-800'">
                                                <input type="radio" x-model="newComponent.method" value="percentage" class="text-brand-600">
                                                <span class="text-sm font-medium">Percentage</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Fixed Amount Input -->
                                    <div x-show="newComponent.method === 'fixed'">
                                        <label class="form-label">Amount</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₦</span>
                                            <input type="number" x-model="newComponent.amount" placeholder="0.00" class="form-input pl-8" min="0" step="0.01">
                                        </div>
                                    </div>

                                    <!-- Percentage Inputs -->
                                    <div x-show="newComponent.method === 'percentage'" class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="form-label">Percentage (%)</label>
                                            <input type="number" x-model="newComponent.percentage" placeholder="0" class="form-input" min="0" max="100" step="0.1">
                                        </div>
                                        <div>
                                            <label class="form-label">Of Base</label>
                                            <select x-model="newComponent.base" class="form-select">
                                                <option value="basic">Basic Salary</option>
                                                <option value="gross">Gross Salary</option>
                                                <!-- Other Components -->
                                                <optgroup label="Other Allowances" x-show="otherComponents.length > 0">
                                                    <template x-for="comp in otherComponents" :key="comp.id">
                                                        <option :value="comp.id" x-text="comp.name"></option>
                                                    </template>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>

                                    <div x-show="newComponent.method === 'percentage'" class="mt-4 p-3 rounded-lg border text-sm transition-colors"
                                         :class="(newComponent.base === 'gross' && totalStructPercentage > 100) ? 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/10 dark:border-red-800 dark:text-red-400' : 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-900/10 dark:border-blue-800 dark:text-blue-400'">
                                        
                                        <!-- Gross Base Validation -->
                                        <div x-show="newComponent.base === 'gross'">
                                            <div class="flex justify-between font-bold mb-1">
                                                <span>Total Allocated: <span x-text="totalStructPercentage + '%'"></span></span>
                                                <span>Remaining: <span x-text="(100 - totalStructPercentage).toFixed(2) + '%'"></span></span>
                                            </div>
                                            <div class="w-full bg-white dark:bg-slate-900 h-2 rounded-full overflow-hidden mb-2">
                                                <div class="h-full transition-all duration-300" 
                                                     :class="totalStructPercentage > 100 ? 'bg-red-500' : 'bg-blue-500'" 
                                                     :style="'width: ' + Math.min(totalStructPercentage, 100) + '%'"></div>
                                            </div>
                                            <p x-show="totalStructPercentage > 100" class="flex items-start gap-2 font-medium">
                                                <svg class="w-4 h-4 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                                <span>Limit Exceeded! Total salary structure must equal 100%. Please reduce Basic or other allowances.</span>
                                            </p>
                                            <p x-show="totalStructPercentage <= 100">
                                                <svg class="w-3 h-3 inline mr-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                                Target 100% distribution for accurate breakdown.
                                            </p>
                                        </div>

                                        <!-- Non-Gross Base Notice -->
                                        <div x-show="newComponent.base !== 'gross'" class="flex items-start gap-2">
                                            <svg class="w-4 h-4 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                            <span>Values based on <b>Basic Salary</b> or other components do not count towards the Global 100% Structure Cap.</span>
                                        </div>
                                    </div>

                                    <div class="space-y-3 pt-4">
                                        <label class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-help" title="Calculate PAYE on this?">Taxable</span>
                                            <input type="checkbox" x-model="newComponent.taxable" class="rounded text-brand-600 bg-slate-100 border-none w-5 h-5">
                                        </label>
                                        <label class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300 cursor-help" title="Deduct Pension from this?">Pensionable</span>
                                            <input type="checkbox" x-model="newComponent.pensionable" class="rounded text-brand-600 bg-slate-100 border-none w-5 h-5">
                                        </label>
                                    </div>
                                    </div>
                                    <div class="p-6 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                                        <button @click="addComponent()" :disabled="newComponent.method === 'percentage' && newComponent.base === 'gross' && totalStructPercentage > 100" :class="{'opacity-50 cursor-not-allowed': newComponent.method === 'percentage' && newComponent.base === 'gross' && totalStructPercentage > 100}" class="w-full py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-lg hover:opacity-90 transition-opacity shadow-lg">
                                            Save & Add Allowance
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>

                    <!-- TAB 5: CATEGORIES -->
                    <div x-show="currentTab === 'categories'" x-cloak x-init="$nextTick(() => lucide.createIcons())">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <!-- Left: List (2/3) -->
                            <div class="lg:col-span-2">
                                <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                        <h3 class="font-bold text-slate-900 dark:text-white">Existing Categories</h3>
                                        <span class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-500" x-text="categories.length + ' Total'"></span>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr class="border-b border-slate-100 dark:border-slate-800 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50">
                                                    <th class="px-6 py-3 font-medium">Category Name</th>
                                                    <th class="px-6 py-3 font-medium">Gross Salary</th>
                                                    <th class="px-6 py-3 font-medium">Breakdown Split (%)</th>
                                                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                <template x-for="cat in categories" :key="cat.id">
                                                    <tr class="group hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                                        <td class="px-6 py-4">
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-2 h-2 rounded-full" :class="cat.active ? 'bg-green-500 shadow-sm shadow-green-500/50' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                                                <span class="font-medium text-slate-900 dark:text-white" x-text="cat.title"></span>
                                                                <span x-show="!cat.active" class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 ml-2">Suspended</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300" x-text="'₦' + Number(cat.amount).toLocaleString()"></span>
                                                        </td>
                                                        <td class="px-6 py-4">
                                                            <div class="flex flex-col gap-1">
                                                                <div class="flex gap-2">
                                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 dark:bg-blue-900/20" x-text="'B: ' + cat.struct.basic + '%'"></span>
                                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20" x-text="'H: ' + cat.struct.housing + '%'"></span>
                                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-purple-50 text-purple-600 dark:bg-purple-900/20" x-text="'T: ' + cat.struct.transport + '%'"></span>
                                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-50 text-slate-600 dark:bg-slate-800" x-text="'O: ' + cat.struct.other + '%'"></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 text-right">
                                                            <div class="flex justify-end gap-2">
                                                                <button @click="toggleCategory(cat.id)" class="p-1.5 rounded-md transition-colors text-black dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800" :title="cat.active ? 'Suspend Category' : 'Activate Category'">
                                                                    <i :data-lucide="cat.active ? 'pause-circle' : 'play-circle'" class="w-4 h-4"></i>
                                                                </button>
                                                                <button @click="editCategory(cat)" class="p-1.5 text-black dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors" title="Edit Category">
                                                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                                                </button>
                                                                <button @click="prepareDelete(cat.id)" class="p-1.5 text-black dark:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded-md transition-colors" title="Delete Category">
                                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <template x-if="categories.length === 0">
                                        <div class="p-12 text-center text-slate-500 dark:text-slate-400">
                                            <i data-lucide="list" class="w-12 h-12 mx-auto mb-3 text-slate-300 dark:text-slate-700"></i>
                                            <p>No categories defined yet.</p>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Right: Form (1/3) -->
                            <div class="lg:col-span-1">
                                <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 sticky top-6">
                                    <div class="flex items-center justify-between mb-6">
                                        <h3 class="font-bold text-slate-900 dark:text-white" x-text="editMode ? 'Edit Category' : 'New Category'"></h3>
                                        <button x-show="editMode" @click="resetCatForm()" class="text-xs text-brand-600 hover:underline">Cancel</button>
                                    </div>
                                    
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="tab" value="category_save">
                                        <input type="hidden" name="cat_id" x-model="catForm.id">
                                        
                                        <div>
                                            <label class="form-label">Category Title</label>
                                            <input type="text" name="cat_name" x-model="catForm.title" @input="updateCategoryTitle()" placeholder="e.g. Senior Associate" class="form-input border" required>
                                        </div>
                                        
                                        <div>
                                            <label class="form-label">Gross Annual Salary (₦)</label>
                                            <input type="number" name="cat_gross" x-model="catForm.amount" @blur="checkBreakdownNotification()" class="form-input border" required>
                                        </div>

                                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Breakdown Split (%)</h4>
                                            <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                                                <div>
                                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Basic</label>
                                                    <input type="number" name="cat_basic" x-model.number="catForm.basic" class="form-input text-sm border">
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Housing</label>
                                                    <input type="number" name="cat_housing" x-model.number="catForm.housing" class="form-input text-sm border">
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Transport</label>
                                                    <input type="number" name="cat_transport" x-model.number="catForm.transport" class="form-input text-sm border">
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Other</label>
                                                    <input type="number" name="cat_other" x-model.number="catForm.other" class="form-input text-sm border">
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                                                <div class="flex justify-between items-center text-xs font-bold">
                                                    <span class="text-slate-500">Total Percentage:</span>
                                                    <span :class="(Number(catForm.basic || 0) + Number(catForm.housing || 0) + Number(catForm.transport || 0) + Number(catForm.other || 0)) === 100 ? 'text-green-600' : 'text-red-500'" 
                                                          x-text="(Number(catForm.basic || 0) + Number(catForm.housing || 0) + Number(catForm.transport || 0) + Number(catForm.other || 0)) + '%'"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pt-4">
                                            <button type="submit" 
                                                :disabled="(Number(catForm.basic || 0) + Number(catForm.housing || 0) + Number(catForm.transport || 0) + Number(catForm.other || 0)) !== 100" 
                                                class="w-full py-2.5 px-4 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-bold shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                                Save Category
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Preview Info -->
                                    <div class="mt-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
                                        <div class="flex gap-2">
                                            <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
                                            <p class="text-xs text-indigo-700 dark:text-indigo-300 leading-relaxed">
                                                Categories define the baseline pay structure. The total percentage split must equal exactly 100%.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 6: STATUTORY -->
                    <div x-show="currentTab === 'statutory'" x-cloak>
                        <div class="max-w-4xl mx-auto">
                            <div class="bg-white dark:bg-slate-950 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                                <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-800">
                                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Statutory Deductions</h2>
                                    <p class="text-sm text-slate-500 mt-1">Configure mandatory government contributions and tax rules.</p>
                                </div>
                                
                                <form method="POST" class="p-8">
                                    <input type="hidden" name="tab" value="statutory">
                                    
                                    <div class="space-y-6">
                                        <!-- PAYE Tax -->
                                        <div class="flex items-center justify-between p-5 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                            <div class="flex items-center gap-5">
                                                <div class="w-12 h-12 flex items-center justify-center bg-green-100 dark:bg-green-900/30 rounded-xl text-green-600 dark:text-green-400 group-hover:scale-110 transition-transform">
                                                    <i data-lucide="banknote" class="w-6 h-6"></i>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-900 dark:text-white">PAYE Tax (Income Tax)</p>
                                                    <p class="text-xs text-slate-500">Enable automatic calculation of PAYE based on tax tables.</p>
                                                </div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="enable_paye_dummy" x-model="statutory.paye" class="sr-only peer">
                                                <input type="hidden" name="enable_paye" :value="statutory.paye ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>

                                        <!-- Pension -->
                                        <div class="p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                            <div class="flex items-center justify-between mb-6">
                                                <div class="flex items-center gap-5">
                                                    <div class="w-12 h-12 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-xl text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                                                        <i data-lucide="piggy-bank" class="w-6 h-6"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-slate-900 dark:text-white">Pension Contribution</p>
                                                        <p class="text-xs text-slate-500">Enable mandatory 18% (8% Employee / 10% Employer) splits.</p>
                                                    </div>
                                                </div>
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" name="enable_pension_dummy" x-model="statutory.pension" class="sr-only peer">
                                                    <input type="hidden" name="enable_pension" :value="statutory.pension ? 'on' : ''">
                                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                                </label>
                                            </div>
                                            <div x-show="statutory.pension" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-2 gap-6 pl-16">
                                                <div class="space-y-1.5">
                                                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Employer Contribution (%)</label>
                                                    <div class="relative">
                                                        <input type="number" name="pension_employer_perc" x-model="statutory.pension_employer" class="form-input pl-4 pr-10 py-2.5">
                                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">%</span>
                                                    </div>
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Employee Contribution (%)</label>
                                                    <div class="relative">
                                                        <input type="number" name="pension_employee_perc" x-model="statutory.pension_employee" class="form-input pl-4 pr-10 py-2.5">
                                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- NHIS -->
                                        <div class="flex items-center justify-between p-5 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                            <div class="flex items-center gap-5">
                                                <div class="w-12 h-12 flex items-center justify-center bg-rose-100 dark:bg-rose-900/30 rounded-xl text-rose-600 dark:text-rose-400 group-hover:scale-110 transition-transform">
                                                    <i data-lucide="heart-pulse" class="w-6 h-6"></i>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-900 dark:text-white">NHIS (Health Insurance)</p>
                                                    <p class="text-xs text-slate-500">Apply National Health Insurance Scheme deductions.</p>
                                                </div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="enable_nhis_dummy" x-model="statutory.nhis" class="sr-only peer">
                                                <input type="hidden" name="enable_nhis" :value="statutory.nhis ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>

                                        <!-- NHF -->
                                        <div class="flex items-center justify-between p-5 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                            <div class="flex items-center gap-5">
                                                <div class="w-12 h-12 flex items-center justify-center bg-amber-100 dark:bg-amber-900/30 rounded-xl text-amber-600 dark:text-amber-400 group-hover:scale-110 transition-transform">
                                                    <i data-lucide="home" class="w-6 h-6"></i>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-900 dark:text-white">NHF (Housing Fund)</p>
                                                    <p class="text-xs text-slate-500">Apply National Housing Fund contributions (2.5% of Basic).</p>
                                                </div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="enable_nhf_dummy" x-model="statutory.nhf" class="sr-only peer">
                                                <input type="hidden" name="enable_nhf" :value="statutory.nhf ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mt-10 pt-6 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                                        <button type="submit" class="px-8 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold shadow-lg shadow-brand-500/25 transition-all flex items-center gap-2">
                                            <i data-lucide="save" class="w-4 h-4"></i> Save Statutory Config
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 7: BEHAVIOUR -->
                    <div x-show="currentTab === 'behaviour'" x-cloak>
                        <div class="max-w-6xl mx-auto">
                            <div class="mb-8">
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Payroll Behaviour & Rules</h2>
                                <p class="text-sm text-slate-500 mt-1">Configure global rules and automation for payroll processing.</p>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="tab" value="behaviour">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    
                                    <!-- Proration -->
                                    <div class="p-6 bg-white dark:bg-slate-950 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-12 h-12 flex items-center justify-center bg-indigo-100 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                                                <i data-lucide="calendar-clock" class="w-6 h-6"></i>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="prorate_new_hires_dummy" x-model="behaviour.prorate" class="sr-only peer">
                                                <input type="hidden" name="prorate_new_hires" :value="behaviour.prorate ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>
                                        <h3 class="font-bold text-slate-900 dark:text-white mb-1">Prorate New Hires</h3>
                                        <p class="text-xs text-slate-500 leading-relaxed">Automatically calculate partial salary for employees joining mid-month based on their start date.</p>
                                    </div>

                                    <!-- Payslip Email -->
                                    <div class="p-6 bg-white dark:bg-slate-950 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-12 h-12 flex items-center justify-center bg-blue-100 dark:bg-blue-900/30 rounded-xl text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                                                <i data-lucide="mail-check" class="w-6 h-6"></i>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="email_payslips_dummy" x-model="behaviour.email" class="sr-only peer">
                                                <input type="hidden" name="email_payslips" :value="behaviour.email ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>
                                        <h3 class="font-bold text-slate-900 dark:text-white mb-1">Auto-Email Payslips</h3>
                                        <p class="text-xs text-slate-500 leading-relaxed">Automatically dispatch payslips to employee email addresses once the monthly payroll is approved.</p>
                                    </div>

                                    <!-- Password Protect -->
                                    <div class="p-6 bg-white dark:bg-slate-950 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-12 h-12 flex items-center justify-center bg-red-100 dark:bg-red-900/30 rounded-xl text-red-600 dark:text-red-400 group-hover:scale-110 transition-transform">
                                                <i data-lucide="lock" class="w-6 h-6"></i>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="password_protect_payslips_dummy" x-model="behaviour.password" class="sr-only peer">
                                                <input type="hidden" name="password_protect_payslips" :value="behaviour.password ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>
                                        <h3 class="font-bold text-slate-900 dark:text-white mb-1">Password Protection</h3>
                                        <p class="text-xs text-slate-500 leading-relaxed">Secure PDF payslips. Employees will need to enter their Date of Birth (DDMMYYYY) to view the file.</p>
                                    </div>

                                    <!-- Overtime -->
                                    <div class="p-6 bg-white dark:bg-slate-950 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 group hover:border-brand-300 dark:hover:border-brand-500/30 transition-all">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-12 h-12 flex items-center justify-center bg-yellow-100 dark:bg-yellow-900/30 rounded-xl text-yellow-600 dark:text-yellow-400 group-hover:scale-110 transition-transform">
                                                <i data-lucide="clock" class="w-6 h-6"></i>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="enable_overtime_dummy" x-model="behaviour.overtime" class="sr-only peer">
                                                <input type="hidden" name="enable_overtime" :value="behaviour.overtime ? 'on' : ''">
                                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 font-bold"></div>
                                            </label>
                                        </div>
                                        <h3 class="font-bold text-slate-900 dark:text-white mb-1">Enable Overtime</h3>
                                        <p class="text-xs text-slate-500 leading-relaxed">Add overtime input fields to the payroll processing screen for manual calculations.</p>
                                    </div>

                                </div>
                                <div class="mt-10 flex justify-end">
                                    <button type="submit" class="px-8 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold shadow-lg shadow-brand-500/25 transition-all flex items-center gap-2">
                                        <i data-lucide="save" class="w-4 h-4"></i> Save Behaviours
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    </div> <!-- End Tabbed Content Area -->
                </div>
            </main>
        </div>

    
    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
