<?php
require_once '../includes/functions.php';
require_login();
$company_id = $_SESSION['company_id'] ?? 0;
$current_page = 'employees';

// --- ENSURE DATABASE MIGRATION (Columns & Tables) ---
if (isset($_GET['ajax_fetch'])) {
    header('Content-Type: application/json');
    $id = preg_replace('/[^0-9]/', '', $_GET['ajax_fetch']);
    
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $company_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($emp) {
        // Fetch Sub-tables
        $stmt = $pdo->prepare("SELECT * FROM employee_education WHERE employee_id=?");
        $stmt->execute([$id]);
        $emp['education'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM employee_work_history WHERE employee_id=?");
        $stmt->execute([$id]);
        $emp['work_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM employee_guarantors WHERE employee_id=?");
        $stmt->execute([$id]);
        $emp['guarantors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM next_of_kin WHERE employee_id=?");
        $stmt->execute([$id]);
        $emp['nok'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($emp);
    } else {
        echo json_encode(['error' => 'Not found']);
    }
    exit;
}
try {
    // 1. Employees Columns
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'department'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER email");
    
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'job_title'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN job_title VARCHAR(100) DEFAULT NULL AFTER department");
    
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'photo_path'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL");
    
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'id_document_path'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN id_document_path VARCHAR(255) DEFAULT NULL");
    
    // NEW COLUMNS
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'marital_status'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN marital_status VARCHAR(50) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employment_type'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN employment_type VARCHAR(50) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'job_description'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN job_description TEXT DEFAULT NULL");

    // MISSING COLUMNS FIXED
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'phone'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN phone VARCHAR(20) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'gender'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN gender VARCHAR(20) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'dob'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN dob DATE DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'address'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN address TEXT DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employment_status'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN employment_status VARCHAR(50) DEFAULT 'Active'");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'date_of_joining'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN date_of_joining DATE DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'bank_name'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'account_number'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN account_number VARCHAR(50) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'account_name'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN account_name VARCHAR(100) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'pension_pfa'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN pension_pfa VARCHAR(100) DEFAULT NULL");

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'pension_rsa'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN pension_rsa VARCHAR(50) DEFAULT NULL");

    // Ensure salary_category_id is present (It existed in debug check, but safe to keep)
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'salary_category_id'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employees ADD COLUMN salary_category_id INT DEFAULT NULL");

    // 2. New Sub-tables for extended info
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_education (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        institution VARCHAR(255),
        qualification VARCHAR(100),
        year INT,
        course VARCHAR(255),
        certificate_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_work_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employer VARCHAR(255),
        role VARCHAR(255),
        duration VARCHAR(100),
        document_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id)
    )");

    // DB Patch: Ensure document_path exists if table already existed
    $cols = $pdo->query("SHOW COLUMNS FROM employee_work_history LIKE 'document_path'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE employee_work_history ADD COLUMN document_path VARCHAR(255) DEFAULT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_guarantors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        full_name VARCHAR(255),
        relationship VARCHAR(100),
        phone VARCHAR(50),
        address TEXT,
        occupation VARCHAR(255),
        id_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id)
    )");

    // Ensure departments table exists (Defensive)
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (company_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS next_of_kin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        full_name VARCHAR(255),
        relationship VARCHAR(100),
        phone VARCHAR(50),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id)
    )");

} catch (Exception $e) { /* ignore */ }


// --- UPLOAD HELPER ---
// --- UPLOAD HELPER ---
function upload_file($file_array, $key = null, $folder = 'uploads', $max_size = 2097152, $compress = false) {
    $target_dir = "../" . $folder . "/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    // Check if it's a simple file or array file
    if ($key !== null) {
        if (!isset($file_array['name'][$key]) || $file_array['error'][$key] != 0) return null;
        $name = $file_array['name'][$key];
        $tmp = $file_array['tmp_name'][$key];
        $size = $file_array['size'][$key];
    } else {
        if (!isset($file_array['name']) || $file_array['error'] != 0) return null;
        $name = $file_array['name'];
        $tmp = $file_array['tmp_name'];
        $size = $file_array['size'];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowed)) return null;

    // Size Check
    if ($size > $max_size) return null;

    $new_name = uniqid() . '.' . $ext;
    $target_file = $target_dir . $new_name;

    // Compression Logic (Images Only)
    if ($compress && in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $info = getimagesize($tmp);
        if ($info['mime'] == 'image/jpeg') $image = imagecreatefromjpeg($tmp);
        elseif ($info['mime'] == 'image/png') $image = imagecreatefrompng($tmp);
        else $image = null;

        if ($image) {
            // Save with 70% quality (approx 30% compression trigger)
            if(imagejpeg($image, $target_file, 70)) {
                imagedestroy($image);
                return $folder . "/" . $new_name;
            }
        }
    }

    // Fallback or Non-Image
    if (move_uploaded_file($tmp, $target_file)) {
        return $folder . "/" . $new_name;
    }
    return null;
}


