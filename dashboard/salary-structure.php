<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$success_msg = '';
$error_msg = '';

// Handle Add/Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM salary_categories WHERE id=? AND company_id=?");
        $stmt->execute([$id, $company_id]);
        $success_msg = "Category deleted.";
    } else {
        $name = clean_input($_POST['name']);
        $gross = floatval($_POST['base_gross_amount']);
        $basic_perc = floatval($_POST['basic_perc']);
        $housing_perc = floatval($_POST['housing_perc']);
        $transport_perc = floatval($_POST['transport_perc']);
        $other_perc = floatval($_POST['other_perc']);
        
        // Validate total 100%
        $total_perc = $basic_perc + $housing_perc + $transport_perc + $other_perc;
        if (abs($total_perc - 100) > 0.01) {
            $error_msg = "Total percentage must equal 100%. Current total: $total_perc%";
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update
                $stmt = $pdo->prepare("UPDATE salary_categories SET name=?, base_gross_amount=?, basic_perc=?, housing_perc=?, transport_perc=?, other_perc=? WHERE id=? AND company_id=?");
                $stmt->execute([$name, $gross, $basic_perc, $housing_perc, $transport_perc, $other_perc, $_POST['id'], $company_id]);
                $success_msg = "Category updated successfully.";
            } else {
                // Create
                $stmt = $pdo->prepare("INSERT INTO salary_categories (company_id, name, base_gross_amount, basic_perc, housing_perc, transport_perc, other_perc) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_id, $name, $gross, $basic_perc, $housing_perc, $transport_perc, $other_perc]);
                $success_msg = "Category created successfully.";
            }
        }
    }
}

// Fetch Categories
$stmt = $pdo->prepare("SELECT * FROM salary_categories WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll();

$current_page = 'salary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Structure - MiPayMaster</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .salary-breakdown-preview {
            background: #f1f5f9;
            padding: 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
        .breakdown-row {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            border-bottom: 1px dashed #cbd5e1;
        }
        .breakdown-row:last-child { border-bottom: none; font-weight: 600; margin-top: 0.5rem; border-top: 2px solid #cbd5e1; padding-top: 0.5rem;}
    </style>
</head>
<body>

<div class="dashboard-layout">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <?php 
        $page_title = "Salary Structure";
        include '../includes/dashboard_header.php'; 
        ?>

        <div class="container" style="padding-top: 2rem;">
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="flex gap-4" style="align-items: flex-start;">
                <!-- List Categories -->
                <div class="card" style="flex: 2;">
                    <h3>Salary Categories</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 0.5rem;">Name</th>
                                <th style="padding: 0.5rem;">Gross Pay</th>
                                <th style="padding: 0.5rem;">Breakdown (%)</th>
                                <th style="padding: 0.5rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 0.5rem;"><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td style="padding: 0.75rem 0.5rem;"><?php echo number_format($cat['base_gross_amount'], 2); ?></td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    B:<?php echo $cat['basic_perc']; ?>% | 
                                    H:<?php echo $cat['housing_perc']; ?>% | 
                                    T:<?php echo $cat['transport_perc']; ?>%
                                </td>
                                <td style="padding: 0.75rem 0.5rem;">
                                    <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;" onclick='editCategory(<?php echo json_encode($cat); ?>)'>Edit</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add/Edit Form -->
                <div class="card" style="flex: 1.5; position: sticky; top: 100px;">
                    <h3 id="formTitle">Add New Category</h3>
                    <form method="POST" id="salaryForm">
                        <input type="hidden" name="id" id="catId">
                        
                        <div class="form-group">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="name" id="catName" class="form-input" required placeholder="e.g. Intern">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Gross Salary Amount</label>
                            <input type="number" name="base_gross_amount" id="catGross" class="form-input" step="0.01" required oninput="calculateBreakdown()">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Distribution Rules (%)</label>
                            <div class="flex gap-2 mb-2">
                                <div><small>Basic</small><input type="number" name="basic_perc" id="percBasic" class="form-input" value="40" step="0.01" oninput="calculateBreakdown()"></div>
                                <div><small>Housing</small><input type="number" name="housing_perc" id="percHousing" class="form-input" value="30" step="0.01" oninput="calculateBreakdown()"></div>
                            </div>
                            <div class="flex gap-2">
                                <div><small>Transport</small><input type="number" name="transport_perc" id="percTransport" class="form-input" value="20" step="0.01" oninput="calculateBreakdown()"></div>
                                <div><small>Other</small><input type="number" name="other_perc" id="percOther" class="form-input" value="10" step="0.01" oninput="calculateBreakdown()"></div>
                            </div>
                        </div>

                        <div id="breakdownPreview" class="salary-breakdown-preview">
                            <div class="breakdown-row"><span>Basic Salary</span><span id="valBasic">0.00</span></div>
                            <div class="breakdown-row"><span>Housing Allow.</span><span id="valHousing">0.00</span></div>
                            <div class="breakdown-row"><span>Transport Allow.</span><span id="valTransport">0.00</span></div>
                            <div class="breakdown-row"><span>Other Allow.</span><span id="valOther">0.00</span></div>
                            <div class="breakdown-row"><span>Total Gross</span><span id="valTotal">0.00</span></div>
                            <div style="text-align: right; font-size: 0.8rem; margin-top: 5px; color: var(--text-muted);">Total Perc: <span id="valTotalPerc">100</span>%</div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="btn btn-primary" style="flex:1;">Save Category</button>
                            <button type="button" onclick="resetForm()" class="btn btn-outline">Clear</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function calculateBreakdown() {
    const gross = parseFloat(document.getElementById('catGross').value) || 0;
    const basic = parseFloat(document.getElementById('percBasic').value) || 0;
    const housing = parseFloat(document.getElementById('percHousing').value) || 0;
    const transport = parseFloat(document.getElementById('percTransport').value) || 0;
    const other = parseFloat(document.getElementById('percOther').value) || 0;

    document.getElementById('valBasic').innerText = (gross * (basic / 100)).toFixed(2);
    document.getElementById('valHousing').innerText = (gross * (housing / 100)).toFixed(2);
    document.getElementById('valTransport').innerText = (gross * (transport / 100)).toFixed(2);
    document.getElementById('valOther').innerText = (gross * (other / 100)).toFixed(2);
    document.getElementById('valTotal').innerText = gross.toFixed(2);

    const totalPerc = basic + housing + transport + other;
    const percEl = document.getElementById('valTotalPerc');
    percEl.innerText = totalPerc.toFixed(1);
    percEl.style.color = Math.abs(totalPerc - 100) < 0.1 ? 'var(--success-color)' : 'var(--danger-color)';
}

function editCategory(cat) {
    document.getElementById('formTitle').innerText = 'Edit Category: ' + cat.name;
    document.getElementById('catId').value = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catGross').value = cat.base_gross_amount;
    document.getElementById('percBasic').value = cat.basic_perc;
    document.getElementById('percHousing').value = cat.housing_perc;
    document.getElementById('percTransport').value = cat.transport_perc;
    document.getElementById('percOther').value = cat.other_perc;
    calculateBreakdown();
}

function resetForm() {
    document.getElementById('formTitle').innerText = 'Add New Category';
    document.getElementById('salaryForm').reset();
    document.getElementById('catId').value = '';
    calculateBreakdown();
}
</script>

</body>
</html>
