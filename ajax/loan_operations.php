<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enable error reporting for debug (but return JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Debug Log Function
function log_loan_debug($message) {
    $log_file = __DIR__ . '/../loans_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

if (!is_logged_in()) {
    log_loan_debug("Unauthorized access attempt");
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? '';

log_loan_debug("Action: $action | User ID: $user_id | Role: $role | Company ID: $company_id");

// ACTION: CREATE LOAN
if ($action === 'create_loan') {
    try {
        log_loan_debug("CREATE_LOAN: POST data = " . json_encode($_POST));
        
        // Step 1: Resolve Employee ID
        $emp_id = $_POST['employee_id'] ?? null;
        
        if ($role === 'employee') {
            log_loan_debug("CREATE_LOAN: Role is 'employee', fetching employee ID from user_id");
            $stmt_e = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND company_id = ?");
            $stmt_e->execute([$user_id, $company_id]);
            $my_emp_id = $stmt_e->fetchColumn();
            
            if (!$my_emp_id) {
                log_loan_debug("CREATE_LOAN: ERROR - No employee record found for user_id: $user_id");
                throw new Exception("Employee record not found for your account.");
            }
            
            $emp_id = $my_emp_id;
            log_loan_debug("CREATE_LOAN: Resolved employee_id to: $emp_id");
        }
        
        // Step 2: Validate Employee ID
        if (empty($emp_id)) {
            log_loan_debug("CREATE_LOAN: ERROR - Employee ID is missing");
            throw new Exception("Employee ID is required.");
        }
        
        // Verify employee exists and is active
        $stmt_verify = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE id = ? AND company_id = ? AND employment_status = 'active'");
        $stmt_verify->execute([$emp_id, $company_id]);
        $employee = $stmt_verify->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            log_loan_debug("CREATE_LOAN: ERROR - Employee ID $emp_id not found or inactive");
            throw new Exception("Employee not found or inactive.");
        }
        
        log_loan_debug("CREATE_LOAN: Employee verified: {$employee['first_name']} {$employee['last_name']}");
        
        // Step 3: Validate Loan Type
        $type = $_POST['loan_type'] ?? '';
        if (empty($type)) {
            log_loan_debug("CREATE_LOAN: ERROR - Loan type is missing");
            throw new Exception("Loan type is required.");
        }
        log_loan_debug("CREATE_LOAN: Loan type: $type");
        
        $custom_type = null;
        if ($type === 'other') {
            $custom_type = trim($_POST['custom_type'] ?? '');
            if (empty($custom_type)) {
                log_loan_debug("CREATE_LOAN: ERROR - Custom type required for 'other' loan type");
                throw new Exception("Custom loan type description is required.");
            }
            log_loan_debug("CREATE_LOAN: Custom type: $custom_type");
        }
        
        // Step 4: Validate Amounts
        $amount = floatval($_POST['principal_amount'] ?? 0);
        if ($amount <= 0) {
            log_loan_debug("CREATE_LOAN: ERROR - Principal amount is invalid: $amount");
            throw new Exception("Principal amount must be greater than zero.");
        }
        log_loan_debug("CREATE_LOAN: Principal amount: $amount");
        
        $repayment = floatval($_POST['repayment_amount'] ?? 0);
        if ($repayment <= 0) {
            log_loan_debug("CREATE_LOAN: ERROR - Repayment amount is invalid: $repayment");
            throw new Exception("Repayment amount must be greater than zero.");
        }
        log_loan_debug("CREATE_LOAN: Repayment amount: $repayment");
        
        // Step 5: Validate Start Period
        $start_month = intval($_POST['start_month'] ?? 0);
        if ($start_month < 1 || $start_month > 12) {
            log_loan_debug("CREATE_LOAN: ERROR - Invalid start month: $start_month");
            throw new Exception("Invalid start month.");
        }
        
        $start_year = intval($_POST['start_year'] ?? 0);
        if ($start_year < 2020 || $start_year > 2100) {
            log_loan_debug("CREATE_LOAN: ERROR - Invalid start year: $start_year");
            throw new Exception("Invalid start year.");
        }
        log_loan_debug("CREATE_LOAN: Start period: $start_month/$start_year");
        
        // Step 6: Calculate Interest
        $interest_rate = floatval($_POST['interest_rate'] ?? 0);
        $interest_amount = 0;
        if ($interest_rate > 0) {
            $interest_amount = $amount * ($interest_rate / 100);
        }
        $total_balance = $amount + $interest_amount;
        log_loan_debug("CREATE_LOAN: Interest rate: $interest_rate%, Interest amount: $interest_amount, Total balance: $total_balance");
        
        // Step 7: File Upload (Optional)
        $doc_path = null;
        if (isset($_FILES['loan_doc']) && $_FILES['loan_doc']['error'] === UPLOAD_ERR_OK) {
            $allow = ['pdf', 'jpg', 'png', 'jpeg'];
            $fn = $_FILES['loan_doc']['name'];
            $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allow)) {
                log_loan_debug("CREATE_LOAN: ERROR - Invalid file type: $ext");
                throw new Exception("Invalid file type. Only PDF, JPG, PNG allowed.");
            }
            
            if ($_FILES['loan_doc']['size'] > 5242880) { // 5MB
                log_loan_debug("CREATE_LOAN: ERROR - File too large");
                throw new Exception("File size exceeds 5MB limit.");
            }
            
            $new_fn = "loan_doc_{$emp_id}_" . time() . ".$ext";
            $target = "../uploads/loans/";
            if (!is_dir($target)) mkdir($target, 0777, true);
            
            if (move_uploaded_file($_FILES['loan_doc']['tmp_name'], $target . $new_fn)) {
                $doc_path = $new_fn;
                log_loan_debug("CREATE_LOAN: Document uploaded: $new_fn");
            } else {
                log_loan_debug("CREATE_LOAN: WARNING - File upload failed");
            }
        }
        
        // Step 8: Insert Loan Record
        $stmt = $pdo->prepare("INSERT INTO loans (company_id, employee_id, loan_type, custom_type, principal_amount, repayment_amount, balance, start_month, start_year, status, document_path, interest_rate, interest_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
        
        $result = $stmt->execute([
            $company_id, 
            $emp_id, 
            $type, 
            $custom_type, 
            $amount, 
            $repayment, 
            $total_balance, 
            $start_month, 
            $start_year, 
            $doc_path, 
            $interest_rate, 
            $interest_amount
        ]);
        
        if (!$result) {
            $error_info = $stmt->errorInfo();
            log_loan_debug("CREATE_LOAN: ERROR - Database insert failed: " . json_encode($error_info));
            throw new Exception("Database error: " . $error_info[2]);
        }
        
        $loan_id = $pdo->lastInsertId();
        log_loan_debug("CREATE_LOAN: SUCCESS - Loan ID: $loan_id created for Employee ID: $emp_id");
        
        log_audit($company_id, $user_id, 'CREATE_LOAN', "Created loan request ID: $loan_id for Employee ID $emp_id: ₦" . number_format($amount) . " (Interest: $interest_rate%)");
        
        echo json_encode(['status' => true, 'message' => 'Loan request created successfully.', 'loan_id' => $loan_id]);
    
    } catch (Exception $e) {
        log_loan_debug("CREATE_LOAN: EXCEPTION - " . $e->getMessage());
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
}

// ACTION: FETCH LOANS
elseif ($action === 'fetch_loans') {
    $filter = $_POST['filter'] ?? 'all';
    log_loan_debug("FETCH_LOANS: Filter = $filter");
    
    $sql = "SELECT l.*, e.first_name, e.last_name 
            FROM loans l 
            JOIN employees e ON l.employee_id = e.id 
            WHERE l.company_id = ?";
    
    $params = [$company_id];
    
    if ($filter === 'pending') {
        $sql .= " AND l.status = 'pending'";
    } elseif ($filter === 'active') {
        $sql .= " AND l.status IN ('approved') AND l.balance > 0";
    } elseif ($filter === 'completed') {
        $sql .= " AND (l.status = 'completed' OR (l.status = 'approved' AND l.balance <= 0))";
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    log_loan_debug("FETCH_LOANS: Retrieved " . count($loans) . " loan(s)");
    
    echo json_encode(['status' => true, 'loans' => $loans]);
}

// ACTION: APPROVE LOAN
elseif ($action === 'approve_loan') {
    $loan_id = $_POST['loan_id'] ?? null;
    try {
        if (empty($loan_id)) {
            throw new Exception("Loan ID is required.");
        }
        
        $stmt = $pdo->prepare("UPDATE loans SET status='approved', approved_at=NOW(), approved_by=? WHERE id=? AND company_id=?");
        $stmt->execute([$user_id, $loan_id, $company_id]);
        
        log_loan_debug("APPROVE_LOAN: Loan ID $loan_id approved by User ID $user_id");
        log_audit($company_id, $user_id, 'APPROVE_LOAN', "Approved Loan ID: $loan_id");
        
        echo json_encode(['status' => true, 'message' => 'Loan approved.']);
    } catch (Exception $e) {
        log_loan_debug("APPROVE_LOAN: ERROR - " . $e->getMessage());
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
}

// ACTION: REJECT LOAN
elseif ($action === 'reject_loan') {
    $loan_id = $_POST['loan_id'] ?? null;
    try {
        if (empty($loan_id)) {
            throw new Exception("Loan ID is required.");
        }
        
        $stmt = $pdo->prepare("UPDATE loans SET status='rejected' WHERE id=? AND company_id=?");
        $stmt->execute([$loan_id, $company_id]);
        
        log_loan_debug("REJECT_LOAN: Loan ID $loan_id rejected by User ID $user_id");
        log_audit($company_id, $user_id, 'REJECT_LOAN', "Rejected Loan ID: $loan_id");
        
        echo json_encode(['status' => true, 'message' => 'Loan rejected.']);
    } catch (Exception $e) {
        log_loan_debug("REJECT_LOAN: ERROR - " . $e->getMessage());
        echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    }
}

else {
    log_loan_debug("ERROR: Unknown action: $action");
    echo json_encode(['status' => false, 'message' => 'Unknown action.']);
}
?>