// --- HANDLE FORM SUBMISSION (Add/Edit/Delete) ---
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. DELETE
    if ($action === 'delete_employee') {
        $del_id = preg_replace('/[^0-9]/', '', $_POST['employee_id']);
        try {
            $pdo->prepare("DELETE FROM employees WHERE id=? AND company_id=?")->execute([$del_id, $company_id]);
            $_SESSION['flash_success'] = "Employee deleted successfully.";
            header("Location: employees.php"); exit;
        } catch (PDOException $e) { $error_msg = "Delete Error: " . $e->getMessage(); }
    }

    // 2. CREATE / UPDATE
    elseif ($action === 'create_employee' || $action === 'update_employee') {
        $first_name = clean_input($_POST['first_name']);
        $last_name = clean_input($_POST['last_name']);
        $email = clean_input($_POST['email']);

        if($first_name && $last_name) {
            try {
                $pdo->beginTransaction();

                // Uploads
                // Uploads handled after ID generation to ensure user-specific buckets
                $photo_path = null;
                $id_doc_path = null;

                // Common Fields
                $phone = clean_input($_POST['phone']);
                $gender = clean_input($_POST['gender']);
                $dob = $_POST['dob'] ?: null;
                $address = clean_input($_POST['address']);
                $marital = clean_input($_POST['marital_status']);
                $dept = clean_input($_POST['department']);
                $title = clean_input($_POST['job_title']);
                $emp_type = clean_input($_POST['employment_type']);
                $status = clean_input($_POST['employment_status']);
                $join_date = $_POST['date_of_joining'] ?: date('Y-m-d');
                $job_desc = clean_input($_POST['job_description']);
                $cat_id = !empty($_POST['salary_category_id']) ? $_POST['salary_category_id'] : null;
                $bank = clean_input($_POST['bank_name']);
                $acc_num = clean_input($_POST['account_number']);
                $acc_name = clean_input($_POST['account_name']);
                $has_pens = isset($_POST['has_pension']) ? 1 : 0;
                $pfa = $has_pens ? clean_input($_POST['pension_pfa']) : null;
                $rsa = $has_pens ? clean_input($_POST['pension_rsa']) : null;

                if ($action === 'create_employee') {
                    // INSERT
                    // 1. Insert without Payroll ID first to get ID
                    // INSERT (Files null initially)
                    $stmt = $pdo->prepare("INSERT INTO employees 
                        (company_id, first_name, last_name, email, phone, gender, dob, address, marital_status, 
                        department, job_title, employment_type, employment_status, date_of_joining, job_description,
                        salary_category_id, bank_name, account_number, account_name, pension_pfa, pension_rsa, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $company_id, $first_name, $last_name, $email, $phone, $gender, $dob, $address, $marital,
                        $dept, $title, $emp_type, $status, $join_date, $job_desc,
                        $cat_id, $bank, $acc_num, $acc_name, $pfa, $rsa
                    ]);
                    $emp_id = $pdo->lastInsertId();

                    // Generate ID & Handle Uploads
                    $payroll_id = 'MIP-' . str_pad($emp_id, 4, '0', STR_PAD_LEFT);
                    
                    $photo_path = isset($_FILES['photo_upload']) ? upload_file($_FILES['photo_upload'], null, "uploads/employees/$emp_id/profile", 2097152, true) : null;
                    $id_doc_path = isset($_FILES['id_document']) ? upload_file($_FILES['id_document'], null, "uploads/employees/$emp_id/documents", 2097152, true) : null;

                    $upd_sql = "UPDATE employees SET payroll_id=?";
                    $upd_params = [$payroll_id];
                    if($photo_path) { $upd_sql .= ", photo_path=?"; $upd_params[] = $photo_path; }
                    if($id_doc_path) { $upd_sql .= ", id_document_path=?"; $upd_params[] = $id_doc_path; }
                    $upd_sql .= " WHERE id=?";
                    $upd_params[] = $emp_id;
                    $pdo->prepare($upd_sql)->execute($upd_params);
                    
                    $_SESSION['flash_success'] = "Employee onboarded successfully. ID: $payroll_id";
                    // header("Location: employees.php"); exit; // Moved to end

                } else {
                    // UPDATE
                    $emp_id = preg_replace('/[^0-9]/', '', $_POST['employee_id']);
                    
                    $photo_path = isset($_FILES['photo_upload']) ? upload_file($_FILES['photo_upload'], null, "uploads/employees/$emp_id/profile", 2097152, true) : null;
                    $id_doc_path = isset($_FILES['id_document']) ? upload_file($_FILES['id_document'], null, "uploads/employees/$emp_id/documents", 2097152, true) : null;
                    
                    // Build Update SQL dynamically to handle file preservation
                    $sql = "UPDATE employees SET 
                        first_name=?, last_name=?, email=?, phone=?, gender=?, dob=?, address=?, marital_status=?, 
                        department=?, job_title=?, employment_type=?, employment_status=?, date_of_joining=?, job_description=?,
                        salary_category_id=?, bank_name=?, account_number=?, account_name=?, pension_pfa=?, pension_rsa=?";
                    $params = [
                        $first_name, $last_name, $email, $phone, $gender, $dob, $address, $marital,
                        $dept, $title, $emp_type, $status, $join_date, $job_desc,
                        $cat_id, $bank, $acc_num, $acc_name, $pfa, $rsa
                    ];

                    if($photo_path) { $sql .= ", photo_path=?"; $params[] = $photo_path; }
                    if($id_doc_path) { $sql .= ", id_document_path=?"; $params[] = $id_doc_path; }

                    $sql .= " WHERE id=? AND company_id=?";
                    $params[] = $emp_id;
                    $params[] = $company_id;

                    $pdo->prepare($sql)->execute($params);
                    
                    // Clear sub-tables for rewrite (simplest strategy for complex nested forms)
                    $pdo->prepare("DELETE FROM employee_education WHERE employee_id=?")->execute([$emp_id]);
                    $pdo->prepare("DELETE FROM employee_work_history WHERE employee_id=?")->execute([$emp_id]);
                    $pdo->prepare("DELETE FROM employee_guarantors WHERE employee_id=?")->execute([$emp_id]);
                    $pdo->prepare("DELETE FROM employee_additional_qualifications WHERE employee_id=?")->execute([$emp_id]);
                    $pdo->prepare("DELETE FROM next_of_kin WHERE employee_id=?")->execute([$emp_id]);

                    $_SESSION['flash_success'] = "Employee profile updated successfully.";
                }

                // SUB-TABLES (Shared Logic)
                if ($action === 'create_employee') $new_emp_id = $emp_id;
                else $new_emp_id = $emp_id; // For update, we already have emp_id set

                // Education
                if(isset($_POST['edu_institution'])) {
                    $stmt = $pdo->prepare("INSERT INTO employee_education (employee_id, institution, qualification, year, course, certificate_path) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach($_POST['edu_institution'] as $k => $inst) {
                        if(!empty($inst)) {
                            // Check for new upload, otherwise use existing path
                            $cert_path = isset($_FILES['edu_certificate']) && !empty($_FILES['edu_certificate']['name'][$k]) 
                                ? upload_file($_FILES['edu_certificate'], $k, "uploads/employees/$new_emp_id/education", 5242880, true) 
                                : ($_POST['edu_existing_path'][$k] ?? null);
                                
                            $stmt->execute([$new_emp_id, $inst, $_POST['edu_qualification'][$k], (int)$_POST['edu_year'][$k], $_POST['edu_course'][$k], $cert_path]);
                        }
                    }
                }
                // Work
                if(isset($_POST['work_employer'])) {
                    $stmt = $pdo->prepare("INSERT INTO employee_work_history (employee_id, employer, role, duration, document_path) VALUES (?, ?, ?, ?, ?)");
                    foreach($_POST['work_employer'] as $k => $emp) {
                        if($emp) {
                            $doc_path = isset($_FILES['work_document']) && !empty($_FILES['work_document']['name'][$k])
                                ? upload_file($_FILES['work_document'], $k, "uploads/employees/$new_emp_id/work", 5242880, true) 
                                : ($_POST['work_existing_path'][$k] ?? null);
                                
                            $stmt->execute([$new_emp_id, $emp, $_POST['work_role'][$k], $_POST['work_duration'][$k], $doc_path]);
                        }
                    }
                }
                // Guarantors
                if(isset($_POST['guarantor_name'])) {
                    $stmt = $pdo->prepare("INSERT INTO employee_guarantors (employee_id, full_name, relationship, phone, address, occupation, id_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach($_POST['guarantor_name'] as $k => $name) {
                        if($name) {
                            $id_path = isset($_FILES['guarantor_id']) && !empty($_FILES['guarantor_id']['name'][$k])
                                ? upload_file($_FILES['guarantor_id'], $k, "uploads/employees/$new_emp_id/guarantors") 
                                : ($_POST['guarantor_existing_path'][$k] ?? null);
                                
                            $stmt->execute([$new_emp_id, $name, $_POST['guarantor_rel'][$k], $_POST['guarantor_phone'][$k], $_POST['guarantor_address'][$k], $_POST['guarantor_job'][$k], $id_path]);
                        }
                    }
                }
                // Additional Qualifications
                if(isset($_POST['additional_title'])) {
                    $stmt = $pdo->prepare("INSERT INTO employee_additional_qualifications (employee_id, title, document_path) VALUES (?, ?, ?)");
                    foreach($_POST['additional_title'] as $k => $title) {
                        if($title) {
                            $doc_path = isset($_FILES['additional_document']) && !empty($_FILES['additional_document']['name'][$k])
                                ? upload_file($_FILES['additional_document'], $k, "uploads/employees/$new_emp_id/qualifications", 5242880, true) 
                                : ($_POST['additional_existing_path'][$k] ?? null);
                                
                            $stmt->execute([$new_emp_id, $title, $doc_path]);
                        }
                    }
                }
                // NOK
                if(!empty($_POST['nok_name'])) {
                    $pdo->prepare("INSERT INTO next_of_kin (employee_id, full_name, relationship, phone, address) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$new_emp_id, clean_input($_POST['nok_name']), clean_input($_POST['nok_relationship']), clean_input($_POST['nok_phone']), clean_input($_POST['nok_address'])]);
                }

                $pdo->commit();
                
                // Redirect after successful commit
                header("Location: employees.php"); 
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please fill in all required fields (First Name, Last Name, Email).";
        }
    }
}

// Fetch Departments for Dropdown
$departments = [];
try {
    $stmt_dept = $pdo->prepare("SELECT name FROM departments WHERE company_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt_dept->execute([$company_id]);
    $departments = $stmt_dept->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { /* use empty list */ }


// Fetch Salary Categories (Base Gross)
$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, name as title, base_gross_amount FROM salary_categories WHERE company_id = ? ORDER BY name ASC");
    $stmt->execute([$company_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

// Fetch Salary Components (SSOT Rules)
$salary_components = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM salary_components WHERE company_id = ? AND is_active = 1 ORDER BY 
        CASE WHEN type='basic' THEN 1 WHEN type='system' THEN 2 ELSE 3 END, name ASC");
    $stmt->execute([$company_id]);
    $salary_components = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

// Fetch Employees from Logic
$stmt = $pdo->prepare("
    SELECT e.*, s.name as category_name, s.base_gross_amount 
    FROM employees e 
    LEFT JOIN salary_categories s ON e.salary_category_id = s.id 
    WHERE e.company_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$company_id]);
$employees_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Extended Info
$js_employees = [];
foreach ($employees_raw as $emp) {
    // Education
    $stmt = $pdo->prepare("SELECT * FROM employee_education WHERE employee_id=?");
    $stmt->execute([$emp['id']]);
    $emp['education'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Work History
    $stmt = $pdo->prepare("SELECT * FROM employee_work_history WHERE employee_id=?");
    $stmt->execute([$emp['id']]);
    $emp['work_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Guarantors
    $stmt = $pdo->prepare("SELECT * FROM employee_guarantors WHERE employee_id=?");
    $stmt->execute([$emp['id']]);
    $emp['guarantors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Additional Qualifications
    $stmt = $pdo->prepare("SELECT * FROM employee_additional_qualifications WHERE employee_id=?");
    $stmt->execute([$emp['id']]);
    $emp['additional_qualifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->execute([$emp['id']]);
    $emp['guarantors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // NOK
    $stmt = $pdo->prepare("SELECT * FROM next_of_kin WHERE employee_id=?");
    $stmt->execute([$emp['id']]);
    $emp['nok'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Loans
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE employee_id=? ORDER BY created_at DESC");
    $stmt->execute([$emp['id']]);
    $emp['loans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalize Logic
    $emp['status'] = ucfirst($emp['status'] ?? 'Active');
    $emp['department'] = $emp['department'] ?? 'General';
    $emp['role'] = $emp['job_title'] ?? 'Employee';
    $emp['id_display'] = $emp['payroll_id'] ?? ('MIP-' . str_pad($emp['id'], 3, '0', STR_PAD_LEFT));

    $js_employees[] = $emp;
}

// Transform for JS
$colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#10B981', '#06B6D4', '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899'];

foreach ($js_employees as &$emp) {
    if (!isset($emp['initials'])) {
       $emp['initials'] = strtoupper(substr($emp['first_name'] ?? '', 0, 1) . substr($emp['last_name'] ?? '', 0, 1));
    }
    if (!isset($emp['color'])) {
       $emp['color'] = $colors[array_rand($colors)];
    }
    // List View Keys
    $emp['name'] = ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '');
    $emp['category'] = $emp['category_name'] ?? 'Unassigned';
    $emp['gross'] = $emp['base_gross_amount'] ?? 0;
    
    // Fix: Map Photo Path
    $emp['img'] = !empty($emp['photo_path']) ? '../' . $emp['photo_path'] : null;
    
    // Ensure nested objects are arrays if null/empty
    if (!isset($emp['education'])) $emp['education'] = [];
    if (!isset($emp['work_history'])) $emp['work_history'] = [];
    if (!isset($emp['guarantors'])) $emp['guarantors'] = [];
    if (!isset($emp['additional_qualifications'])) $emp['additional_qualifications'] = [];
    if (!isset($emp['loans'])) $emp['loans'] = [];
}
unset($emp);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Mipaymaster</title>
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
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Alpine.js Logic -->
    <script>
        function employeeApp() {
            return {
                view: 'list', // 'list', 'add', 'profile'
                formMode: 'create_employee', // 'create_employee' or 'update_employee'
                currentStep: 1,
                steps: ['Biodata', 'Employment', 'Salary', 'Bank', 'Education', 'Work History', 'Guarantor', 'NOK', 'Review'],
                selectedCategory: '',
                hasPension: true,
                sidebarOpen: false,
                
                // Tabs for Profile View
                profileTab: 'overview', 
                
                // DATA
                employees: <?php echo json_encode($js_employees); ?>,
                salaryCategories: <?php echo json_encode($categories); ?>,
                salaryComponents: <?php echo json_encode($salary_components); ?>,
                departmentsList: <?php echo json_encode($departments); ?>,
                
                // FILTER STATE
                searchQuery: '',
                selectedDept: '',

                // COMPUTED: Filtered List
                get filteredEmployees() {
                    return this.employees.filter(emp => {
                        const q = this.searchQuery.toLowerCase();
                        const matchesSearch = !q || 
                            (emp.name && emp.name.toLowerCase().includes(q)) ||
                            (emp.id_display && emp.id_display.toLowerCase().includes(q));
                        
                        const matchesDept = !this.selectedDept || (emp.department === this.selectedDept);

                        return matchesSearch && matchesDept;
                    });
                },
                
                // Computed Salary Breakdown
                get calculatedSalary() {
                    if (!this.formData.salary_category_id) return null;
                    const cat = this.salaryCategories.find(c => c.id == this.formData.salary_category_id);
                    if (!cat) return null;
                    
                    const gross = parseFloat(cat.base_gross_amount || 0);
                    let breakdown = [];
                    let basicAmount = 0;

                    // 1. Calculate Basic (System Component)
                    const basicComp = this.salaryComponents.find(c => c.type === 'basic');
                    if (basicComp) {
                        basicAmount = (parseFloat(basicComp.percentage) / 100) * gross;
                        breakdown.push({ name: basicComp.name, amount: basicAmount, is_base: true });
                    }

                    // 2. Calculate Others
                    this.salaryComponents.forEach(comp => {
                        if (comp.type === 'basic') return; // Already done
                        if (comp.type === 'system' && comp.name === 'Basic Salary') return; // Double check

                        let amount = 0;
                        if (comp.calculation_method === 'fixed') {
                            amount = parseFloat(comp.amount || 0);
                        } else {
                            // Percentage
                            const perc = parseFloat(comp.percentage || 0);
                            if (comp.percentage_base === 'basic') {
                                amount = (perc / 100) * basicAmount;
                            } else {
                                amount = (perc / 100) * gross;
                            }
                        }
                        breakdown.push({ name: comp.name, amount: amount });
                    });
                    
                    return { gross, breakdown };
                },
                selectedEmployee: null,
                incrementHistory: [],

                // MAIN FORM DATA
                formData: {
                    id: '',
                    first_name: '', last_name: '', email: '', phone: '', gender: 'Male', dob: '', 
                    address: '', marital_status: 'Single',
                    department: '', job_title: '', employment_type: 'Full Time', employment_status: 'Active', 
                    date_of_joining: '', job_description: '', salary_category_id: '',
                    bank_name: '', account_number: '', account_name: '',
                    pension_pfa: '', pension_rsa: '',
                    nok_name: '', nok_relationship: '', nok_phone: '', nok_address: ''
                },

                // Dynamic Form Data (Repeaters)
                educationList: [{institution: '', qualification: '', year: '', course: ''}],
                workList: [{employer: '', role: '', duration: ''}],
                guarantorList: [{name: '', relationship: '', phone: '', address: '', occupation: ''}],
                additionalList: [{title: '', document_path: ''}],

                addEducation() { this.educationList.push({institution: '', qualification: '', year: '', course: ''}); },
                removeEducation(index) { this.educationList.splice(index, 1); },

                addWork() { this.workList.push({employer: '', role: '', duration: ''}); },
                removeWork(index) { this.workList.splice(index, 1); },

                addGuarantor() { this.guarantorList.push({name: '', relationship: '', phone: '', address: '', occupation: ''}); },
                removeGuarantor(index) { this.guarantorList.splice(index, 1); },

                addAdditional() { this.additionalList.push({title: '', document_path: ''}); },
                removeAdditional(index) { this.additionalList.splice(index, 1); },

                // LOGIC
                openProfile(emp) {
                    this.selectedEmployee = emp;
                    this.view = 'profile';
                    this.profileTab = 'overview';
                    this.fetchIncrements(emp.id); 
                },

                startAdd() {
                    this.resetForm();
                    this.view = 'add';
                },

                async editEmployee(empId) {
                    const id = empId.toString().replace(/\D/g, ''); 
                    
                    // 1. Reset Form FIRST (this sets mode to 'create', so we override after)
                    this.resetForm();
                    
                    // 2. Set Mode to UPDATE
                    this.formMode = 'update_employee';
                    this.view = 'add'; 
                    this.formData.id = id; // CRITICAL: Set ID

                    // 3. Fetch Full Data
                    try {
                        const response = await fetch(`employees.php?ajax_fetch=${id}`);
                        const data = await response.json();
                        
                        if(data && !data.error) {
                            // Populate FormData
                            this.formData.first_name = data.first_name || '';
                            this.formData.photo_path = data.photo_path || ''; // ADDED: Enable preview of existing photo
                            this.formData.last_name = data.last_name || '';
                            this.formData.email = data.email || '';
                            this.formData.phone = data.phone || '';
                            this.formData.gender = data.gender || 'Male';
                            this.formData.dob = data.dob || '';
                            this.formData.address = data.address || '';
                            this.formData.marital_status = data.marital_status || 'Single';
                            
                            this.formData.department = data.department || '';
                            this.formData.job_title = data.job_title || '';
                            this.formData.employment_type = data.employment_type || 'Full Time';
                            this.formData.employment_status = data.employment_status || 'Active';
                            this.formData.date_of_joining = data.date_of_joining || '';
                            this.formData.job_description = data.job_description || '';
                            this.formData.salary_category_id = data.salary_category_id || '';
                            
                            this.formData.bank_name = data.bank_name || '';
                            this.formData.account_number = data.account_number || '';
                            this.formData.account_name = data.account_name || '';
                            
                            this.formData.pension_pfa = data.pension_pfa || '';
                            this.formData.pension_rsa = data.pension_rsa || '';
                            this.hasPension = !!(data.pension_pfa || data.pension_rsa);

                            // Lists
                            if(data.education && data.education.length) this.educationList = data.education;
                            if(data.work_history && data.work_history.length) this.workList = data.work_history;
                            if(data.guarantors && data.guarantors.length) this.guarantorList = data.guarantors;
                            if(data.additional_qualifications && data.additional_qualifications.length) this.additionalList = data.additional_qualifications;
                            
                            // NOK
                            if(data.nok) {
                                this.formData.nok_name = data.nok.full_name || '';
                                this.formData.nok_relationship = data.nok.relationship || '';
                                this.formData.nok_phone = data.nok.phone || '';
                                this.formData.nok_address = data.nok.address || '';
                            }
                        }
                    } catch (e) {
                        console.error("Fetch Error", e);
                        alert("Could not load employee details. Please try again.");
                    }
                },
                
                resetForm() {
                    this.formMode = 'create_employee';
                    this.currentStep = 1;
                    this.formData = {
                        id: '', first_name: '', last_name: '', email: '', phone: '', gender: 'Male', dob: '', address: '', marital_status: 'Single',
                        department: '', job_title: '', employment_type: 'Full Time', employment_status: 'Active', 
                        date_of_joining: '', job_description: '', salary_category_id: '',
                        bank_name: '', account_number: '', account_name: '',
                        pension_pfa: '', pension_rsa: '',
                        nok_name: '', nok_relationship: '', nok_phone: '', nok_address: ''
                    };
                    this.educationList = [{institution: '', qualification: '', year: '', course: ''}];
                    this.workList = [{employer: '', role: '', duration: ''}];
                    this.guarantorList = [{name: '', relationship: '', phone: '', address: '', occupation: ''}];
                    this.additionalList = [{title: '', document_path: ''}];
                },

                async fetchIncrements(empId) {
                    this.incrementHistory = [];
                    try {
                        const id = empId.toString().replace(/\D/g, ''); 
                        const response = await fetch(`ajax/get_increments.php?employee_id=${id}`);
                        const data = await response.json();
                        if(data.increments) {
                            this.incrementHistory = data.increments;
                        }
                    } catch (e) { console.error("Failed to fetch increments", e); }
                },

                deleteEmployee(id) {
                    if(confirm('Are you strictly sure you want to delete this employee? This action cannot be undone and will remove all associated records.')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `<input type="hidden" name="action" value="delete_employee"><input type="hidden" name="employee_id" value="${id}">`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                },

                getPageTitle() {
                    if(this.view === 'list') return 'Employees';
                    if(this.view === 'add' && this.formMode === 'create_employee') return 'Add New Employee';
                    if(this.view === 'add' && this.formMode === 'update_employee') return 'Edit Employee';
                    if(this.view === 'profile') return 'Employee Profile';
                },

                init() {
                    this.$watch('view', () => setTimeout(() => lucide.createIcons(), 50));
                    this.$watch('currentStep', () => setTimeout(() => lucide.createIcons(), 50));
                    this.$watch('profileTab', () => setTimeout(() => lucide.createIcons(), 50));
                }
            }
        }
    </script>
    <!-- Alpine.js Core -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }

        /* Sidebar Transitions */
        .sidebar-transition { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out, padding 0.3s; }
        #sidebar.w-0 { overflow: hidden; }
        
        /* Toolbar transition */
        #collapsed-toolbar { transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .toolbar-hidden { max-height: 0; opacity: 0; pointer-events: none; overflow: hidden; }
        .toolbar-visible { max-height: 64px; opacity: 1; pointer-events: auto; }

        /* Form Styles */
        /* Form Styles */
        .form-input {
            width: 100%;
            border-radius: 0.5rem; /* rounded-lg */
            border: 1px solid #cbd5e1; /* border-slate-300 */
            background-color: #ffffff;
            color: #0f172a; /* slate-900 */
            font-size: 0.875rem; /* text-sm */
            padding: 0.625rem 1rem; /* py-2.5 px-4 */
            margin-bottom: 0.5rem; /* mb-2 */
            transition: all 0.2s;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); /* shadow-sm */
        }
        .dark .form-input {
            border-color: #334155; /* dark:border-slate-700 */
            background-color: #1e293b; /* dark:bg-slate-800 */
            color: #ffffff;
        }
        .form-input:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #6366f1; /* brand-500 */
            box-shadow: 0 0 0 2px #6366f1; /* ring-2 */
        }

        .form-label {
            display: block;
            font-size: 0.75rem; /* text-xs */
            font-weight: 700; /* font-bold */
            color: #64748b; /* text-slate-500 */
            text-transform: uppercase;
            letter-spacing: 0.025em; /* tracking-wide */
            margin-bottom: 0.6rem; /* Increased spacing */
            margin-left: 0.25rem;
        }
        .dark .form-label {
            color: #94a3b8; /* dark:text-slate-400 */
        }
        
        /* Step Wizard Indicators */
        .step-active { @apply bg-brand-600 border-brand-600 text-white; }
        .step-completed { @apply border-green-500 text-green-500 bg-white dark:bg-slate-900; }
        .step-inactive { @apply border-slate-200 text-slate-400 bg-white dark:bg-slate-900 dark:border-slate-700; }

        /* Profile Tabs Colorful Buttons */
        .nav-pill { @apply flex-1 min-w-[100px] py-4 text-sm font-medium border-b-2 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50; }
        
        /* Blue */
        .tab-overview-active { @apply border-blue-600 text-blue-600 bg-blue-50/50 dark:bg-blue-900/10; }
        /* Green */
        .tab-payroll-active { @apply border-green-600 text-green-600 bg-green-50/50 dark:bg-green-900/10; }
        /* Amber */
        .tab-attendance-active { @apply border-amber-600 text-amber-600 bg-amber-50/50 dark:bg-amber-900/10; }
        /* Purple */
        .tab-documents-active { @apply border-purple-600 text-purple-600 bg-purple-50/50 dark:bg-purple-900/10; }
        /* Red */
        .tab-payslips-active { @apply border-red-600 text-red-600 bg-red-50/50 dark:bg-red-900/10; }
        
        .tab-inactive { @apply border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400; }

    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="employeeApp()">

    <!-- A. LEFT SIDEBAR (Included via PHP normally, but kept inline as strictly requested to match design) -->
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white" x-text="getPageTitle()">Employees</h2>
                </div>
                <!-- Standard Header Actions -->
                <div class="flex items-center gap-4">
                    <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
                        <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                        <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                    </button>
                    <button id="notif-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white dark:border-slate-950"></span>
                    </button>
                     <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 mx-2"></div>
                    <!-- User Avatar -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 cursor-pointer focus:outline-none">
                            <div class="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 flex items-center justify-center overflow-hidden">
                                <i data-lucide="user" class="w-5 h-5 text-slate-500 dark:text-slate-400"></i>
                            </div>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block"></i>
                        </button>
                        <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-100 dark:border-slate-700 py-1 z-50 mr-4" style="display: none;">
                            <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700">
                                <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Role'); ?></p>
                            </div>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">Log Out</a>
                        </div>
                    </div>
                </div>
            </header>

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

            <!-- Scrollable Content -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900">
                
                <!-- VIEW 1: EMPLOYEE LIST -->
                <div x-show="view === 'list'" x-transition.opacity>
                    <!-- Top Actions -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-950 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-800 w-full md:w-auto">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400"></i>
                            <input type="text" x-model="searchQuery" placeholder="Search Name / ID..." class="bg-transparent text-sm focus:outline-none text-slate-900 dark:text-white w-full">
                        </div>
                        <div class="flex gap-2 w-full md:w-auto">
                            <select x-model="selectedDept" class="px-3 py-2 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-600 dark:text-slate-400 focus:outline-none">
                                <option value="">All Departments</option>
                                <template x-for="dept in departmentsList" :key="dept">
                                    <option :value="dept" x-text="dept"></option>
                                </template>
                            </select>
                            <button @click="view = 'add'" class="flex items-center gap-2 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-4 py-2 rounded-lg text-sm font-bold hover:opacity-90 transition-opacity whitespace-nowrap">
                                <i data-lucide="plus" class="w-4 h-4"></i> Add Employee
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <!-- Photo Column -->
                                    <th class="px-6 py-3 font-medium">Photo</th>
                                    <th class="px-6 py-3 font-medium">Payroll ID</th>
                                    <th class="px-6 py-3 font-medium">Employee Name</th>
                                    <th class="px-6 py-3 font-medium hidden md:table-cell">Dept</th>
                                    <th class="px-6 py-3 font-medium hidden md:table-cell">Salary Category</th>
                                    <th class="px-6 py-3 font-medium text-right">Gross Salary</th>
                                    <th class="px-6 py-3 font-medium text-center">Status</th>
                                    <th class="px-6 py-3 font-medium text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="emp in filteredEmployees" :key="emp.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <!-- Photo Data -->
                                        <td class="px-6 py-4">
                                            <div class="w-10 h-10 rounded-full overflow-hidden border border-slate-200 dark:border-slate-700 relative flex items-center justify-center font-bold text-white text-xs"
                                                 :style="!emp.img ? { backgroundColor: emp.color } : {}">
                                                <template x-if="emp.img">
                                                    <img :src="emp.img" alt="Avatar" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!emp.img">
                                                    <span x-text="emp.initials"></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-mono text-slate-500" x-text="emp.id_display || emp.id"></td>
                                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white" x-text="emp.name"></td>
                                        <td class="px-6 py-4 hidden md:table-cell text-slate-500" x-text="emp.department"></td>
                                        <td class="px-6 py-4 hidden md:table-cell text-slate-500" x-text="emp.category"></td>
                                        <td class="px-6 py-4 text-right font-medium text-slate-900 dark:text-white" x-text="'₦ ' + Number(emp.gross).toLocaleString()"></td>
                                        <td class="px-6 py-4 text-center">
                                            <span :class="{
                                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': emp.status === 'Active' || emp.status === 'Full Time',
                                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': emp.status === 'Suspended' || emp.status === 'Probation' || emp.status === 'Contract' || emp.status === 'Part Time',
                                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': emp.status === 'Intern',
                                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': emp.status === 'Exited' || emp.status === 'Terminated' || emp.status === 'Inactive'
                                            }" class="px-2 py-1 rounded-full text-xs font-bold" x-text="emp.status"></span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button @click="openProfile(emp)" class="text-brand-600 hover:underline">View</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW 2: ADD EMPLOYEE WIZARD (9 STEPS) -->
                <div x-show="view === 'add'" x-cloak class="max-w-4xl mx-auto">
                    <!-- Discontinue / Cancel Button Header -->
                    <div class="flex justify-between items-center mb-6">
                         <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white" x-text="formMode === 'edit' ? 'Edit Employee Profile' : 'New Employee Onboarding'"></h3>
                            <p class="text-sm text-slate-500" x-text="formMode === 'edit' ? 'Update employee information and documents' : 'Follow the steps to onboard a new employee'"></p>
                         </div>
                         <button type="button" @click="resetForm(); view = 'list'" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-500 hover:text-red-600 hover:border-red-200 transition-colors shadow-sm font-medium">
                            <i data-lucide="x-circle" class="w-4 h-4"></i> Discontinue
                         </button>
                    </div>

                    <!-- Wizard Steps Header -->
                    <div class="flex justify-between items-center mb-8 overflow-x-auto pb-2 scrollbar-hide">
                        <template x-for="(stepName, index) in steps" :key="index">
                            <div class="flex items-center min-w-max mr-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-colors"
                                     :class="{
                                        'step-active': currentStep === index + 1,
                                        'step-completed': currentStep > index + 1,
                                        'step-inactive': currentStep < index + 1
                                     }">
                                    <span x-show="currentStep <= index + 1" x-text="index + 1"></span>
                                    <i x-show="currentStep > index + 1" data-lucide="check" class="w-4 h-4"></i>
                                </div>
                                <span class="ml-2 text-sm font-medium" 
                                      :class="currentStep === index + 1 ? 'text-brand-600 dark:text-brand-400' : 'text-slate-500'"
                                      x-text="stepName"></span>
                                <div x-show="index !== steps.length - 1" class="w-8 h-px bg-slate-200 dark:bg-slate-700 mx-2"></div>
                            </div>
                        </template>
                    </div>

                    <!-- Alerts -->
                    <?php if($success_msg): ?>
                    <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg flex items-center gap-2 border border-green-200">
                        <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $success_msg; ?>
                    </div>
                    <?php endif; ?>
                    <?php if($error_msg): ?>
                    <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg flex items-center gap-2 border border-red-200">
                        <i data-lucide="alert-circle" class="w-5 h-5"></i> <?php echo $error_msg; ?>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['flash_success'])): ?>
                    <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg flex items-center gap-2 border border-green-200">
                        <i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Wizard Form Container -->
                    <form method="POST" enctype="multipart/form-data" novalidate class="bg-white dark:bg-slate-950 rounded-xl shadow-lg border border-slate-200 dark:border-slate-800 p-8">
                        <input type="hidden" name="action" :value="formMode">
                        <input type="hidden" name="employee_id" :value="formData.id">
                        
                        <!-- STEP 1: BIODATA -->
                        <div x-show="currentStep === 1">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Biodata</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-2 flex items-center gap-4">
                                    <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800 border-2 border-dashed border-slate-300 flex items-center justify-center relative overflow-hidden group">
                                        <img id="preview-photo" class="w-full h-full object-cover hidden">
                                        <i data-lucide="camera" class="w-6 h-6 text-slate-400 group-hover:opacity-0 transition-opacity"></i>
                                        <input type="file" name="photo_upload" class="absolute inset-0 opacity-0 cursor-pointer" onchange="document.getElementById('preview-photo').src = window.URL.createObjectURL(this.files[0]); document.getElementById('preview-photo').classList.remove('hidden');">
                                    </div>
                                    <div>
                                        <button type="button" class="text-sm text-brand-600 font-medium hover:underline" onclick="document.querySelector('input[name=photo_upload]').click()">Upload Photo</button>
                                        <p class="text-xs text-slate-500">Auto-Generated ID will be assigned</p>
                                    </div>
                                </div>
                                <!-- ID Document Upload -->
                                <div class="col-span-2 border-t border-slate-100 dark:border-slate-800 pt-4">
                                    <label class="form-label">Employee Identification</label>
                                    <div class="flex items-center gap-4 p-4 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                                        <div class="flex-1">
                                            <p class="text-sm text-slate-900 dark:text-white font-medium">Upload Valid ID</p>
                                            <p class="text-xs text-slate-500">Accepted: JPG, PNG, PDF (Max 2MB)</p>
                                        </div>
                                        <div x-data="{ idFileName: '' }" class="contents">
                                            <label class="px-4 py-2 text-sm text-brand-600 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg hover:border-brand-500 transition-colors flex items-center gap-2 cursor-pointer">
                                                <i data-lucide="upload" class="w-4 h-4"></i> 
                                                <span x-text="idFileName || 'Choose File'"></span>
                                                <input type="file" name="id_document" class="hidden" @change="idFileName = $event.target.files[0].name">
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div><label class="form-label">First Name <span class="text-red-500">*</span></label><input type="text" name="first_name" x-model="formData.first_name" class="form-input capitalize" required></div>
                                <div><label class="form-label">Last Name <span class="text-red-500">*</span></label><input type="text" name="last_name" x-model="formData.last_name" class="form-input capitalize" required></div>
                                <div>
                                    <label class="form-label">Gender</label>
                                    <select name="gender" x-model="formData.gender" class="form-input">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div><label class="form-label">Date of Birth</label><input type="date" name="dob" x-model="formData.dob" class="form-input"></div>
                                <div><label class="form-label">Email <span class="text-red-500">*</span></label><input type="email" name="email" x-model="formData.email" class="form-input" required></div>
                                <div><label class="form-label">Phone <span class="text-red-500">*</span></label><input type="tel" name="phone" x-model="formData.phone" class="form-input" required></div>
                                
                                <div class="col-span-2"><label class="form-label">Residential Address</label><textarea name="address" x-model="formData.address" class="form-input" rows="2"></textarea></div>
                                
                                <div>
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" x-model="formData.marital_status" class="form-input">
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: EMPLOYMENT DETAILS -->
                        <div x-show="currentStep === 2">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Employment Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div><label class="form-label">Job Title</label><input type="text" name="job_title" x-model="formData.job_title" class="form-input capitalize"></div>
                                <div>
                                    <label class="form-label">Department</label>
                                    <select name="department" x-model="formData.department" class="form-input">
                                        <option value="">Select Department</option>
                                        <?php foreach($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Employment Type</label>
                                    <select name="employment_type" x-model="formData.employment_type" class="form-input">
                                        <option value="Full Time">Full Time</option>
                                        <option value="Part Time">Part Time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Intern">Intern</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Status</label>
                                    <select name="employment_status" x-model="formData.employment_status" class="form-input">
                                        <option value="Active">Active</option>
                                        <option value="Probation">Probation</option>
                                        <option value="Suspended">Suspended</option>
                                        <option value="Exited">Exited</option>
                                    </select>
                                </div>
                                <div><label class="form-label">Date of Joining</label><input type="date" name="date_of_joining" x-model="formData.date_of_joining" class="form-input"></div>
                                <div class="col-span-2"><label class="form-label">Job Description</label><textarea name="job_description" x-model="formData.job_description" rows="3" class="form-input capitalize"></textarea></div>
                            </div>
                        </div>

                        <!-- STEP 3: SALARY -->
                        <div x-show="currentStep === 3">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Salary & Category</h3>
                            <div class="space-y-6 max-w-2xl">
                                <div>
                                    <label class="form-label">Salary Category</label>
                                    <select name="salary_category_id" x-model="formData.salary_category_id" class="form-input">
                                        <option value="">Select Salary Structure</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['title']) . ' (₦' . number_format($cat['base_gross_amount']) . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-slate-500 mt-1">Breakdown is automatically applied.</p>
                                </div>
                                
                                <div x-show="formData.salary_category_id" class="p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-800">
                                    <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-200 dark:border-slate-800">
                                        <span class="text-sm font-bold text-slate-500">Gross Salary</span>
                                        <span class="text-lg font-bold text-slate-900 dark:text-white" x-text="'₦ ' + Number(calculatedSalary?.gross || 0).toLocaleString()"></span>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <template x-for="comp in calculatedSalary?.breakdown">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-slate-600 dark:text-slate-400" x-text="comp.name"></span>
                                                <span class="font-medium text-slate-900 dark:text-white" x-text="'₦ ' + Number(comp.amount).toLocaleString()"></span>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="mt-4 flex items-center justify-center gap-2 text-xs text-brand-600 border-t border-slate-200 dark:border-slate-800 pt-3">
                                        <i data-lucide="check-circle" class="w-3 h-3"></i> Applied from Company Salary Structure
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 4: BANK & PENSION -->
                        <div x-show="currentStep === 4">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Bank & Pension Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-2"><h4 class="text-sm font-bold text-brand-600 uppercase tracking-wide">Bank Details</h4></div>
                                <div><label class="form-label">Bank Name</label><input type="text" name="bank_name" x-model="formData.bank_name" class="form-input capitalize"></div>
                                <div><label class="form-label">Account Number</label><input type="text" name="account_number" x-model="formData.account_number" class="form-input"></div>
                                <div class="col-span-2"><label class="form-label">Account Name</label><input type="text" name="account_name" x-model="formData.account_name" class="form-input capitalize"></div>
                                
                                <div class="col-span-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-sm font-bold text-brand-600 uppercase tracking-wide">Pension</h4>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Enable Pension?</span>
                                            <input type="checkbox" name="has_pension" x-model="hasPension" class="w-5 h-5 text-brand-600 rounded">
                                        </label>
                                    </div>
                                </div>
                                <div x-show="hasPension" class="contents">
                                    <div><label class="form-label">PFA</label><input type="text" name="pension_pfa" x-model="formData.pension_pfa" class="form-input capitalize"></div>
                                    <div><label class="form-label">RSA Number</label><input type="text" name="pension_rsa" x-model="formData.pension_rsa" class="form-input"></div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 5: EDUCATION -->
                        <div x-show="currentStep === 5">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Educational History</h3>
                            <div class="space-y-6">
                                <template x-for="(edu, index) in educationList" :key="index">
                                    <div class="p-4 border border-slate-200 dark:border-slate-700 rounded-lg relative">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="col-span-2"><label class="form-label">Institution Name</label><input type="text" name="edu_institution[]" class="form-input capitalize" x-model="edu.institution"></div>
                                            <div><label class="form-label">Qualification</label><input type="text" name="edu_qualification[]" class="form-input capitalize" x-model="edu.qualification"></div>
                                            <div><label class="form-label">Year</label><input type="number" name="edu_year[]" class="form-input" x-model="edu.year"></div>
                                            <div class="col-span-2"><label class="form-label">Course</label><input type="text" name="edu_course[]" class="form-input capitalize" x-model="edu.course"></div>
                                            <div class="col-span-2 border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between">
                                                <div>
                                                    <label class="flex items-center gap-2 text-sm text-brand-600 cursor-pointer w-fit">
                                                        <i data-lucide="upload" class="w-4 h-4"></i> <span x-text="edu.newFileName ? edu.newFileName : (edu.certificate_path ? 'Change Certificate' : 'Upload Certificate')"></span>
                                                        <input type="file" name="edu_certificate[]" class="hidden" @change="edu.newFileName = $event.target.files[0].name" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                                        <!-- Hidden input to preserve existing path -->
                                                        <input type="hidden" name="edu_existing_path[]" :value="edu.certificate_path">
                                                    </label>
                                                    <p class="text-[10px] text-slate-400 mt-1">Supported: JPG, PNG, PDF, DOCX (Max 5MB)</p>
                                                </div>
                                                <template x-if="edu.certificate_path">
                                                    <span class="text-xs text-green-600 font-bold flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Uploaded</span>
                                                </template>
                                            </div>
                                        </div>
                                        <button type="button" @click="removeEducation(index)" class="absolute top-2 right-2 text-slate-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    </div>
                                </template>
                                <button type="button" @click="addEducation()" class="w-full py-2 border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 rounded-lg hover:border-brand-500 hover:text-brand-600 transition-colors flex items-center justify-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Add Another Education
                                </button>
                            </div>
                            <!-- Additional Qualifications Section -->
                            <div class="mt-8 border-t border-slate-100 dark:border-slate-800 pt-6">
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4 uppercase tracking-wide">Additional Certifications / Documents</h4>
                                <div class="space-y-6">
                                    <template x-for="(add, index) in additionalList" :key="index">
                                        <div class="p-4 border border-slate-200 dark:border-slate-700 rounded-lg relative bg-slate-50 dark:bg-slate-900/50">
                                            <div class="grid grid-cols-1 gap-4">
                                                <div><label class="form-label">Document Title</label><input type="text" name="additional_title[]" class="form-input capitalize" x-model="add.title" placeholder="e.g. Professional Certification"></div>
                                                <div class="border-t border-slate-200 dark:border-slate-700 pt-3 flex items-center justify-between">
                                                    <div>
                                                        <label class="flex items-center gap-2 text-sm text-brand-600 cursor-pointer w-fit">
                                                            <i data-lucide="upload" class="w-4 h-4"></i> 
                                                            <span x-text="add.newFileName ? add.newFileName : (add.document_path ? 'Change Document' : 'Upload Document')"></span>
                                                            <input type="file" name="additional_document[]" class="hidden" @change="add.newFileName = $event.target.files[0].name" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                                                            <!-- Hidden input to preserve existing path -->
                                                            <input type="hidden" name="additional_existing_path[]" :value="add.document_path">
                                                        </label>
                                                        <p class="text-[10px] text-slate-400 mt-1">JPG, PNG, PDF, DOC, XLS (Max 5MB)</p>
                                                    </div>
                                                    <template x-if="add.document_path">
                                                        <span class="text-xs text-green-600 font-bold flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Uploaded</span>
                                                    </template>
                                                </div>
                                            </div>
                                            <button type="button" @click="removeAdditional(index)" class="absolute top-2 right-2 text-slate-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </div>
                                    </template>
                                    <button type="button" @click="addAdditional()" class="w-full py-2 border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 rounded-lg hover:border-brand-500 hover:text-brand-600 transition-colors flex items-center justify-center gap-2">
                                        <i data-lucide="plus" class="w-4 h-4"></i> Add Another Document
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 6: WORK HISTORY -->
                        <div x-show="currentStep === 6">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Work History</h3>
                            <div class="space-y-6">
                                <template x-for="(work, index) in workList" :key="index">
                                    <div class="p-4 border border-slate-200 dark:border-slate-700 rounded-lg relative">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="col-span-2"><label class="form-label">Employer Name</label><input type="text" name="work_employer[]" class="form-input capitalize" x-model="work.employer"></div>
                                            <div><label class="form-label">Job Title</label><input type="text" name="work_role[]" class="form-input capitalize" x-model="work.role"></div>
                                            <div><label class="form-label">Duration</label><input type="text" name="work_duration[]" placeholder="e.g. 2019-2023" class="form-input" x-model="work.duration"></div>
                                            <div class="col-span-2 border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between">
                                                <div>
                                                    <label class="flex items-center gap-2 text-sm text-brand-600 cursor-pointer w-fit">
                                                        <i data-lucide="upload" class="w-4 h-4"></i> <span x-text="work.newFileName ? work.newFileName : (work.document_path ? 'Change Document' : 'Upload Document')"></span>
                                                        <input type="file" name="work_document[]" class="hidden" @change="work.newFileName = $event.target.files[0].name" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                                        <!-- Hidden input to preserve existing path -->
                                                        <input type="hidden" name="work_existing_path[]" :value="work.document_path">
                                                    </label>
                                                    <p class="text-[10px] text-slate-400 mt-1">Supported: JPG, PNG, PDF, DOCX (Max 5MB)</p>
                                                </div>
                                                <template x-if="work.document_path">
                                                    <span class="text-xs text-green-600 font-bold flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Uploaded</span>
                                                </template>
                                            </div>
                                        </div>
                                        <button type="button" @click="removeWork(index)" class="absolute top-2 right-2 text-slate-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    </div>
                                </template>
                                <button type="button" @click="addWork()" class="w-full py-2 border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 rounded-lg hover:border-brand-500 hover:text-brand-600 transition-colors flex items-center justify-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Add Another Job
                                </button>
                            </div>
                        </div>

                        <!-- STEP 7: GUARANTOR -->
                        <div x-show="currentStep === 7">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Guarantor Details</h3>
                            <div class="space-y-6">
                                <template x-for="(g, index) in guarantorList" :key="index">
                                    <div class="p-4 border border-slate-200 dark:border-slate-700 rounded-lg relative">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="col-span-2"><label class="form-label">Full Name</label><input type="text" name="guarantor_name[]" class="form-input capitalize" x-model="g.full_name"></div>
                                            <div><label class="form-label">Relationship</label><input type="text" name="guarantor_rel[]" class="form-input capitalize" x-model="g.relationship"></div>
                                            <div><label class="form-label">Phone</label><input type="tel" name="guarantor_phone[]" class="form-input" x-model="g.phone"></div>
                                            <div class="col-span-2"><label class="form-label">Address</label><input type="text" name="guarantor_address[]" class="form-input capitalize" x-model="g.address"></div>
                                            <div><label class="form-label">Occupation</label><input type="text" name="guarantor_job[]" class="form-input capitalize" x-model="g.occupation"></div>
                                            <div class="col-span-2 border-t border-slate-100 dark:border-slate-800 pt-3 flex items-center justify-between">
                                                <label class="flex items-center gap-2 text-sm text-brand-600 cursor-pointer w-fit">
                                                    <i data-lucide="upload" class="w-4 h-4"></i>
                                                    <!-- Dynamic Label -->
                                                    <span x-text="g.newFileName ? g.newFileName : (g.id_path ? 'Change ID' : 'Upload ID')"></span>
                                                    <input type="file" name="guarantor_id[]" class="hidden" 
                                                           @change="g.newFileName = $event.target.files[0]?.name">
                                                    <!-- Hidden input to preserve existing path -->
                                                    <input type="hidden" name="guarantor_existing_path[]" :value="g.id_path">
                                                </label>
                                                <template x-if="g.id_path && !g.newFileName">
                                                    <span class="text-xs text-green-600 font-bold flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Uploaded</span>
                                                </template>
                                            </div>
                                        </div>
                                        <button type="button" @click="removeGuarantor(index)" class="absolute top-2 right-2 text-slate-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                    </div>
                                </template>
                                <button type="button" @click="addGuarantor()" class="w-full py-2 border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-500 rounded-lg hover:border-brand-500 hover:text-brand-600 transition-colors flex items-center justify-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Add Another Guarantor
                                </button>
                            </div>
                        </div>

                        <!-- STEP 8: NEXT OF KIN -->
                        <div x-show="currentStep === 8">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Next of Kin</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-2"><label class="form-label">Full Name</label><input type="text" name="nok_name" x-model="formData.nok_name" class="form-input capitalize"></div>
                                <div><label class="form-label">Relationship</label><input type="text" name="nok_relationship" x-model="formData.nok_relationship" class="form-input capitalize"></div>
                                <div><label class="form-label">Phone</label><input type="tel" name="nok_phone" x-model="formData.nok_phone" class="form-input"></div>
                                <div class="col-span-2"><label class="form-label">Address</label><input type="text" name="nok_address" x-model="formData.nok_address" class="form-input capitalize"></div>
                            </div>
                        </div>

                        <!-- STEP 9: REVIEW -->
                        <div x-show="currentStep === 9">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Review & Activate</h3>
                            <div class="bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-900/30 rounded-lg p-4 mb-6 flex items-start gap-3">
                                <i data-lucide="check-circle" class="w-5 h-5 text-green-600 shrink-0 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-bold text-green-800 dark:text-green-400">Ready to <span x-text="formMode === 'create_employee' ? 'Onboard' : 'Update'"></span></p>
                                    <p class="text-xs text-green-700 dark:text-green-500">All required fields for payroll have been captured.</p>
                                </div>
                            </div>
                            <!-- Simple Review Summary -->
                            <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-lg text-sm text-center text-slate-500">
                                Please verify all details before submitting.
                            </div>
                        </div>

                        <!-- Wizard Controls -->
                        <div class="mt-8 flex justify-between pt-6 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" x-show="currentStep > 1" @click="currentStep--" class="px-6 py-2 border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 rounded-lg font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Previous</button>
                            <div x-show="currentStep === 1" class="flex-1"></div>
                            
                            <button type="button" x-show="currentStep < 9" @click="currentStep++" class="px-6 py-2 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg font-bold hover:opacity-90 transition-opacity ml-auto">Next Step</button>
                            
                            <button type="submit" x-show="currentStep === 9" class="px-6 py-2 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 transition-colors ml-auto shadow-lg shadow-brand-500/30" x-text="formMode === 'create_employee' ? 'Save & Activate' : 'Update Profile'"></button>
                        </div>
                    </form>
                </div>

                <!-- VIEW 3: EMPLOYEE PROFILE (Redesigned) -->
                <div x-show="view === 'profile'" x-cloak class="pb-20">
                    <!-- Back & Header -->
                    <div class="mb-6">
                        <button @click="view = 'list'" class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900 dark:hover:text-white mb-4 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Team
                        </button>
                        
                        <div class="bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col md:flex-row items-center md:items-start gap-6 shadow-sm">
                            <div class="relative group">
                                <div class="w-24 h-24 rounded-2xl bg-slate-100 flex items-center justify-center text-2xl font-bold text-slate-400 overflow-hidden border-2 border-slate-50 dark:border-slate-800 shadow-inner">
                                    <template x-if="selectedEmployee?.img">
                                        <img :src="selectedEmployee.img" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!selectedEmployee?.photo_path">
                                        <span x-text="selectedEmployee?.initials" :style="{ color: selectedEmployee?.color }"></span>
                                    </template>
                                </div>
                                <span class="absolute -bottom-2 -right-2 w-6 h-6 rounded-full border-2 border-white dark:border-slate-950 flex items-center justify-center" :class="selectedEmployee?.status === 'Active' ? 'bg-green-500' : 'bg-slate-400'">
                                    <i data-lucide="check" class="w-3 h-3 text-white" x-show="selectedEmployee?.status === 'Active'"></i>
                                </span>
                            </div>
                            
                            <div class="flex-1 text-center md:text-left">
                                <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight" x-text="selectedEmployee?.name"></h1>
                                <p class="text-slate-500 dark:text-slate-400 font-medium mt-1 flex items-center justify-center md:justify-start gap-2">
                                    <i data-lucide="briefcase" class="w-4 h-4"></i>
                                    <span x-text="selectedEmployee?.role"></span>
                                    <span class="text-slate-300">•</span>
                                    <span x-text="selectedEmployee?.department"></span>
                                </p>
                                <div class="flex items-center justify-center md:justify-start gap-3 mt-4">
                                    <span class="px-3 py-1 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 text-xs rounded-lg font-mono tracking-wide" x-text="selectedEmployee?.id"></span>
                                    <span class="px-3 py-1 text-xs rounded-full font-bold border" 
                                          :class="selectedEmployee?.status === 'Active' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200'" 
                                          x-text="selectedEmployee?.status"></span>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <button @click="editEmployee(selectedEmployee.id)" class="px-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-sm">
                                    Edit Profile
                                </button>
                                <button @click="deleteEmployee(selectedEmployee.id)" class="px-4 py-2 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 rounded-lg text-sm font-semibold hover:bg-red-100 dark:hover:bg-red-950/50 transition-colors shadow-sm flex items-center gap-2">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modern Tabs -->
                    <div class="bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm min-h-[500px]">
                        <div class="sticky top-0 z-10 bg-white/80 dark:bg-slate-950/80 backdrop-blur-md rounded-t-2xl border-b border-slate-100 dark:border-slate-800 p-2">
                            <div class="flex p-1 bg-slate-100/50 dark:bg-slate-900/50 rounded-xl overflow-x-auto gap-1">
                                <template x-for="tab in ['Overview', 'Payroll', 'Increments', 'Loans', 'Attendance', 'Documents']">
                                    <button @click="profileTab = tab.toLowerCase()" 
                                            :class="profileTab === tab.toLowerCase() ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200 dark:ring-slate-700' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-200/50 dark:hover:bg-slate-800/50'"
                                            class="flex-1 min-w-[100px] py-2 px-4 rounded-lg text-sm font-semibold transition-all duration-200 whitespace-nowrap"
                                            x-text="tab"></button>
                                </template>
                            </div>
                        </div>

                        <div class="p-6 md:p-8">
                            <!-- OVERVIEW TAB -->
                            <div x-show="profileTab === 'overview'" x-transition.opacity.duration.300ms>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <!-- Personal Info -->
                                    <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <i data-lucide="user" class="w-4 h-4 text-brand-500"></i> Personal Info
                                        </h3>
                                        <div class="space-y-4 text-sm">
                                            <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Full Name</span> <span class="col-span-2 font-medium text-slate-900 dark:text-white" x-text="selectedEmployee?.name"></span></div>
                                            <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Email</span> <span class="col-span-2 text-slate-900 dark:text-white" x-text="selectedEmployee?.email"></span></div>
                                            <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Phone</span> <span class="col-span-2 text-slate-900 dark:text-white" x-text="selectedEmployee?.phone || '-'"></span></div>
                                            <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Address</span> <span class="col-span-2 text-slate-900 dark:text-white" x-text="selectedEmployee?.address || '-'"></span></div>
                                            <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">DOB</span> <span class="col-span-2 text-slate-900 dark:text-white" x-text="selectedEmployee?.dob || '-'"></span></div>
                                        </div>
                                    </div>

                                    <!-- Next of Kin -->
                                    <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <i data-lucide="heart" class="w-4 h-4 text-red-500"></i> Next of Kin
                                        </h3>
                                        <template x-if="selectedEmployee?.nok">
                                            <div class="space-y-4 text-sm">
                                                <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Name</span> <span class="col-span-2 font-medium text-slate-900 dark:text-white" x-text="selectedEmployee.nok.full_name"></span></div>
                                                <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Relationship</span> <span class="col-span-2 text-slate-900 dark:text-white" x-text="selectedEmployee.nok.relationship"></span></div>
                                                <div class="grid grid-cols-3 gap-2"><span class="text-slate-500">Phone</span> <span class="col-span-2 text-slate-900 dark:text-white" x-text="selectedEmployee.nok.phone"></span></div>
                                            </div>
                                        </template>
                                        <template x-if="!selectedEmployee?.nok">
                                            <p class="text-slate-500 text-sm">No Next of Kin recorded.</p>
                                        </template>
                                    </div>

                                    <!-- Education -->
                                    <div class="md:col-span-2 bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <i data-lucide="graduation-cap" class="w-4 h-4 text-blue-500"></i> Education
                                        </h3>
                                        <div class="space-y-4">
                                            <template x-if="selectedEmployee?.education && selectedEmployee?.education.length">
                                                <template x-for="edu in selectedEmployee.education">
                                                    <div class="flex items-start gap-4 pb-4 border-b border-slate-200 dark:border-slate-800 last:border-0 last:pb-0">
                                                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                                            <span class="font-bold text-blue-600 dark:text-blue-400 text-xs" x-text="edu.year"></span>
                                                        </div>
                                                        <div>
                                                            <h4 class="font-bold text-slate-900 dark:text-white text-sm" x-text="edu.institution"></h4>
                                                            <p class="text-xs text-slate-500" x-text="edu.qualification + ' in ' + edu.course"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </template>
                                            <template x-if="!selectedEmployee?.education || !selectedEmployee?.education.length">
                                                <p class="text-slate-500 text-sm">No education history recorded.</p>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <!-- Guarantors -->
                                    <div class="md:col-span-2 bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i> Guarantors
                                        </h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <template x-if="selectedEmployee?.guarantors">
                                                <template x-for="g in selectedEmployee.guarantors">
                                                    <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                                        <p class="font-bold text-slate-900 dark:text-white text-sm" x-text="g.full_name"></p>
                                                        <p class="text-xs text-slate-500 space-x-2">
                                                            <span x-text="g.relationship"></span>
                                                            <span>•</span>
                                                            <span x-text="g.phone"></span>
                                                        </p>
                                                    </div>
                                                </template>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Work History (Overview) -->
                                    <div class="md:col-span-2 bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <i data-lucide="briefcase" class="w-4 h-4 text-slate-500"></i> Work History
                                        </h3>
                                        <div class="space-y-4">
                                            <template x-if="selectedEmployee?.work_history && selectedEmployee?.work_history.length">
                                                <template x-for="work in selectedEmployee.work_history">
                                                    <div class="flex items-start gap-4 pb-4 border-b border-slate-200 dark:border-slate-800 last:border-0 last:pb-0">
                                                        <div class="w-10 h-10 rounded-lg bg-slate-200 dark:bg-slate-700 flex items-center justify-center shrink-0 text-slate-500">
                                                            <i data-lucide="building-2" class="w-5 h-5"></i>
                                                        </div>
                                                        <div>
                                                            <h4 class="font-bold text-slate-900 dark:text-white text-sm" x-text="work.employer"></h4>
                                                            <p class="text-xs text-slate-500" x-text="work.role"></p>
                                                            <p class="text-[10px] text-slate-400 mt-0.5" x-text="work.duration"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </template>
                                            <template x-if="!selectedEmployee?.work_history || !selectedEmployee?.work_history.length">
                                                <p class="text-slate-500 text-sm">No work history recorded.</p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PAYROLL TAB -->
                            <div x-show="profileTab === 'payroll'" x-transition.opacity.duration.300ms>
                                <div class="max-w-xl mx-auto text-center py-10">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-6">
                                        <span class="text-2xl font-bold text-green-600 dark:text-green-400">₦</span>
                                    </div>
                                    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2" x-text="'₦ ' + Number(selectedEmployee?.gross || 0).toLocaleString()"></h2>
                                    <p class="text-slate-500 uppercase text-xs font-bold tracking-widest">Base Annual Gross Salary</p>
                                    
                                    <div class="mt-8 p-6 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
                                        <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4">Salary Breakdown (Estimates)</h4>
                                        <div class="space-y-3">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-slate-500">Basic Salary</span>
                                                <span class="font-mono text-slate-900 dark:text-white">~40%</span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-slate-500">Housing Allowance</span>
                                                <span class="font-mono text-slate-900 dark:text-white">~30%</span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-slate-500">Transport Allowance</span>
                                                <span class="font-mono text-slate-900 dark:text-white">~20%</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button class="mt-8 px-6 py-2 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 transition-colors shadow-lg shadow-brand-500/30">
                                        View Full Payslip History
                                    </button>
                                </div>
                            </div>
                            
                            <!-- INCREMENTS TAB -->
                            <div x-show="profileTab === 'increments'" x-transition.opacity.duration.300ms>
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Adjustments History</h3>
                                    <a href="increments.php" class="text-sm text-brand-600 font-medium hover:underline flex items-center gap-1">
                                        Manage / Download <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </a>
                                </div>
                                <template x-if="incrementHistory.length === 0">
                                    <div class="text-center py-12 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
                                        <i data-lucide="trending-up" class="w-8 h-8 text-slate-300 mx-auto mb-3"></i>
                                        <p class="text-slate-500 font-medium">No salary adjustments recorded.</p>
                                    </div>
                                </template>
                                <div class="space-y-4">
                                    <template x-for="inc in incrementHistory" :key="inc.id">
                                        <div class="flex items-center p-4 border border-slate-200 dark:border-slate-800 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-colors">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 mr-4"
                                                 :class="inc.type === 'Percentage' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'">
                                                <i :data-lucide="inc.type === 'Percentage' ? 'percent' : 'dollar-sign'" class="w-5 h-5"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex justify-between mb-1">
                                                    <h4 class="font-bold text-slate-900 dark:text-white text-sm" x-text="inc.reason || 'Increment Adjustment'"></h4>
                                                    <span class="text-xs px-2 py-0.5 rounded-full font-bold" 
                                                          :class="inc.status === 'Approved' ? 'bg-green-100 text-green-700' : (inc.status === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700')"
                                                          x-text="inc.status"></span>
                                                </div>
                                                <div class="flex justify-between text-xs text-slate-500">
                                                    <span>Effective: <span x-text="inc.effective_date"></span></span>
                                                    <span class="font-mono font-bold text-brand-600" x-text="inc.type === 'Percentage' ? '+' + inc.value + '%' : '₦' + Number(inc.value).toLocaleString()"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- LOANS TAB -->
                            <div x-show="profileTab === 'loans'" x-transition.opacity.duration.300ms>
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Loan History</h3>
                                    <a href="loans.php" class="text-sm text-brand-600 font-medium hover:underline flex items-center gap-1">
                                        Manage Loans <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </a>
                                </div>
                                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 font-medium border-b border-slate-100 dark:border-slate-800">
                                            <tr>
                                                <th class="p-4">Date</th>
                                                <th class="p-4">Type</th>
                                                <th class="p-4">Principal</th>
                                                <th class="p-4">Balance</th>
                                                <th class="p-4">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <template x-for="loan in selectedEmployee?.loans || []" :key="loan.id">
                                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                                    <td class="p-4 text-slate-600 dark:text-slate-400" x-text="new Date(loan.created_at).toLocaleDateString()"></td>
                                                    <td class="p-4 capitalize text-slate-900 dark:text-white">
                                                        <span x-text="loan.loan_type === 'other' ? loan.custom_type : loan.loan_type"></span>
                                                    </td>
                                                    <td class="p-4 font-mono font-medium text-slate-900 dark:text-white" x-text="'₦ ' + Number(loan.principal_amount).toLocaleString()"></td>
                                                    <td class="p-4 font-mono text-slate-500" x-text="'₦ ' + Number(loan.balance).toLocaleString()"></td>
                                                    <td class="p-4">
                                                        <span :class="{
                                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': loan.status === 'pending',
                                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': loan.status === 'approved' || loan.status === 'running',
                                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': loan.status === 'completed',
                                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': loan.status === 'rejected'
                                                        }" class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide" x-text="loan.status"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="!selectedEmployee?.loans?.length">
                                                <td colspan="5" class="p-12 text-center text-slate-500 italic">
                                                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                                                    No loan history found for this employee.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- OTHER PLACEHOLDERS -->
                            <!-- DOCUMENTS TAB -->
                            <div x-show="profileTab === 'documents'" x-transition.opacity.duration.300ms>
                                <div class="space-y-6">
                                    
                                    <!-- 1. Personal Documents -->
                                    <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                            <i data-lucide="folder-open" class="w-4 h-4 text-brand-500"></i> Personal Documents
                                        </h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Valid ID -->
                                            <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                                        <i data-lucide="file-badge" class="w-5 h-5"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-slate-900 dark:text-white text-sm">Valid Identification</p>
                                                        <p class="text-xs text-slate-500" x-text="selectedEmployee?.id_document_path ? 'Uploaded' : 'Not Uploaded'"></p>
                                                    </div>
                                                </div>
                                                <template x-if="selectedEmployee?.id_document_path">
                                                    <a :href="'../' + selectedEmployee.id_document_path" download target="_blank" class="px-3 py-1.5 text-xs font-bold text-brand-600 bg-brand-50 hover:bg-brand-100 rounded-md border border-brand-200 transition-colors">
                                                        View / Download
                                                    </a>
                                                </template>
                                            </div>
                                    <!-- 2. Profile Photo (Explicit Download) -->
                                    <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                                <i data-lucide="image" class="w-5 h-5"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-900 dark:text-white text-sm">Profile Photo</p>
                                                <p class="text-xs text-slate-500" x-text="selectedEmployee?.photo_path ? 'Uploaded' : 'Not Uploaded'"></p>
                                            </div>
                                        </div>
                                        <template x-if="selectedEmployee?.photo_path">
                                            <div class="flex gap-2">
                                                <a :href="'../' + selectedEmployee.photo_path" target="_blank" class="px-3 py-1.5 text-xs font-bold text-brand-600 bg-brand-50 hover:bg-brand-100 rounded-md border border-brand-200 transition-colors">
                                                    View
                                                </a>
                                                <a :href="'../' + selectedEmployee.photo_path" download target="_blank" class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-md border border-slate-200 transition-colors">
                                                    Download
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                        </div>
                                    </div>

                                    <!-- 2. Education Certificates -->
                                    <template x-if="selectedEmployee?.education && selectedEmployee.education.some(d => d.certificate_path)">
                                        <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                                <i data-lucide="graduation-cap" class="w-4 h-4 text-blue-500"></i> Education Certificates
                                            </h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <template x-for="edu in selectedEmployee.education">
                                                    <template x-if="edu.certificate_path">
                                                        <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                                                            <div class="flex items-center gap-3">
                                                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                                                    <i data-lucide="file-check" class="w-5 h-5"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="font-bold text-slate-900 dark:text-white text-sm" x-text="edu.institution"></p>
                                                                    <p class="text-xs text-slate-500" x-text="edu.qualification"></p>
                                                                </div>
                                                            </div>
                                                            <a :href="'../' + edu.certificate_path" download target="_blank" class="px-3 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md border border-blue-200 transition-colors">
                                                                Download
                                                            </a>
                                                        </div>
                                                    </template>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- 3. Work Documents -->
                                    <template x-if="selectedEmployee?.work_history && selectedEmployee.work_history.some(d => d.document_path)">
                                        <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                                <i data-lucide="briefcase" class="w-4 h-4 text-amber-500"></i> Employment Records
                                            </h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <template x-for="work in selectedEmployee.work_history">
                                                    <template x-if="work.document_path">
                                                        <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                                                            <div class="flex items-center gap-3">
                                                                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                                                                    <i data-lucide="file-text" class="w-5 h-5"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="font-bold text-slate-900 dark:text-white text-sm" x-text="work.employer"></p>
                                                                    <p class="text-xs text-slate-500" x-text="work.role"></p>
                                                                </div>
                                                            </div>
                                                            <a :href="'../' + work.document_path" download target="_blank" class="px-3 py-1.5 text-xs font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-md border border-amber-200 transition-colors">
                                                                Download
                                                            </a>
                                                        </div>
                                                    </template>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- 4. Additional Qualifications -->
                                    <template x-if="selectedEmployee?.additional_qualifications && selectedEmployee.additional_qualifications.length > 0">
                                        <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                                <i data-lucide="award" class="w-4 h-4 text-purple-500"></i> Additional Certifications
                                            </h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <template x-for="add in selectedEmployee.additional_qualifications">
                                                    <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
                                                                <i data-lucide="file-badge-2" class="w-5 h-5"></i>
                                                            </div>
                                                            <div>
                                                                <p class="font-bold text-slate-900 dark:text-white text-sm" x-text="add.title || 'Document'"></p>
                                                                <p class="text-xs text-slate-500">Uploaded</p>
                                                            </div>
                                                        </div>
                                                        <a :href="'../' + add.document_path" download target="_blank" class="px-3 py-1.5 text-xs font-bold text-purple-600 bg-purple-50 hover:bg-purple-100 rounded-md border border-purple-200 transition-colors">
                                                            Download
                                                        </a>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <!-- 5. Guarantor Details & IDs -->
                                    <template x-if="selectedEmployee?.guarantors && selectedEmployee.guarantors.length > 0">
                                        <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800 mb-6">
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                                <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i> Guarantors
                                            </h3>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <template x-for="g in selectedEmployee.guarantors">
                                                    <div class="p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                                        <div class="flex justify-between items-start mb-3">
                                                            <div class="flex items-center gap-3">
                                                                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 dark:text-green-400">
                                                                    <i data-lucide="user-check" class="w-5 h-5"></i>
                                                                </div>
                                                                <div>
                                                                    <p class="font-bold text-slate-900 dark:text-white text-sm" x-text="g.full_name || g.name"></p>
                                                                    <p class="text-xs text-slate-500" x-text="g.relationship"></p>
                                                                </div>
                                                            </div>
                                                            <template x-if="g.id_path">
                                                                <a :href="'../' + g.id_path" target="_blank" class="px-2 py-1 text-xs font-bold text-green-600 bg-green-50 hover:bg-green-100 rounded border border-green-200 transition-colors">
                                                                    ID Card
                                                                </a>
                                                            </template>
                                                        </div>
                                                        <div class="space-y-1 pl-13"> 
                                                            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400" x-show="g.phone">
                                                                <i data-lucide="phone" class="w-3 h-3 text-slate-400"></i>
                                                                <span x-text="g.phone"></span>
                                                            </div>
                                                            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400" x-show="g.address">
                                                                <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                                                <span x-text="g.address"></span>
                                                            </div>
                                                            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400" x-show="g.occupation">
                                                                <i data-lucide="briefcase" class="w-3 h-3 text-slate-400"></i>
                                                                <span x-text="g.occupation"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- 5. Work History (Requested Feature) -->
                                    <template x-if="selectedEmployee?.work_history && selectedEmployee.work_history.length > 0">
                                        <div class="bg-slate-50 dark:bg-slate-900/30 rounded-xl p-6 border border-slate-100 dark:border-slate-800">
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                                                <i data-lucide="briefcase" class="w-4 h-4 text-blue-500"></i> Work History
                                            </h3>
                                            <div class="space-y-3">
                                                <template x-for="work in selectedEmployee.work_history">
                                                    <div class="flex items-center justify-between p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                                                <i data-lucide="building-2" class="w-5 h-5"></i>
                                                            </div>
                                                            <div>
                                                                <p class="font-bold text-slate-900 dark:text-white text-sm" x-text="work.employer"></p>
                                                                <p class="text-xs text-slate-500"><span x-text="work.role"></span> • <span x-text="work.duration"></span></p>
                                                            </div>
                                                        </div>
                                                        <template x-if="work.document_path">
                                                            <a :href="'../' + work.document_path" target="_blank" class="px-3 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md border border-blue-200 transition-colors">
                                                                View Doc
                                                            </a>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>


    <script>
        lucide.createIcons();

        // Auto-Sentence Case
        document.addEventListener('input', function(e) {
            if (e.target.matches('.capitalize, .form-input:not([type="email"]):not([type="date"]):not([type="file"])')) {
                if (e.target.value.length > 0) {
                    e.target.value = e.target.value.charAt(0).toUpperCase() + e.target.value.slice(1);
                }
            }
        });

        // Theme Toggle Logic
        // ... (Keep existing theme toggle) ...
        const themeBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        if(themeBtn) {
            themeBtn.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
            });
        }


        
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const collapsedToolbar = document.getElementById('collapsed-toolbar');
        const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');

        if(mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        function toggleSidebar() {
            if(!sidebar) return;
            sidebar.classList.toggle('w-64');
            sidebar.classList.toggle('w-0');
            sidebar.classList.toggle('p-0'); 
            
            if(collapsedToolbar) {
                if(sidebar.classList.contains('w-0')) {
                    collapsedToolbar.classList.remove('toolbar-hidden');
                    collapsedToolbar.classList.add('toolbar-visible');
                } else {
                    collapsedToolbar.classList.add('toolbar-hidden');
                    collapsedToolbar.classList.remove('toolbar-visible');
                }
            }
        }

        if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);
    </script>
</body>
</html>
