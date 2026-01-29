<?php
/**
 * Enhanced ID Card AJAX Handler v2.0
 * Handles settings, preview, download, and template operations
 */

require_once '../includes/functions.php';
require_once '../includes/id_card_generator.php';

// Check for public actions first (no login needed)
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Download action can work without session for public embedding
if ($action === 'download' && isset($_GET['employee_id'])) {
    $employee_id = (int)$_GET['employee_id'];
    
    // Get employee's company
    $stmt = $pdo->prepare("SELECT company_id FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch();
    
    if ($emp) {
        header('Content-Type: text/html; charset=UTF-8');
        echo generate_id_card_preview_html($employee_id, $emp['company_id'], true);
        exit;
    }
}

// All other actions require login
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'] ?? 0;

try {
    switch ($action) {
        case 'get_settings':
            $settings = get_id_card_settings($company_id);
            $templates = get_template_definitions();
            
            echo json_encode([
                'success' => true,
                'settings' => $settings,
                'templates' => $templates
            ]);
            break;
            
        case 'update_settings':
            $data = [
                'validity_years' => $_POST['validity_years'] ?? 1,
                'code_type' => $_POST['code_type'] ?? 'qr',
                'card_shape' => $_POST['card_shape'] ?? 'horizontal',
                'template_id' => $_POST['template_id'] ?? 1,
                'logo_position' => $_POST['logo_position'] ?? 'left',
                'primary_color' => $_POST['primary_color'] ?? '#1e40af',
                'secondary_color' => $_POST['secondary_color'] ?? '#3b82f6',
                'accent_color' => $_POST['accent_color'] ?? '#f59e0b',
                'text_color' => $_POST['text_color'] ?? '#1f2937',
                'show_department' => isset($_POST['show_department']) ? 1 : 0,
                'show_designation' => isset($_POST['show_designation']) ? 1 : 0,
                'show_employee_id' => isset($_POST['show_employee_id']) ? 1 : 0,
                'custom_back_text' => $_POST['custom_back_text'] ?? '',
                'emergency_contact' => $_POST['emergency_contact'] ?? ''
            ];
            
            $result = update_id_card_settings($company_id, $data);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Settings saved successfully' : 'Failed to save settings'
            ]);
            break;
            
        case 'preview':
            $employee_id = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
            
            if (!$employee_id) {
                throw new Exception('Employee ID required');
            }
            
            // Verify employee belongs to company
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND company_id = ?");
            $stmt->execute([$employee_id, $company_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Employee not found');
            }
            
            $settings = get_id_card_settings($company_id);
            
            echo json_encode([
                'success' => true,
                'front' => generate_id_card_front_html($employee_id, $company_id),
                'back' => generate_id_card_back_html($employee_id, $company_id),
                'css' => get_id_card_css($settings)
            ]);
            break;
            
        case 'preview_sample':
            // Get any employee for sample preview
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE company_id = ? LIMIT 1");
            $stmt->execute([$company_id]);
            $emp = $stmt->fetch();
            
            $settings = get_id_card_settings($company_id);
            
            if ($emp) {
                echo json_encode([
                    'success' => true,
                    'front' => generate_id_card_front_html($emp['id'], $company_id),
                    'back' => generate_id_card_back_html($emp['id'], $company_id),
                    'css' => get_id_card_css($settings)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No employees found'
                ]);
            }
            break;
            
        case 'get_templates':
            $templates = get_template_definitions();
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            break;
            
        case 'get_verification_url':
            $employee_id = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
            
            if (!$employee_id) {
                throw new Exception('Employee ID required');
            }
            
            $url = get_verification_url($employee_id);
            
            echo json_encode([
                'success' => true,
                'url' => $url
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
