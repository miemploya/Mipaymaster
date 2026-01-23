<?php
require_once '../config/db.php';

echo "Starting Database Repair...\n";

try {
    // 1. Salary Categories (Ensure table exists)
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
    echo "Checked salary_categories\n";

    // 2. Salary Components (Critical for dashboard)
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
    echo "Checked salary_components\n";

    // 3. Departments
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (company_id)
    )");
    echo "Checked departments\n";

    // 4. Employees - Add department_id
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'department_id'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN department_id INT DEFAULT NULL AFTER employment_status"); // approx position
        echo "Added department_id to employees\n";
    }

    // 5. Statutory Settings
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
    echo "Checked statutory_settings\n";

    // 6. Payroll Behaviours
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_behaviours (
        company_id INT PRIMARY KEY,
        prorate_new_hires TINYINT(1) DEFAULT 1,
        email_payslips TINYINT(1) DEFAULT 0,
        password_protect_payslips TINYINT(1) DEFAULT 0,
        enable_overtime TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Checked payroll_behaviours\n";

    // 7. Payroll Items
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
    echo "Checked payroll_items\n";
    
    // 8. Logo URL
    $cols = $pdo->query("SHOW COLUMNS FROM companies LIKE 'logo_url'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL");
        echo "Added logo_url to companies\n";
    }

    // 9. Payroll Items Types tables (referenced in company.php line 308: payroll_deduction_types)
    // company.php logic refers to payroll_bonus_types and payroll_deduction_types.
    // Wait, Step 136, line 308: $table = ($type === 'deduction') ? 'payroll_deduction_types' : 'payroll_bonus_types';
    // But creation block created 'payroll_items'.
    // Ah, line 142 creates 'payroll_items'.
    // line 308 uses 'payroll_deduction_types'. 
    // This is a DISCREPANCY in company.php!
    // company.php:308 implies there are tables named payroll_bonus_types / payroll_deduction_types.
    // But migration block :142 creates payroll_items.
    
    // Let's look at lines 857 in company.php:
    // $stmt = $pdo->prepare("SELECT *, 'bonus' as type FROM payroll_bonus_types ...");
    // So the CODE uses payroll_bonus_types.
    // The MIGRATION (commented out) uses payroll_items.
    // This is messy.
    // I should create payroll_bonus_types and payroll_deduction_types to match the CODE logic.
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_bonus_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        calculation_mode VARCHAR(50) DEFAULT 'fixed',
        percentage_base VARCHAR(50) DEFAULT 'basic',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payroll_deduction_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        calculation_mode VARCHAR(50) DEFAULT 'fixed',
        percentage_base VARCHAR(50) DEFAULT 'basic',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Checked bonus/deduction types tables\n";


    echo "Repair Completed Successfully.\n";

} catch (PDOException $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
?>
