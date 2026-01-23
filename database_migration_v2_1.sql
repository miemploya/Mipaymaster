-- DATABASE MIGRATION V2.1 - Mipaymaster
-- Description: Increment • Locked Payroll • Statutory Validation • Rollback Safety
-- Date: 2026-01-20

-- =======================================================
-- 1. ROLLBACK SAFETY: SCHEMA VERSIONS
-- =======================================================
CREATE TABLE IF NOT EXISTS schema_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version_label VARCHAR(50),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO schema_versions (version_label)
SELECT 'v2.1_start'
WHERE NOT EXISTS (
  SELECT 1 FROM schema_versions WHERE version_label = 'v2.1_start'
);

-- =======================================================
-- 2. INCREMENT VALIDATION & APPROVAL
-- =======================================================
-- Modifying employee_salary_adjustments to support approval workflow and auditing

CREATE TABLE IF NOT EXISTS employee_salary_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    adjustment_type ENUM('fixed','percentage','override') NOT NULL,
    adjustment_value DECIMAL(15,2) NOT NULL,
    effective_from DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (employee_id)
);

-- Add new columns safely using prepared statements
SET @dbname = DATABASE();
SET @tablename = "employee_salary_adjustments";

SET @columnname = "effective_to";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE employee_salary_adjustments ADD COLUMN effective_to DATE DEFAULT NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "reason";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE employee_salary_adjustments ADD COLUMN reason VARCHAR(255)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "approval_status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE employee_salary_adjustments ADD COLUMN approval_status ENUM('pending','approved','rejected') DEFAULT 'pending'"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "approved_by";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE employee_salary_adjustments ADD COLUMN approved_by INT DEFAULT NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "approved_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE employee_salary_adjustments ADD COLUMN approved_at DATETIME DEFAULT NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- OPTIONAL: Add Index for frequent lookups
-- Using a stored procedure-like check isn't strictly necessary for CREATE INDEX IF NOT EXISTS in valid MySQL versions (8.0+ has it), 
-- but simpler to just try it or check. 
-- Since we are in a script that might run on older MySQL/MariaDB, let's use the handler approach or just try-catch in PHP runner?
-- User asked for SQL: "CREATE INDEX idx_employee_increment_active ON ..."
-- We will use a safe checking block again.
SET @indexname = "idx_employee_increment_active";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_employee_increment_active ON employee_salary_adjustments (employee_id, approval_status, is_active, effective_from, effective_to)"
));
PREPARE indexIfNotExists FROM @preparedStatement;
EXECUTE indexIfNotExists;
DEALLOCATE PREPARE indexIfNotExists;


-- =======================================================
-- 3. PAYROLL LOCKING & REVERSAL CONTROL
-- =======================================================

-- Update payroll_runs status enum SAFELY
-- NOTE: Rollback requires manual restoration of previous enum definition.
ALTER TABLE payroll_runs 
CHANGE status status ENUM('draft','approved','locked','reversed') DEFAULT 'draft';


-- Add locking columns
SET @tablename = "payroll_runs";
SET @columnname = "locked_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE payroll_runs ADD COLUMN locked_at DATETIME NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "locked_by";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE payroll_runs ADD COLUMN locked_by INT NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Reversal Log Table
CREATE TABLE IF NOT EXISTS payroll_reversals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    reason VARCHAR(255),
    reversed_by INT,
    reversed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (payroll_run_id)
);

-- =======================================================
-- 4. PAYROLL SNAPSHOT STORAGE
-- =======================================================
CREATE TABLE IF NOT EXISTS payroll_snapshots (
    payroll_entry_id INT NOT NULL,
    snapshot_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payroll_entry_id)
);

INSERT INTO schema_versions (version_label)
SELECT 'v2.1_complete'
WHERE NOT EXISTS (
  SELECT 1 FROM schema_versions WHERE version_label = 'v2.1_complete'
);
