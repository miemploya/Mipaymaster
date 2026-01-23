<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$success_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enable_paye = isset($_POST['enable_paye']) ? 1 : 0;
    $enable_pension = isset($_POST['enable_pension']) ? 1 : 0;
    $enable_nhis = isset($_POST['enable_nhis']) ? 1 : 0;
    $enable_nhf = isset($_POST['enable_nhf']) ? 1 : 0;
    
    $pension_employer = floatval($_POST['pension_employer_perc']);
    $pension_employee = floatval($_POST['pension_employee_perc']);

    try {
        $stmt = $pdo->prepare("UPDATE statutory_settings SET enable_paye=?, enable_pension=?, enable_nhis=?, enable_nhf=?, pension_employer_percentage=?, pension_employee_percentage=? WHERE company_id=?");
        $stmt->execute([$enable_paye, $enable_pension, $enable_nhis, $enable_nhf, $pension_employer, $pension_employee, $company_id]);
        $success_msg = "Statutory settings updated successfully.";
    } catch (PDOException $e) {
        set_flash_message('error', 'Error updating settings.');
    }
}

// Fetch Settings
$stmt = $pdo->prepare("SELECT * FROM statutory_settings WHERE company_id = ?");
$stmt->execute([$company_id]);
$settings = $stmt->fetch();

$current_page = 'statutory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statutory Settings - MiPayMaster</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="dashboard-layout">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <?php 
        $page_title = "Statutory & Compliance";
        include '../includes/dashboard_header.php'; 
        ?>

        <div class="container" style="padding-top: 2rem; max-width: 800px; margin-left: 0;">
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST">
                    
                    <div class="mb-4">
                        <h3>Tax Configuration</h3>
                        <p class="text-muted text-sm mb-4">Enable or disable automatic PAYE tax calculations.</p>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="enable_paye" id="paye" <?php echo ($settings['enable_paye']) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <label for="paye" class="form-label mb-0">Enable PAYE (Pay As You Earn)</label>
                        </div>
                    </div>
                    
                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

                    <div class="mb-4">
                        <h3>Pension Scheme</h3>
                        <p class="text-muted text-sm mb-4">Configure pension contribution percentages.</p>
                        
                        <div class="flex items-center gap-2 mb-4">
                            <input type="checkbox" name="enable_pension" id="pension" <?php echo ($settings['enable_pension']) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <label for="pension" class="form-label mb-0">Enable Pension Deductions</label>
                        </div>

                        <div class="flex gap-4">
                                <label class="form-label">Employer Contribution (%)</label>
                                <input type="number" name="pension_employer_perc" class="form-input" value="<?php echo $settings['pension_employer_percentage']; ?>" step="0.01">
                            </div>
                                <label class="form-label">Employee Contribution (%)</label>
                                <input type="number" name="pension_employee_perc" class="form-input" value="<?php echo $settings['pension_employee_percentage']; ?>" step="0.01">
                            </div>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

                    <div class="mb-4">
                        <h3>Other Statutory Deductions</h3>
                        <div class="flex items-center gap-2 mb-2">
                            <input type="checkbox" name="enable_nhis" id="nhis" <?php echo ($settings['enable_nhis']) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <label for="nhis" class="form-label mb-0">National Health Insurance Scheme (NHIS)</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="enable_nhf" id="nhf" <?php echo ($settings['enable_nhf']) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <label for="nhf" class="form-label mb-0">National Housing Fund (NHF)</label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

</body>
</html>
