<?php
/**
 * Setup Missing Modules
 * This script initializes the database tables required for Recruitment, Onboarding, 
 * Performance, and Employee Relations modules.
 */

require_once 'includes/functions.php';

// We need access to the $pdo object. functions.php likely includes config/db.php which creates it.
// Let's assume $pdo is available or try to re-establish connection if needed.
// Based on functions.php viewing, it requires config/db.php.

try {
    echo "Starting Database Update...<br>";

    $queries = [
        "CREATE TABLE IF NOT EXISTS hr_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            email VARCHAR(255),
            phone VARCHAR(50),
            resume_path VARCHAR(255),
            status ENUM('pending', 'shortlisted', 'interviewed', 'hired', 'rejected') DEFAULT 'pending',
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES hr_jobs(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS onboarding_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            role_target ENUM('all', 'manager', 'hr', 'employee') DEFAULT 'all',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS employee_onboarding (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            task_id INT NOT NULL,
            status ENUM('pending', 'completed') DEFAULT 'pending',
            completed_at TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES onboarding_tasks(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS performance_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            employee_id INT NOT NULL,
            reviewer_id INT,
            review_period_start DATE,
            review_period_end DATE,
            score DECIMAL(3, 1),
            comments TEXT,
            status ENUM('draft', 'submitted', 'finalized') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS hr_disciplinary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            employee_id INT NOT NULL,
            type ENUM('warning', 'suspension', 'termination', 'other'),
            description TEXT,
            action_taken TEXT,
            incident_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
        // Extract table name for feedback (rough regex)
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches);
        $tableName = $matches[1] ?? 'Unknown Table';
        echo "Table '$tableName' check/creation successful.<br>";
    }

    echo "Database Update Complete.";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
