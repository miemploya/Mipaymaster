<?php
/**
 * Employee Relations AJAX Operations
 * Handles cases, messages, and notifications for HR/Staff communication
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

global $pdo;

// For employees, lookup employee.id from the users table link
$user_id = null;
if ($role === 'employee') {
    // Staff: lookup employee_id by user_id
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND company_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['company_id']]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $emp ? $emp['id'] : null;
} else {
    $user_id = $_SESSION['user_id'];
}

try {
    
    // ========================================
    // STAFF ACTIONS - Create/View Cases
    // ========================================
    
    // Create a new case (Staff)
    if ($action === 'create_case') {
        if (!$user_id) throw new Exception("Employee not identified.");
        
        $case_type = $_POST['case_type'] ?? 'inquiry';
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        
        if (empty($subject) || empty($description)) {
            throw new Exception("Subject and description are required.");
        }
        
        // Handle file attachment
        $attachment_path = null;
        $attachment_name = null;
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_type = $finfo->file($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only PDF, JPG, PNG allowed.");
            }
            if ($file['size'] > $max_size) {
                throw new Exception("File too large. Maximum 2MB allowed.");
            }
            
            $upload_dir = __DIR__ . '/../uploads/messages/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_name = 'case_' . time() . '_' . uniqid() . '.' . $ext;
            $target_path = $upload_dir . $unique_name;
            
            if (in_array($file_type, ['image/jpeg', 'image/png'])) {
                if ($file_type === 'image/jpeg') {
                    $img = imagecreatefromjpeg($file['tmp_name']);
                    imagejpeg($img, $target_path, 50);
                    imagedestroy($img);
                } else {
                    $img = imagecreatefrompng($file['tmp_name']);
                    imagesavealpha($img, true);
                    imagepng($img, $target_path, 5);
                    imagedestroy($img);
                }
            } else {
                move_uploaded_file($file['tmp_name'], $target_path);
            }
            
            $attachment_path = 'uploads/messages/' . $unique_name;
            $attachment_name = $file['name'];
        }
        
        // Generate case number
        $case_number = 'CASE-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $stmt = $pdo->prepare("
            INSERT INTO employee_cases (company_id, employee_id, case_number, case_type, subject, description, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$company_id, $user_id, $case_number, $case_type, $subject, $description, $priority]);
        $case_id = $pdo->lastInsertId();
        
        // Add initial message from employee
        $stmt = $pdo->prepare("
            INSERT INTO employee_case_messages (case_id, sender_id, sender_role, message, attachment_path, attachment_name)
            VALUES (?, ?, 'employee', ?, ?, ?)
        ");
        $stmt->execute([$case_id, $user_id, $description, $attachment_path, $attachment_name]);
        
        // Create notification for HR
        $stmt = $pdo->prepare("
            INSERT INTO hr_notifications (company_id, notification_type, reference_type, reference_id, title, message)
            VALUES (?, 'new_case', 'case', ?, ?, ?)
        ");
        $stmt->execute([$company_id, $case_id, "New Case: $subject", "A new $case_type has been submitted."]);
        
        echo json_encode(['status' => true, 'message' => 'Case submitted successfully.', 'case_number' => $case_number]);
    }
    
    // Get my cases (Staff)
    elseif ($action === 'get_my_cases') {
        if (!$user_id) throw new Exception("Employee not identified.");
        
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM employee_case_messages WHERE case_id = c.id AND is_internal = 0) as message_count
            FROM employee_cases c
            WHERE c.employee_id = ? AND c.company_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id, $company_id]);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'cases' => $cases]);
    }
    
    // Get case detail with messages (Staff or HR)
    elseif ($action === 'get_case_detail') {
        $case_id = intval($_POST['case_id'] ?? 0);
        if (!$case_id) throw new Exception("Case ID required.");
        
        // Fetch case
        $stmt = $pdo->prepare("
            SELECT c.*, e.first_name, e.last_name, e.email, e.payroll_id as emp_code
            FROM employee_cases c
            JOIN employees e ON c.employee_id = e.id
            WHERE c.id = ? AND c.company_id = ?
        ");
        $stmt->execute([$case_id, $company_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) throw new Exception("Case not found.");
        
        // Check access: staff can only see their own cases
        $is_hr = in_array($role, ['super_admin', 'company_admin', 'hr_manager']);
        if (!$is_hr && $case['employee_id'] != $user_id) {
            throw new Exception("Access denied.");
        }
        
        // Fetch messages (exclude internal if staff)
        $sql = "SELECT m.*, 
                       CASE WHEN m.sender_role = 'employee' THEN e.first_name ELSE 'HR Team' END as sender_name
                FROM employee_case_messages m
                LEFT JOIN employees e ON m.sender_id = e.id AND m.sender_role = 'employee'
                WHERE m.case_id = ?";
        if (!$is_hr) {
            $sql .= " AND m.is_internal = 0";
        }
        $sql .= " ORDER BY m.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$case_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'case' => $case, 'messages' => $messages]);
    }
    
    // Reply to case (Staff or HR) - with attachment support
    elseif ($action === 'reply_case') {
        $case_id = intval($_POST['case_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $message_title = trim($_POST['message_title'] ?? '');
        $is_internal = intval($_POST['is_internal'] ?? 0);
        
        if (!$case_id || empty($message)) {
            throw new Exception("Case ID and message required.");
        }
        
        // Verify case access
        $stmt = $pdo->prepare("SELECT * FROM employee_cases WHERE id = ? AND company_id = ?");
        $stmt->execute([$case_id, $company_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) throw new Exception("Case not found.");
        
        $is_hr = in_array($role, ['super_admin', 'company_admin', 'hr_manager']);
        $sender_role = $is_hr ? 'hr' : 'employee';
        
        // Staff can only reply to their own cases
        if (!$is_hr && $case['employee_id'] != $user_id) {
            throw new Exception("Access denied.");
        }
        
        // Staff cannot send internal notes
        if (!$is_hr) $is_internal = 0;
        
        // Handle file attachment
        $attachment_path = null;
        $attachment_name = null;
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Validate type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_type = $finfo->file($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only PDF, JPG, PNG allowed.");
            }
            
            // Validate size
            if ($file['size'] > $max_size) {
                throw new Exception("File too large. Maximum 2MB allowed.");
            }
            
            // Create uploads directory
            $upload_dir = __DIR__ . '/../uploads/messages/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_name = 'msg_' . time() . '_' . uniqid() . '.' . $ext;
            $target_path = $upload_dir . $unique_name;
            
            // Compress images (50% quality)
            if (in_array($file_type, ['image/jpeg', 'image/png'])) {
                if ($file_type === 'image/jpeg') {
                    $img = imagecreatefromjpeg($file['tmp_name']);
                    imagejpeg($img, $target_path, 50); // 50% quality
                    imagedestroy($img);
                } elseif ($file_type === 'image/png') {
                    $img = imagecreatefrompng($file['tmp_name']);
                    // For PNG, use compression level 5 (0-9)
                    imagesavealpha($img, true);
                    imagepng($img, $target_path, 5);
                    imagedestroy($img);
                }
            } else {
                // PDF - just move
                move_uploaded_file($file['tmp_name'], $target_path);
            }
            
            $attachment_path = 'uploads/messages/' . $unique_name;
            $attachment_name = $file['name'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO employee_case_messages (case_id, message_title, sender_id, sender_role, message, attachment_path, attachment_name, is_internal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$case_id, $message_title ?: null, $user_id, $sender_role, $message, $attachment_path, $attachment_name, $is_internal]);
        
        // Update case status if HR is replying
        if ($is_hr && $case['status'] === 'open') {
            $pdo->prepare("UPDATE employee_cases SET status = 'in_review' WHERE id = ?")->execute([$case_id]);
        }
        
        // Create notification
        if ($is_hr && !$is_internal) {
            // Notify employee (future: could send email)
        } else if (!$is_hr) {
            // Notify HR
            $stmt = $pdo->prepare("
                INSERT INTO hr_notifications (company_id, notification_type, reference_type, reference_id, title, message)
                VALUES (?, 'case_reply', 'case', ?, ?, ?)
            ");
            $stmt->execute([$company_id, $case_id, "Reply: {$case['subject']}", "Staff replied to case {$case['case_number']}"]);
        }
        
        echo json_encode(['status' => true, 'message' => 'Reply sent successfully.']);
    }
    
    // Acknowledge a message (Staff or HR)
    elseif ($action === 'acknowledge_message') {
        $message_id = intval($_POST['message_id'] ?? 0);
        if (!$message_id) throw new Exception("Message ID required.");
        
        // Get the message and case
        $stmt = $pdo->prepare("
            SELECT m.*, c.employee_id, c.company_id
            FROM employee_case_messages m
            JOIN employee_cases c ON m.case_id = c.id
            WHERE m.id = ?
        ");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$message) throw new Exception("Message not found.");
        if ($message['company_id'] != $company_id) throw new Exception("Unauthorized.");
        
        // Check permission: staff can acknowledge HR messages, HR can acknowledge staff messages
        $is_hr = in_array($role, ['super_admin', 'company_admin', 'hr_manager']);
        $can_acknowledge = false;
        
        if (!$is_hr && $message['employee_id'] == $user_id && $message['sender_role'] == 'hr') {
            // Staff acknowledging HR message
            $can_acknowledge = true;
        } elseif ($is_hr && $message['sender_role'] == 'employee') {
            // HR acknowledging staff message
            $can_acknowledge = true;
        }
        
        if (!$can_acknowledge) {
            throw new Exception("You cannot acknowledge this message.");
        }
        
        // Update acknowledged_at timestamp
        $stmt = $pdo->prepare("UPDATE employee_case_messages SET acknowledged_at = NOW() WHERE id = ?");
        $stmt->execute([$message_id]);
        
        echo json_encode(['status' => true, 'message' => 'Message acknowledged.']);
    }
    
    // Create manual case (HR on behalf of employee)
    elseif ($action === 'create_manual_case') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $case_type = $_POST['case_type'] ?? 'complaint';
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        
        if (!$employee_id || empty($subject) || empty($description)) {
            throw new Exception("Employee, subject and description are required.");
        }
        
        // Verify employee exists in this company
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Employee not found.");
        }
        
        // Generate case number with MANUAL prefix
        $case_number = 'MANUAL-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Handle file attachment
        $attachment_path = null;
        $attachment_name = null;
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_type = $finfo->file($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only PDF, JPG, PNG allowed.");
            }
            if ($file['size'] > $max_size) {
                throw new Exception("File too large. Maximum 2MB allowed.");
            }
            
            $upload_dir = __DIR__ . '/../uploads/messages/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_name = 'manual_' . time() . '_' . uniqid() . '.' . $ext;
            $target_path = $upload_dir . $unique_name;
            
            if (in_array($file_type, ['image/jpeg', 'image/png'])) {
                if ($file_type === 'image/jpeg') {
                    $img = imagecreatefromjpeg($file['tmp_name']);
                    imagejpeg($img, $target_path, 50);
                    imagedestroy($img);
                } else {
                    $img = imagecreatefrompng($file['tmp_name']);
                    imagesavealpha($img, true);
                    imagepng($img, $target_path, 5);
                    imagedestroy($img);
                }
            } else {
                move_uploaded_file($file['tmp_name'], $target_path);
            }
            
            $attachment_path = 'uploads/messages/' . $unique_name;
            $attachment_name = $file['name'];
        }
        
        // Create case (marked as manual)
        $stmt = $pdo->prepare("
            INSERT INTO employee_cases (company_id, employee_id, case_number, case_type, subject, description, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'in_review')
        ");
        $stmt->execute([$company_id, $employee_id, $case_number, $case_type, $subject, $description, $priority]);
        $case_id = $pdo->lastInsertId();
        
        // Add initial message from HR with attachment
        $stmt = $pdo->prepare("
            INSERT INTO employee_case_messages (case_id, message_title, sender_id, sender_role, message, attachment_path, attachment_name, is_internal)
            VALUES (?, 'Manual Entry', ?, 'hr', ?, ?, ?, 0)
        ");
        $stmt->execute([$case_id, $_SESSION['user_id'] ?? 0, $description, $attachment_path, $attachment_name]);
        
        echo json_encode(['status' => true, 'message' => "Manual case created successfully. Case #$case_number"]);
    }
    
    // ========================================
    // HR ACTIONS - Manage Cases
    // ========================================
    
    // Get all cases (HR)
    elseif ($action === 'get_cases') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $filter_status = $_POST['status'] ?? '';
        $filter_type = $_POST['type'] ?? '';
        
        $sql = "SELECT c.*, e.first_name, e.last_name, e.payroll_id as emp_code,
                       (SELECT COUNT(*) FROM employee_case_messages WHERE case_id = c.id) as message_count
                FROM employee_cases c
                JOIN employees e ON c.employee_id = e.id
                WHERE c.company_id = ?";
        $params = [$company_id];
        
        // Exclude closed/resolved from inbox (they go to Archive) unless specifically filtered
        if ($filter_status && in_array($filter_status, ['closed', 'resolved'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filter_status;
        } elseif ($filter_status) {
            $sql .= " AND c.status = ?";
            $params[] = $filter_status;
        } else {
            // By default, exclude closed/resolved - they appear in Archive
            $sql .= " AND c.status NOT IN ('closed', 'resolved')";
        }
        
        if ($filter_type) {
            $sql .= " AND c.case_type = ?";
            $params[] = $filter_type;
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'cases' => $cases]);
    }
    
    // Update case status (HR)
    elseif ($action === 'update_case_status') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $case_id = intval($_POST['case_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        
        if (!$case_id || !$new_status) throw new Exception("Case ID and status required.");
        
        $stmt = $pdo->prepare("UPDATE employee_cases SET status = ?, resolved_at = ? WHERE id = ? AND company_id = ?");
        $resolved_at = in_array($new_status, ['resolved', 'closed']) ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$new_status, $resolved_at, $case_id, $company_id]);
        
        echo json_encode(['status' => true, 'message' => 'Case status updated.']);
    }
    
    // Get archived cases (closed/resolved) - HR only
    elseif ($action === 'get_archived_cases') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $sql = "SELECT c.*, e.first_name, e.last_name, e.payroll_id as emp_code,
                       (SELECT COUNT(*) FROM employee_case_messages WHERE case_id = c.id) as message_count
                FROM employee_cases c
                LEFT JOIN employees e ON c.employee_id = e.id
                WHERE c.company_id = ? AND c.status IN ('closed', 'resolved')
                ORDER BY c.updated_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$company_id]);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'cases' => $cases]);
    }
    
    // Delete case permanently - Super Admin only
    elseif ($action === 'delete_case') {
        if ($role !== 'super_admin') {
            throw new Exception("Only Super Admin can delete cases.");
        }
        
        $case_id = intval($_POST['case_id'] ?? 0);
        if (!$case_id) throw new Exception("Case ID required.");
        
        // Verify case belongs to this company and is closed/resolved
        $stmt = $pdo->prepare("SELECT * FROM employee_cases WHERE id = ? AND company_id = ?");
        $stmt->execute([$case_id, $company_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) throw new Exception("Case not found.");
        if (!in_array($case['status'], ['closed', 'resolved'])) {
            throw new Exception("Only closed or resolved cases can be deleted.");
        }
        
        // Delete messages first (foreign key constraint)
        $pdo->prepare("DELETE FROM employee_case_messages WHERE case_id = ?")->execute([$case_id]);
        
        // Delete the case
        $pdo->prepare("DELETE FROM employee_cases WHERE id = ?")->execute([$case_id]);
        
        echo json_encode(['status' => true, 'message' => 'Case deleted permanently.']);
    }
    
    // Delete multiple cases - Super Admin only
    elseif ($action === 'delete_multiple_cases') {
        if ($role !== 'super_admin') {
            throw new Exception("Only Super Admin can delete cases.");
        }
        
        $case_ids = json_decode($_POST['case_ids'] ?? '[]', true);
        if (empty($case_ids)) throw new Exception("No cases selected.");
        
        $deleted = 0;
        foreach ($case_ids as $case_id) {
            $case_id = intval($case_id);
            
            // Verify case belongs to this company and is closed/resolved
            $stmt = $pdo->prepare("SELECT * FROM employee_cases WHERE id = ? AND company_id = ? AND status IN ('closed', 'resolved')");
            $stmt->execute([$case_id, $company_id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($case) {
                $pdo->prepare("DELETE FROM employee_case_messages WHERE case_id = ?")->execute([$case_id]);
                $pdo->prepare("DELETE FROM employee_cases WHERE id = ?")->execute([$case_id]);
                $deleted++;
            }
        }
        
        echo json_encode(['status' => true, 'message' => "$deleted case(s) deleted permanently."]);
    }
    
    // Delete single case with reason - Super Admin only
    elseif ($action === 'delete_case_with_reason') {
        if ($role !== 'super_admin') {
            throw new Exception("Only Super Admin can delete cases.");
        }
        
        $case_id = intval($_POST['case_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if (!$case_id) throw new Exception("Case ID required.");
        if (empty($reason)) throw new Exception("Deletion reason required.");
        
        // Verify case belongs to this company
        $stmt = $pdo->prepare("SELECT * FROM employee_cases WHERE id = ? AND company_id = ?");
        $stmt->execute([$case_id, $company_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) throw new Exception("Case not found.");
        
        // Log deletion reason (store in a simple way - could add audit table later)
        error_log("Case {$case['case_number']} deleted by user {$_SESSION['user_id']}. Reason: $reason");
        
        // Delete messages first
        $pdo->prepare("DELETE FROM employee_case_messages WHERE case_id = ?")->execute([$case_id]);
        // Delete the case
        $pdo->prepare("DELETE FROM employee_cases WHERE id = ?")->execute([$case_id]);
        
        echo json_encode(['status' => true, 'message' => 'Case deleted. Reason logged.']);
    }
    
    // Delete multiple cases with reason - Super Admin only
    elseif ($action === 'delete_cases_with_reason') {
        if ($role !== 'super_admin') {
            throw new Exception("Only Super Admin can delete cases.");
        }
        
        $case_ids = json_decode($_POST['case_ids'] ?? '[]', true);
        $reason = trim($_POST['reason'] ?? '');
        
        if (empty($case_ids)) throw new Exception("No cases selected.");
        if (empty($reason)) throw new Exception("Deletion reason required.");
        
        $deleted = 0;
        foreach ($case_ids as $case_id) {
            $case_id = intval($case_id);
            
            // Verify case belongs to this company
            $stmt = $pdo->prepare("SELECT * FROM employee_cases WHERE id = ? AND company_id = ?");
            $stmt->execute([$case_id, $company_id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($case) {
                // Log deletion
                error_log("Case {$case['case_number']} deleted by user {$_SESSION['user_id']}. Reason: $reason");
                
                $pdo->prepare("DELETE FROM employee_case_messages WHERE case_id = ?")->execute([$case_id]);
                $pdo->prepare("DELETE FROM employee_cases WHERE id = ?")->execute([$case_id]);
                $deleted++;
            }
        }
        
        echo json_encode(['status' => true, 'message' => "$deleted case(s) deleted. Reason logged."]);
    }
    
    // Get HR notifications
    elseif ($action === 'get_notifications') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM hr_notifications 
            WHERE company_id = ? AND (user_id IS NULL OR user_id = ?)
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$company_id, $_SESSION['user_id'] ?? 0]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count unread
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM hr_notifications 
            WHERE company_id = ? AND is_read = 0 AND (user_id IS NULL OR user_id = ?)
        ");
        $stmt->execute([$company_id, $_SESSION['user_id'] ?? 0]);
        $unread_count = $stmt->fetchColumn();
        
        echo json_encode(['status' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
    }
    
    // Mark notification as read
    elseif ($action === 'mark_notification_read') {
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id) {
            $pdo->prepare("UPDATE hr_notifications SET is_read = 1 WHERE id = ? AND company_id = ?")->execute([$notification_id, $company_id]);
        }
        echo json_encode(['status' => true]);
    }
    
    // Get dashboard stats (HR)
    elseif ($action === 'get_stats') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $stats = [];
        
        // Open cases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_cases WHERE company_id = ? AND status IN ('open', 'in_review', 'awaiting_response')");
        $stmt->execute([$company_id]);
        $stats['open_cases'] = $stmt->fetchColumn();
        
        // Resolved cases
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_cases WHERE company_id = ? AND status IN ('resolved', 'closed')");
        $stmt->execute([$company_id]);
        $stats['resolved_cases'] = $stmt->fetchColumn();
        
        // Pending (awaiting response)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_cases WHERE company_id = ? AND status = 'awaiting_response'");
        $stmt->execute([$company_id]);
        $stats['pending_cases'] = $stmt->fetchColumn();
        
        echo json_encode(['status' => true, 'stats' => $stats]);
    }
    
    // Send message to employee(s) (HR)
    elseif ($action === 'send_message') {
        if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
            throw new Exception("Unauthorized.");
        }
        
        $target_type = $_POST['target_type'] ?? 'individual'; // individual, department, category, all
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $department_id = intval($_POST['department_id'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $message_title = trim($_POST['message_title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (empty($subject) || empty($message)) {
            throw new Exception("Subject and message are required.");
        }
        
        // Handle file attachment (only for first recipient, shared path)
        $attachment_path = null;
        $attachment_name = null;
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024;
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $file_type = $finfo->file($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only PDF, JPG, PNG allowed.");
            }
            if ($file['size'] > $max_size) {
                throw new Exception("File too large. Maximum 2MB allowed.");
            }
            
            $upload_dir = __DIR__ . '/../uploads/messages/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_name = 'msg_' . time() . '_' . uniqid() . '.' . $ext;
            $target_path = $upload_dir . $unique_name;
            
            if (in_array($file_type, ['image/jpeg', 'image/png'])) {
                if ($file_type === 'image/jpeg') {
                    $img = imagecreatefromjpeg($file['tmp_name']);
                    imagejpeg($img, $target_path, 50);
                    imagedestroy($img);
                } else {
                    $img = imagecreatefrompng($file['tmp_name']);
                    imagesavealpha($img, true);
                    imagepng($img, $target_path, 5);
                    imagedestroy($img);
                }
            } else {
                move_uploaded_file($file['tmp_name'], $target_path);
            }
            
            $attachment_path = 'uploads/messages/' . $unique_name;
            $attachment_name = $file['name'];
        }
        
        // Get target employees
        $employees = [];
        if ($target_type === 'individual' && $employee_id) {
            $employees = [$employee_id];
        } elseif ($target_type === 'department' && $department_id) {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE company_id = ? AND department_id = ?");
            $stmt->execute([$company_id, $department_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($target_type === 'category' && $category_id) {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE company_id = ? AND salary_category_id = ?");
            $stmt->execute([$company_id, $category_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($target_type === 'all') {
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        if (empty($employees)) {
            throw new Exception("No recipients found.");
        }
        
        // Create a case for each employee (as HR-initiated inquiry)
        $count = 0;
        foreach ($employees as $emp_id) {
            $case_number = 'MSG-' . strtoupper(substr(md5(uniqid()), 0, 8));
            
            $stmt = $pdo->prepare("
                INSERT INTO employee_cases (company_id, employee_id, case_number, case_type, subject, description, status)
                VALUES (?, ?, ?, 'inquiry', ?, ?, 'awaiting_response')
            ");
            $stmt->execute([$company_id, $emp_id, $case_number, $subject, $message]);
            $case_id = $pdo->lastInsertId();
            
            // Add the message as first reply from HR with title and attachment
            $stmt = $pdo->prepare("
                INSERT INTO employee_case_messages (case_id, message_title, sender_id, sender_role, message, attachment_path, attachment_name)
                VALUES (?, ?, ?, 'hr', ?, ?, ?)
            ");
            $stmt->execute([$case_id, $message_title ?: null, $_SESSION['user_id'] ?? 0, $message, $attachment_path, $attachment_name]);
            $count++;
        }
        
        echo json_encode(['status' => true, 'message' => "Message sent to $count employee(s)."]);
    }
    
    else {
        throw new Exception("Invalid action: $action");
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
