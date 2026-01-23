-- Database Schema for MiPayMaster

CREATE DATABASE IF NOT EXISTS mipaymaster;
USE mipaymaster;

-- 1. Companies Table
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    logo_path VARCHAR(255),
    currency VARCHAR(10) DEFAULT 'NGN',
    tax_jurisdiction VARCHAR(50) DEFAULT 'Nigeria',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Users Table (Admins/HR/Employees login)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'company_admin', 'hr_manager', 'employee') DEFAULT 'employee',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 3. Salary Categories
CREATE TABLE IF NOT EXISTS salary_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL, -- e.g., Junior, Senior
    base_gross_amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 4. Statutory Settings (Global per company)
CREATE TABLE IF NOT EXISTS statutory_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    enable_paye BOOLEAN DEFAULT TRUE,
    enable_pension BOOLEAN DEFAULT TRUE,
    enable_nhis BOOLEAN DEFAULT FALSE,
    enable_nhf BOOLEAN DEFAULT FALSE,
    pension_employer_perc DECIMAL(5, 2) DEFAULT 10.00,
    pension_employee_perc DECIMAL(5, 2) DEFAULT 8.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 5. Employees Biodata
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT UNIQUE, -- Link to users table for login
    salary_category_id INT,
    payroll_id VARCHAR(50) UNIQUE, -- Auto-generated
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    other_names VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    dob DATE,
    gender ENUM('Male', 'Female', 'Other'),
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed'),
    address TEXT,
    employment_status ENUM('Full Time', 'Part Time', 'Contract', 'Intern') DEFAULT 'Full Time',
    date_of_joining DATE,
    photo_path VARCHAR(255),
    
    -- Banking
    bank_name VARCHAR(100),
    account_number VARCHAR(20),
    account_name VARCHAR(150),
    
    -- Pension
    pension_pfa VARCHAR(100),
    pension_rsa VARCHAR(50),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (salary_category_id) REFERENCES salary_categories(id) ON DELETE SET NULL
);

-- 6. Attendance Records
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in DATETIME,
    clock_out DATETIME,
    status ENUM('Present', 'Absent', 'Late', 'Leave', 'Holiday') DEFAULT 'Present',
    overtime_hours DECIMAL(5, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 7. Payroll Runs
CREATE TABLE IF NOT EXISTS payroll_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    total_gross DECIMAL(15, 2) DEFAULT 0.00,
    total_net DECIMAL(15, 2) DEFAULT 0.00,
    created_by INT, -- User ID
    approved_by INT, -- User ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- 8. Payslips
CREATE TABLE IF NOT EXISTS payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(15, 2) DEFAULT 0.00,
    housing_allowance DECIMAL(15, 2) DEFAULT 0.00,
    transport_allowance DECIMAL(15, 2) DEFAULT 0.00,
    other_allowances DECIMAL(15, 2) DEFAULT 0.00,
    gross_salary DECIMAL(15, 2) DEFAULT 0.00,
    
    tax_paye DECIMAL(15, 2) DEFAULT 0.00,
    pension_employee DECIMAL(15, 2) DEFAULT 0.00,
    pension_employer DECIMAL(15, 2) DEFAULT 0.00,
    nhis_deduction DECIMAL(15, 2) DEFAULT 0.00,
    nhf_deduction DECIMAL(15, 2) DEFAULT 0.00,
    other_deductions DECIMAL(15, 2) DEFAULT 0.00,
    total_deductions DECIMAL(15, 2) DEFAULT 0.00,
    
    net_salary DECIMAL(15, 2) DEFAULT 0.00,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 9. Education History
CREATE TABLE IF NOT EXISTS education_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    institution VARCHAR(255),
    degree VARCHAR(100),
    start_year YEAR,
    end_year YEAR,
    certificate_path VARCHAR(255),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 10. Work History
CREATE TABLE IF NOT EXISTS work_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    employer_name VARCHAR(255),
    role VARCHAR(100),
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 11. Next of Kin
CREATE TABLE IF NOT EXISTS next_of_kin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    full_name VARCHAR(255),
    relationship VARCHAR(50),
    phone VARCHAR(50),
    address TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 12. Guarantors
CREATE TABLE IF NOT EXISTS guarantors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    full_name VARCHAR(255),
    relationship VARCHAR(50),
    phone VARCHAR(50),
    id_card_path VARCHAR(255),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 13. HR Recruitment (Jobs)
CREATE TABLE IF NOT EXISTS hr_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

