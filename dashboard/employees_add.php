<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$success_msg = '';
$error_msg = '';

$employee = null;
$next_of_kin = null;
$is_edit = false;

// Fetch Categories for Dropdown
// Fetch Departments for Dropdown
$stmt = $pdo->prepare("SELECT id, name FROM departments WHERE company_id = ? AND is_active = 1 ORDER BY name ASC");
$stmt->execute([$company_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories for Dropdown
$stmt = $pdo->prepare("SELECT id, name as title FROM salary_categories WHERE company_id = ?");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll();

// Check if Edit Mode
if (isset($_GET['id'])) {
    $is_edit = true;
    $emp_id = $_GET['id'];
    
    // Fetch Employee
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ?");
    $stmt->execute([$emp_id, $company_id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        redirect('employees.php');
    }
    
    // Fetch Next of Kin
    $stmt = $pdo->prepare("SELECT * FROM next_of_kin WHERE employee_id = ?");
    $stmt->execute([$emp_id]);
    $next_of_kin = $stmt->fetch();
}

// Get next Employee ID preview for new employees
$next_employee_id = '';
if (!$is_edit) {
    $next_employee_id = generate_employee_id($company_id, false); // Preview, don't increment
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Biodata
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $gender = clean_input($_POST['gender']);
    $address = clean_input($_POST['address']);
    
    // 2. Employment
    $dept_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $salary_cat = !empty($_POST['salary_category_id']) ? $_POST['salary_category_id'] : null;
    $status = clean_input($_POST['employment_status']);
    $join_date = $_POST['date_of_joining'];
    
    // 3. Banking
    $bank_name = clean_input($_POST['bank_name']);
    $acc_num = clean_input($_POST['account_number']);
    $acc_name = clean_input($_POST['account_name']);
    $pfa = clean_input($_POST['pension_pfa']);
    $rsa = clean_input($_POST['pension_rsa']);

    // Generate Payroll ID if new (using company's ID format)
    if ($is_edit) {
        $payroll_id = $employee['payroll_id'];
    } else {
        $payroll_id = generate_employee_id($company_id, true); // Generate and increment counter
    }

    try {
        $pdo->beginTransaction();

        if ($is_edit) {
            $stmt = $pdo->prepare("UPDATE employees SET 
                department_id=?, salary_category_id=?, first_name=?, last_name=?, email=?, phone=?, gender=?, address=?, 
                employment_status=?, date_of_joining=?, bank_name=?, account_number=?, account_name=?, 
                pension_pfa=?, pension_rsa=? 
                WHERE id=? AND company_id=?");
            $stmt->execute([
                $dept_id, $salary_cat, $first_name, $last_name, $email, $phone, $gender, $address,
                $status, $join_date, $bank_name, $acc_num, $acc_name, $pfa, $rsa,
                $emp_id, $company_id
            ]);
            $employee_id = $emp_id;
            log_audit($company_id, $_SESSION['user_id'], 'UPDATE_EMPLOYEE', "Updated employee details: $first_name $last_name (ID: $emp_id)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO employees 
                (company_id, department_id, salary_category_id, payroll_id, first_name, last_name, email, phone, gender, address, 
                employment_status, date_of_joining, bank_name, account_number, account_name, pension_pfa, pension_rsa) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $company_id, $dept_id, $salary_cat, $payroll_id, $first_name, $last_name, $email, $phone, $gender, $address,
                $status, $join_date, $bank_name, $acc_num, $acc_name, $pfa, $rsa
            ]);
            $employee_id = $pdo->lastInsertId();
            log_audit($company_id, $_SESSION['user_id'], 'CREATE_EMPLOYEE', "Created new employee: $first_name $last_name (ID: $employee_id)");
        }

        // NEXT OF KIN Handing
        $nok_name = clean_input($_POST['nok_name']);
        $nok_rel = clean_input($_POST['nok_relationship']);
        $nok_phone = clean_input($_POST['nok_phone']);

        if ($nok_name) {
            if ($next_of_kin) {
                // Update
                $stmt = $pdo->prepare("UPDATE next_of_kin SET full_name=?, relationship=?, phone=? WHERE employee_id=?");
                $stmt->execute([$nok_name, $nok_rel, $nok_phone, $employee_id]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO next_of_kin (employee_id, full_name, relationship, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$employee_id, $nok_name, $nok_rel, $nok_phone]);
            }
        }

        $pdo->commit();
        $success_msg = "Employee record saved successfully.";
        
        // Refresh data if edit
        if($is_edit) {
            // Re-fetch handled at top of page on reload, but update local var for display now
             $employee['first_name'] = $first_name; // basic patch
        } else {
             redirect("employees_add.php?id=$employee_id");
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

$current_page = 'employees';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Employee - MiPayMaster</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .form-section { margin-bottom: 2rem; }
        .form-section h3 { font-size: 1.1rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary-color); }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden">

<!-- Standard Layout -->
<div class="flex h-screen w-full">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        
        <!-- Header -->
        <!-- Header -->
        <?php $page_title = $is_edit ? "Edit Employee" : "Onboard New Employee"; include '../includes/dashboard_header.php'; ?>

        <!-- Collapsed Toolbar -->
        <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
            <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
            </button>
        </div>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8">

        <div class="container" style="padding-top: 2rem; max-width: 900px; margin-left: 0;">
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="card">
                    
                    <!-- Section 1: Personal Info -->
                    <div class="form-section">
                        <h3>Personal Information</h3>
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-input" required value="<?php echo $employee['first_name'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-input" required value="<?php echo $employee['last_name'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" required value="<?php echo $employee['email'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-input" value="<?php echo $employee['phone'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-input">
                                    <option value="Male" <?php echo ($employee['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($employee['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:2;">
                                <label class="form-label">Residential Address</label>
                                <input type="text" name="address" class="form-input" value="<?php echo $employee['address'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Employment -->
                    <div class="form-section">
                        <h3>Employment Details</h3>
                        
                        <!-- Employee ID Display -->
                        <div class="flex gap-4 mb-4">
                            <div class="form-group" style="flex:1; max-width: 300px;">
                                <label class="form-label">Employee ID</label>
                                <input type="text" 
                                    class="form-input bg-slate-100 dark:bg-slate-800 font-mono font-bold text-brand-600" 
                                    value="<?php echo $is_edit ? htmlspecialchars($employee['payroll_id']) : htmlspecialchars($next_employee_id); ?>" 
                                    readonly 
                                    title="<?php echo $is_edit ? 'Employee ID cannot be changed' : 'This ID will be assigned on save'; ?>">
                                <?php if (!$is_edit): ?>
                                <p class="text-xs text-slate-500 mt-1">This ID will be auto-assigned when saved</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-input" required>
                                    <option value="">Select Department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo ($employee['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Salary Category</label>
                                <select name="salary_category_id" class="form-input" required>
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($employee['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Status</label>
                                <select name="employment_status" class="form-input">
                                    <option value="Full Time" <?php echo ($employee['employment_status'] ?? '') == 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                                    <option value="Contract" <?php echo ($employee['employment_status'] ?? '') == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="Intern" <?php echo ($employee['employment_status'] ?? '') == 'Intern' ? 'selected' : ''; ?>>Intern</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" name="date_of_joining" class="form-input" value="<?php echo $employee['date_of_joining'] ?? date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- Section 3: Banking -->
                    <div class="form-section">
                        <h3>Financial Details</h3>
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-input" placeholder="e.g. GTBank" value="<?php echo $employee['bank_name'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" class="form-input" value="<?php echo $employee['account_number'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Account Name</label>
                                <input type="text" name="account_name" class="form-input" value="<?php echo $employee['account_name'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Pension PFA</label>
                                <input type="text" name="pension_pfa" class="form-input" placeholder="e.g. Stanbic IBTC Pension" value="<?php echo $employee['pension_pfa'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">RSA Number</label>
                                <input type="text" name="pension_rsa" class="form-input" placeholder="PEN123456789" value="<?php echo $employee['pension_rsa'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Next of Kin -->
                    <div class="form-section">
                        <h3>Next of Kin</h3>
                        <div class="flex gap-4">
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="nok_name" class="form-input" value="<?php echo $next_of_kin['full_name'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Relationship</label>
                                <input type="text" name="nok_relationship" class="form-input" placeholder="e.g. Spouse" value="<?php echo $next_of_kin['relationship'] ?? ''; ?>">
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label class="form-label">Phone</label>
                                <input type="text" name="nok_phone" class="form-input" value="<?php echo $next_of_kin['phone'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Employee Record</button>
                        <a href="employees.php" class="btn btn-outline">Cancel</a>
                    </div>
                </div>

            </form>

        </div>
        </main>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
