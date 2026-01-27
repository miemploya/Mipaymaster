<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$success_msg = '';
$error_msg = '';

// --- HELPER: Ensure Default Components Exist for Company ---
// This ensures that even if the migration didn't run perfect, the UI will force them into existence so they can be used.
$defaults = [
    ['name' => 'Basic Salary', 'type' => 'basic', 'default' => 40],
    ['name' => 'Housing Allowance', 'type' => 'allowance', 'default' => 30],
    ['name' => 'Transport Allowance', 'type' => 'allowance', 'default' => 20]
];

foreach ($defaults as $def) {
    try {
        $chk = $pdo->prepare("SELECT id FROM salary_components WHERE company_id=? AND name=?");
        $chk->execute([$company_id, $def['name']]);
        if ($chk->rowCount() == 0) {
            $ins = $pdo->prepare("INSERT INTO salary_components (company_id, name, type, default_percentage, is_active, is_custom) VALUES (?, ?, ?, ?, 1, 0)");
            $ins->execute([$company_id, $def['name'], $def['type'], $def['default']]);
        }
    } catch (Exception $e) {}
}


// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM salary_categories WHERE id=? AND company_id=?");
        $stmt->execute([$id, $company_id]);
        $success_msg = "Category deleted.";
    } 
    else {
        // SAVE (Create/Update)
        try {
            $pdo->beginTransaction();

            $name = clean_input($_POST['name']);
            $gross = floatval($_POST['base_gross_amount']);
            $percentages = $_POST['percentages'] ?? []; // Array [component_id => val]

            // 1. Validate Total 100%
            $total_perc = array_sum($percentages);
            if (abs($total_perc - 100) > 0.01) {
                throw new Exception("Total percentage must equal 100%. Current total: " . $total_perc . "%");
            }

            // 2. Create/Update Category
            $category_id = $_POST['id'] ?? null;
            if ($category_id) {
                $stmt = $pdo->prepare("UPDATE salary_categories SET name=?, base_gross_amount=? WHERE id=? AND company_id=?");
                $stmt->execute([$name, $gross, $category_id, $company_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO salary_categories (company_id, name, base_gross_amount) VALUES (?, ?, ?)");
                $stmt->execute([$company_id, $name, $gross]);
                $category_id = $pdo->lastInsertId();
            }

            // 3. Save Breakdown (Delete old, Insert new)
            $del = $pdo->prepare("DELETE FROM salary_category_breakdown WHERE category_id=?");
            $del->execute([$category_id]);

            $ins = $pdo->prepare("INSERT INTO salary_category_breakdown (category_id, salary_component_id, component_name, percentage) VALUES (?, ?, ?, ?)");
            
            // Get Component Names map
            $comp_map = [];
            $all_comps = $pdo->prepare("SELECT id, name FROM salary_components WHERE company_id=?");
            $all_comps->execute([$company_id]);
            while($c = $all_comps->fetch()){ $comp_map[$c['id']] = $c['name']; }

            foreach ($percentages as $comp_id => $perc) {
                $val = floatval($perc);
                if ($val > 0) {
                     // Verify component belongs to company or is system? (Schema has company_id on components, so yes)
                     if (!isset($comp_map[$comp_id])) continue; 
                     $ins->execute([$category_id, $comp_id, $comp_map[$comp_id], $val]);
                }
            }

            $pdo->commit();
            $success_msg = "Category saved successfully.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = $e->getMessage();
        }
    }
}

// --- FETCH DATA ---

// 1. Get Components (Active)
$components = [];
$stmt = $pdo->prepare("SELECT * FROM salary_components WHERE company_id = ? AND is_active = 1 ORDER BY type ASC, id ASC"); // Basic first usually
$stmt->execute([$company_id]);
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sort components: Basic first, then allowances
usort($components, function($a, $b) {
    // Priority: Basic (0), Allowance (1), Others (2)
    $typeOrder = ['basic' => 0, 'allowance' => 1, 'system' => 2]; 
    $orderA = $typeOrder[$a['type']] ?? 3;
    $orderB = $typeOrder[$b['type']] ?? 3;
    
    if ($orderA !== $orderB) return $orderA - $orderB;
    // Secondary sort: Custom is active=1, is_custom=0 first? "System Locked" usually implies specific IDs or Names.
    // Let's just sort by ID after Type.
    return $a['id'] - $b['id'];
});


// 2. Get Categories & Breakdowns
$categories = [];
$stmt = $pdo->prepare("SELECT * FROM salary_categories WHERE company_id = ? ORDER BY name ASC");
$stmt->execute([$company_id]);
$raw_cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($raw_cats as $cat) {
    // Fetch breakdown
    $b_stmt = $pdo->prepare("SELECT * FROM salary_category_breakdown WHERE category_id = ?");
    $b_stmt->execute([$cat['id']]);
    $cat['breakdown'] = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
    $categories[] = $cat;
}


$current_page = 'salary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Structure - MiPayMaster</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
         @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
         body { font-family: 'Inter', sans-serif; }
         [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 font-sans antialiased" x-data="salaryApp()">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <!-- Main -->
    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <!-- Header -->
        <?php $page_title = 'Salary Structure'; include '../includes/dashboard_header.php'; ?>
        <!-- HR Sub-Header -->
        <?php include '../includes/hr_header.php'; ?>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            
            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-50 text-green-700 border border-green-200 flex items-center gap-2"><i data-lucide="check-circle" class="w-5 h-5"></i> <?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 text-red-700 border border-red-200 flex items-center gap-2"><i data-lucide="alert-circle" class="w-5 h-5"></i> <?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="flex flex-col lg:flex-row gap-8 items-start">
                
                <!-- LIST CARD -->
                <div class="flex-1 w-full bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-lg">Defined Categories</h3>
                        <span class="text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-slate-500"><?php echo count($categories); ?> Total</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="p-4 font-semibold">Category Name</th>
                                    <th class="p-4 font-semibold">Gross Pay</th>
                                    <th class="p-4 font-semibold">Breakdown</th>
                                    <th class="p-4 font-semibold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php foreach ($categories as $cat): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors group">
                                    <td class="p-4 font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td class="p-4 font-mono text-slate-600 dark:text-slate-400">₦<?php echo number_format($cat['base_gross_amount'], 2); ?></td>
                                    <td class="p-4">
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($cat['breakdown'] as $bd): ?>
                                                 <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                     <?php echo htmlspecialchars($bd['component_name']); ?>: <?php echo $bd['percentage']; ?>%
                                                 </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <button @click="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" class="text-brand-600 hover:text-brand-700 font-medium text-xs border border-brand-200 rounded px-3 py-1 bg-brand-50 hover:bg-brand-100 transition-colors">Edit</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                    <tr><td colspan="4" class="p-8 text-center text-slate-500">No salary categories defined yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FORM CARD -->
                <div class="w-full lg:w-96 shrink-0 sticky top-6">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden ring-1 ring-slate-900/5">
                        <div class="p-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white" x-text="isEdit ? 'Edit Category' : 'New Category'"></h3>
                            <p class="text-xs text-slate-500 mt-1">Define base salary and component splits.</p>
                        </div>
                        
                        <form method="POST" class="p-5 space-y-5">
                            <input type="hidden" name="id" x-model="form.id">

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Category Name</label>
                                <input type="text" name="name" x-model="form.name" required placeholder="e.g. Senior Manager" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-medium focus:ring-brand-500 focus:border-brand-500 transition-shadow">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Gross Annual Salary (₦)</label>
                                <input type="number" step="0.01" name="base_gross_amount" x-model="form.gross" @input="calculateValues()" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-medium focus:ring-brand-500 focus:border-brand-500 transition-shadow">
                            </div>

                            <div class="pt-2">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-3 flex justify-between">
                                    <span>Component Breakdown</span>
                                    <span :class="{'text-green-600': totalPerc == 100, 'text-red-500': totalPerc != 100}">
                                        Total: <span x-text="totalPerc.toFixed(1)"></span>%
                                    </span>
                                </label>
                                
                                <div class="space-y-3 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-lg border border-slate-100 dark:border-slate-800 max-h-[300px] overflow-y-auto">
                                    <?php foreach ($components as $comp): ?>
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium truncate text-slate-700 dark:text-slate-200" title="<?php echo $comp['name']; ?>">
                                                <?php echo htmlspecialchars($comp['name']); ?>
                                                <?php if($comp['is_custom'] == 0): ?><i data-lucide="lock" class="w-3 h-3 inline text-slate-400 ml-1"></i><?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="relative w-24">
                                            <input type="number" step="0.01" min="0" max="100" 
                                                name="percentages[<?php echo $comp['id']; ?>]" 
                                                x-model.number="form.percentages[<?php echo $comp['id']; ?>]" 
                                                @input="calculateValues()"
                                                class="w-full rounded border-slate-200 dark:border-slate-700 text-right pr-6 py-1 text-sm bg-white dark:bg-slate-800 focus:ring-1 focus:ring-brand-500">
                                            <span class="absolute right-2 top-1.5 text-xs text-slate-400">%</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Live Preview -->
                            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 text-sm space-y-1">
                                <div class="flex justify-between text-xs text-indigo-900 dark:text-indigo-200 font-semibold mb-2 uppercase">
                                    <span>Values Preview</span>
                                </div>
                                <template x-for="(val, id) in previewValues" :key="id">
                                    <div class="flex justify-between text-indigo-800 dark:text-indigo-300" x-show="val > 0">
                                        <span x-text="compNames[id]"></span>
                                        <span x-text="'₦' + val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </div>
                                </template>
                            </div>

                            <button type="submit" :disabled="Math.abs(totalPerc - 100) > 0.1" 
                                :class="{'opacity-50 cursor-not-allowed': Math.abs(totalPerc - 100) > 0.1}"
                                class="w-full py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 shadow-md transition-all active:scale-[0.98]">
                                <span x-text="isEdit ? 'Update Structure' : 'Create Structure'"></span>
                            </button>
                            
                            <button type="button" x-show="isEdit" @click="resetForm()" class="w-full py-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel Edit</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

    <?php include '../includes/dashboard_scripts.php'; ?>

    function salaryApp() {
        return {
            isEdit: false,
            // Map Component IDs to Names for Preview
            compNames: <?php 
                $map = []; 
                foreach($components as $c) $map[$c['id']] = $c['name']; 
                echo json_encode($map); 
            ?>,
            
            form: {
                id: '',
                name: '',
                gross: 0,
                percentages: <?php 
                    // Initial Map for all components = 0 or default
                    $initPerc = [];
                    foreach($components as $c) $initPerc[$c['id']] = floatval($c['default_percentage']);
                    echo json_encode($initPerc);
                ?>
            },
            
            totalPerc: 0,
            previewValues: {},

            init() {
                this.calculateValues();
            },

            calculateValues() {
                let total = 0;
                let gross = parseFloat(this.form.gross) || 0;
                
                for (const [id, perc] of Object.entries(this.form.percentages)) {
                    let p = parseFloat(perc) || 0;
                    total += p;
                    this.previewValues[id] = gross * (p / 100);
                }
                this.totalPerc = total;
            },

            editCategory(cat) {
                this.isEdit = true;
                this.form.id = cat.id;
                this.form.name = cat.name;
                this.form.gross = cat.base_gross_amount;
                
                // Reset percentages to 0 first
                for (let id in this.form.percentages) { this.form.percentages[id] = 0; }

                // Fill from breakdown
                if (cat.breakdown) {
                    cat.breakdown.forEach(bd => {
                        if (this.form.percentages.hasOwnProperty(bd.salary_component_id)) {
                            this.form.percentages[bd.salary_component_id] = parseFloat(bd.percentage);
                        }
                    });
                }
                
                this.calculateValues();
            },

            resetForm() {
                this.isEdit = false;
                this.form.id = '';
                this.form.name = '';
                this.form.gross = 0;
                // Reset to defaults
                this.form.percentages = <?php echo json_encode($initPerc); ?>;
                this.calculateValues();
            }
        }
    }
</script>
</body>
</html>
