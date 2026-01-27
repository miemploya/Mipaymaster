<?php
require_once 'config/db.php';

try {
    // 1. Create LOANS table
    // Status enum updated to include 'active' explicitly or implied by approved? 
    // Plan said: 'pending', 'approved', 'rejected', 'completed', 'cancelled'. 
    // Usually 'approved' implies active until 'completed'.
    $pdo->exec("CREATE TABLE IF NOT EXISTS loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        loan_type ENUM('salary_advance', 'housing', 'car', 'personal', 'other') NOT NULL,
        custom_type VARCHAR(100) DEFAULT NULL,
        principal_amount DECIMAL(15,2) NOT NULL,
        repayment_amount DECIMAL(15,2) NOT NULL,
        balance DECIMAL(15,2) NOT NULL,
        start_month INT NOT NULL,
        start_year INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
        document_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_at DATETIME DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        INDEX (company_id),
        INDEX (employee_id),
        INDEX (status)
    )");
    echo "Loans table created/checked.<br>";

    // 2. Create LOAN_REPAYMENTS table
    $pdo->exec("CREATE TABLE IF NOT EXISTS loan_repayments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loan_id INT NOT NULL,
        payroll_run_id INT NOT NULL,
        amount_paid DECIMAL(15,2) NOT NULL,
        balance_after DECIMAL(15,2) NOT NULL,
        paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (loan_id),
        INDEX (payroll_run_id)
    )");
    echo "Loan Repayments table created/checked.<br>";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
