<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'] ?? 0;
$company_name = $_SESSION['company_name'] ?? 'Company';
$current_page = 'supplementary';

// Fetch Departments
$stmt = $pdo->prepare("SELECT name FROM departments WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Salary Categories
$stmt = $pdo->prepare("SELECT id, name FROM salary_categories WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Comprehensive bonus types list
$bonus_types = [
    "Sales Commission", "Productivity Bonus", "Attendance Bonus", "Service Charge Bonus", "Tips",
    "Unpaid Backlog Tip", "Unpaid Backlog Bonus", "Unpaid Backlog Commission", "Appraisal Bonus",
    "Overtime Pay", "Night Shift Allowance", "Weekend or Holiday Bonus", "Incentive Bonus",
    "Recognition Bonus", "Professional Certification Bonus", "Call-Out Bonus", "Supervisory Bonus",
    "Relief Bonus", "Employee of the Month", "Employee of the Year", "Holiday Bonus", "13th Month Bonus",
    "Quarterly Bonus", "Retention Bonus", "Joining Bonus", "Referral Commission", "Referral Bonus",
    "Project Completion Bonus", "Sign-On Bonus", "Loyalty/Anniversary Bonus",
    "Reallocation/Resettlement Allowance", "Expat Allowance", "Death Benefit Pay",
    "Maternity Gift or Bonus", "Marriage Bonus", "Birthday Bonus", "Special Recognition Award",
    "Training Reimbursement", "Promotion Adjustment", "Performance Grant", "Hardship Bonus",
    "Flight Ticket Bonus", "Tool/Equipment Allowance", "Shipboard Allowance",
    "Hostile Environment Premium", "Family Relocation Bonus", "Baby Delivery Support Bonus",
    "Back-to-School Bonus", "Mobilization Fee", "Demobilization Fee", "On-Call Bonus",
    "Daily Site Allowance", "Per Diem (Daily Living Expense)", "Milestone Bonus",
    "Fixed-Term Completion Bonus", "Hardware Stipend", "Tech Stack Allowance",
    "Coding/Production Bonus", "Bug Bounty Bonus", "Retention Tokens", "Court Appearance Bonus",
    "Client Billable Hour Bonus", "Professional Membership Fee Reimbursement", "Christmas Bonus",
    "Eid Bonus", "Black Friday Bonus", "Welcome Back Bonus", "Anniversary Celebration Bonus",
    "Campaign Success Bonus", "Festival Allowance", "Zero Disciplinary Case Bonus",
    "Punctuality Bonus", "Wellness Participation Bonus", "Internal Referral Bonus",
    "Idea Submission Bonus", "Culture Champion Bonus", "Signing Bonus (Executive)",
    "Board Attendance Fee", "Executive Car Grant", "Chairman's Gift Bonus",
    "Exit Appreciation Bonus", "Strategic Retention Allowance", "Other Bonus"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplementary Payroll - Mipaymaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1',
                            600: '#4f46e5', 700: '#4338ca', 900: '#312e81'
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100" x-data="suppPayroll()">

    <?php include '../includes/dashboard_sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        <?php $page_title = 'Supplementary Payroll'; include '../includes/dashboard_header.php'; ?>
        <?php include '../includes/payroll_header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900">
            
            <!-- Period Selection -->
            <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 mb-6">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                    <i data-lucide="gift" class="w-5 h-5 text-brand-600"></i>
                    Supplementary Bonus Payroll
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    Process bonus payments (13th month, performance bonus, arrears) separately from regular payroll.
                </p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Month</label>
                        <select x-model="period.month" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Year</label>
                        <input type="number" x-model="period.year" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                    </div>
                    <div class="flex items-end">
                        <button @click="loadRuns()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium transition-colors">
                            View Past Runs
                        </button>
                    </div>
                </div>
            </div>

            <!-- Entry Mode Tabs -->
            <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 mb-6">
                <div class="border-b border-slate-200 dark:border-slate-800 px-6 pt-4">
                    <div class="flex space-x-4">
                        <button @click="mode = 'bulk'" 
                            :class="mode === 'bulk' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                            class="pb-3 px-2 border-b-2 font-medium text-sm transition-colors">
                            <i data-lucide="users" class="w-4 h-4 inline mr-1"></i>
                            Bulk Entry
                        </button>
                        <button @click="mode = 'individual'" 
                            :class="mode === 'individual' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                            class="pb-3 px-2 border-b-2 font-medium text-sm transition-colors">
                            <i data-lucide="user" class="w-4 h-4 inline mr-1"></i>
                            Individual Entry
                        </button>
                    </div>
                </div>

                <!-- Bulk Entry Form -->
                <div x-show="mode === 'bulk'" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Department</label>
                            <select x-model="bulkForm.department" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Salary Category</label>
                            <select x-model="bulkForm.category" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="relative">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Bonus Type</label>
                            <input type="text" x-model="bulkBonusSearch" @input="filterBulkBonuses()" @focus="showBulkBonusList = true"
                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5"
                                placeholder="Start typing to search bonus types...">
                            
                            <!-- Bonus Autocomplete Dropdown -->
                            <div x-show="showBulkBonusList && filteredBulkBonuses.length > 0" x-cloak
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                <template x-for="bonus in filteredBulkBonuses" :key="bonus">
                                    <button @click="selectBulkBonus(bonus)" 
                                        class="w-full text-left px-4 py-2 hover:bg-brand-50 dark:hover:bg-slate-700 text-sm transition-colors"
                                        x-text="bonus"></button>
                                </template>
                            </div>
                            
                            <!-- Selected Bonus Badge -->
                            <div x-show="bulkForm.bonus_name" class="mt-2">
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                                    <span x-text="bulkForm.bonus_name"></span>
                                    <button @click="clearBulkBonus()" class="hover:text-green-900">
                                        <i data-lucide="x" class="w-3 h-3"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Amount (₦)</label>
                            <input type="number" x-model="bulkForm.amount" step="0.01" min="0" 
                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5"
                                placeholder="Enter amount">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Notes (Optional)</label>
                            <input type="text" x-model="bulkForm.notes" 
                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5"
                                placeholder="e.g., Q4 2025 Performance">
                        </div>
                    </div>
                    <button @click="addBulk()" :disabled="loading" 
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add to Staging
                    </button>
                </div>

                <!-- Individual Entry Form -->
                <div x-show="mode === 'individual'" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div class="lg:col-span-2 relative">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Employee</label>
                            <input type="text" x-model="searchQuery" @input.debounce.300ms="searchEmployees()" 
                                @focus="showSearch = true"
                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5"
                                placeholder="Search by name or ID...">
                            
                            <!-- Search Results Dropdown -->
                            <div x-show="showSearch && searchResults.length > 0" x-cloak
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                <template x-for="emp in searchResults" :key="emp.id">
                                    <button @click="selectEmployee(emp)" 
                                        class="w-full text-left px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm transition-colors">
                                        <span x-text="emp.first_name + ' ' + emp.last_name" class="font-medium"></span>
                                        <span x-text="'(' + (emp.payroll_id || 'N/A') + ')'" class="text-slate-500"></span>
                                        <span x-text="emp.department" class="text-xs text-slate-400 ml-2"></span>
                                    </button>
                                </template>
                            </div>
                            
                            <!-- Selected Employee Badge -->
                            <div x-show="individualForm.employee_id" class="mt-2">
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-brand-100 text-brand-700 rounded-full text-sm">
                                    <span x-text="individualForm.employee_name"></span>
                                    <button @click="clearEmployee()" class="hover:text-brand-900">
                                        <i data-lucide="x" class="w-3 h-3"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div class="relative">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Bonus Type</label>
                            <input type="text" x-model="indivBonusSearch" @input="filterIndivBonuses()" @focus="showIndivBonusList = true"
                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5"
                                placeholder="Start typing to search bonus types...">
                            
                            <!-- Bonus Autocomplete Dropdown -->
                            <div x-show="showIndivBonusList && filteredIndivBonuses.length > 0" x-cloak
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                <template x-for="bonus in filteredIndivBonuses" :key="bonus">
                                    <button @click="selectIndivBonus(bonus)" 
                                        class="w-full text-left px-4 py-2 hover:bg-brand-50 dark:hover:bg-slate-700 text-sm transition-colors"
                                        x-text="bonus"></button>
                                </template>
                            </div>
                            
                            <!-- Selected Bonus Badge -->
                            <div x-show="individualForm.bonus_name" class="mt-2">
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                                    <span x-text="individualForm.bonus_name"></span>
                                    <button @click="clearIndivBonus()" class="hover:text-green-900">
                                        <i data-lucide="x" class="w-3 h-3"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Amount (₦)</label>
                            <input type="number" x-model="individualForm.amount" step="0.01" min="0" 
                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5"
                                placeholder="Enter amount">
                        </div>
                    </div>
                    <button @click="addIndividual()" :disabled="loading || !individualForm.employee_id" 
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2 disabled:opacity-50">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add to Staging
                    </button>
                </div>
            </div>

            <!-- Staging Table -->
            <div class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 mb-6">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                    <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="list" class="w-4 h-4"></i>
                        Staging Entries
                        <span x-show="staging.length > 0" class="text-sm font-normal text-slate-500" x-text="'(' + staging.length + ' entries)'"></span>
                    </h3>
                    <div class="flex items-center gap-2">
                        <button @click="refreshStaging()" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </button>
                        <button x-show="staging.length > 0" @click="clearStaging()" class="text-red-500 hover:text-red-700 text-sm font-medium">
                            Clear All
                        </button>
                    </div>
                </div>
                
                <div x-show="staging.length === 0" class="p-8 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                    <p>No entries yet. Add employees using the forms above.</p>
                </div>
                
                <div x-show="staging.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-400">Employee</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-400">Department</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-400">Bonus Type</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-400">Amount</th>
                                <th class="px-4 py-3 text-center font-semibold text-slate-600 dark:text-slate-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="entry in staging" :key="entry.id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="px-4 py-3">
                                        <span x-text="entry.first_name + ' ' + entry.last_name" class="font-medium text-slate-900 dark:text-white"></span>
                                        <span x-text="'(' + (entry.payroll_id || 'N/A') + ')'" class="text-slate-400 text-xs ml-1"></span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400" x-text="entry.department || '-'"></td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400" x-text="entry.bonus_name"></td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-white" x-text="formatCurrency(entry.amount)"></td>
                                    <td class="px-4 py-3 text-center">
                                        <button @click="removeEntry(entry.id)" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-900 font-bold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-slate-700 dark:text-slate-300">Total</td>
                                <td class="px-4 py-3 text-right text-brand-600" x-text="formatCurrency(stagingTotal)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Execute Button -->
            <div x-show="staging.length > 0" class="space-y-4">
                <!-- Guidance Note -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <i data-lucide="info" class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <p class="font-medium mb-1">After generating supplementary payroll:</p>
                            <ul class="list-disc ml-4 space-y-1 text-blue-600 dark:text-blue-400">
                                <li>You can <strong>Print</strong> or <strong>Export</strong> the draft directly from this page</li>
                                <li>Alternatively, go to <strong>Run Payroll → Payroll Sheet</strong> to view the combined payroll with all entries</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button @click="executePayroll()" :disabled="loading" 
                        class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg transition-colors flex items-center gap-2 disabled:opacity-50">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        <span x-text="loading ? 'Processing...' : 'Generate Supplementary Payroll'"></span>
                    </button>
                </div>
            </div>

            <!-- Generated Payroll Sheet -->
            <div x-show="generatedSheet.length > 0" class="bg-white dark:bg-slate-950 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 mb-6" id="generated-sheet">
                <!-- Header with Collapse/Expand, Print -->
                <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <button @click="sheetExpanded = !sheetExpanded" 
                                class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                <i :data-lucide="sheetExpanded ? 'chevron-down' : 'chevron-right'" class="w-5 h-5 text-slate-600"></i>
                            </button>
                            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <i data-lucide="file-text" class="w-5 h-5 text-green-600"></i>
                                Generated Supplementary Payroll
                                <span class="text-sm font-normal text-slate-500" x-text="'(' + generatedSheet.length + ' employees)'"></span>
                            </h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Save Changes Button -->
                            <button x-show="hasSheetEdits" @click="saveSheetEdits()" :disabled="loading"
                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg flex items-center gap-1">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Save Changes
                            </button>
                            <!-- Print Button -->
                            <button @click="printSheet()" 
                                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg flex items-center gap-1">
                                <i data-lucide="printer" class="w-4 h-4"></i>
                                Print
                            </button>
                            <!-- Export Button -->
                            <button @click="exportSheet()" 
                                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg flex items-center gap-1">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Export
                            </button>
                        </div>
                    </div>
                    
                    <!-- Audit Trail Info -->
                    <div x-show="sheetAudit.last_edited" class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                            <i data-lucide="history" class="w-4 h-4"></i>
                            <span>Last edited by <strong x-text="sheetAudit.edited_by"></strong> on <span x-text="sheetAudit.last_edited"></span></span>
                        </div>
                    </div>
                </div>
                
                <!-- Collapsible Content -->
                <div x-show="sheetExpanded" x-collapse class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-400">Employee</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-400">Department</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-400">Gross Bonus</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-400">PAYE Tax</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-400">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="(emp, idx) in generatedSheet" :key="emp.id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white" x-text="emp.employee_name"></td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400" x-text="emp.department || '-'"></td>
                                    <td class="px-4 py-3 text-right">
                                        <input type="number" step="0.01" min="0" 
                                            x-model.number="generatedSheet[idx].gross_salary"
                                            @input="recalculateEntry(idx)"
                                            class="w-28 text-right rounded border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-1.5">
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-600" x-text="formatCurrency(emp.total_deductions)"></td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600" x-text="formatCurrency(emp.net_pay)"></td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-900 font-bold">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-slate-700 dark:text-slate-300">Total</td>
                                <td class="px-4 py-3 text-right" x-text="formatCurrency(sheetTotals.gross)"></td>
                                <td class="px-4 py-3 text-right text-red-600" x-text="formatCurrency(sheetTotals.deductions)"></td>
                                <td class="px-4 py-3 text-right text-green-600" x-text="formatCurrency(sheetTotals.net)"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Collapsed Summary -->
                <div x-show="!sheetExpanded" class="p-4 text-sm text-slate-600 dark:text-slate-400">
                    <div class="flex justify-between">
                        <span x-text="generatedSheet.length + ' employees'"></span>
                        <span class="font-bold text-green-600" x-text="'Net Total: ' + formatCurrency(sheetTotals.net)"></span>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            <div x-show="successMessage" x-cloak x-transition
                class="fixed bottom-6 right-6 bg-green-600 text-white px-6 py-4 rounded-xl shadow-xl flex items-center gap-3 z-50">
                <i data-lucide="check-circle" class="w-6 h-6"></i>
                <span x-text="successMessage"></span>
            </div>

        </main>
    </div>

    <script>
        function suppPayroll() {
            return {
                period: {
                    month: new Date().getMonth() + 1,
                    year: new Date().getFullYear()
                },
                sessionId: 'supp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                mode: 'bulk',
                loading: false,
                successMessage: '',
                
                bulkForm: { department: '', category: '', bonus_name: '', amount: 0, notes: '' },
                individualForm: { employee_id: null, employee_name: '', bonus_name: '', amount: 0 },
                
                // Bonus type autocomplete
                bonusTypes: <?= json_encode($bonus_types) ?>,
                bulkBonusSearch: '',
                showBulkBonusList: false,
                filteredBulkBonuses: [],
                indivBonusSearch: '',
                showIndivBonusList: false,
                filteredIndivBonuses: [],
                
                generatedSheet: [],
                sheetTotals: { gross: 0, deductions: 0, net: 0 },
                sheetExpanded: true,
                hasSheetEdits: false,
                currentRunId: null,
                sheetAudit: { last_edited: null, edited_by: null },
                originalSheet: [],
                
                searchQuery: '',
                searchResults: [],
                showSearch: false,
                
                staging: [],
                stagingTotal: 0,
                
                init() {
                    this.refreshStaging();
                    // Close dropdowns on outside click
                    document.addEventListener('click', (e) => {
                        if (!e.target.closest('.relative')) {
                            this.showSearch = false;
                            this.showBulkBonusList = false;
                            this.showIndivBonusList = false;
                        }
                    });
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                // Bonus autocomplete functions
                filterBulkBonuses() {
                    const search = this.bulkBonusSearch.toLowerCase();
                    if (search.length === 0) {
                        this.filteredBulkBonuses = this.bonusTypes.slice(0, 10);
                    } else {
                        this.filteredBulkBonuses = this.bonusTypes.filter(b => 
                            b.toLowerCase().includes(search)
                        ).slice(0, 15);
                    }
                    this.showBulkBonusList = true;
                },
                
                selectBulkBonus(bonus) {
                    this.bulkForm.bonus_name = bonus;
                    this.bulkBonusSearch = '';
                    this.showBulkBonusList = false;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                clearBulkBonus() {
                    this.bulkForm.bonus_name = '';
                    this.bulkBonusSearch = '';
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                filterIndivBonuses() {
                    const search = this.indivBonusSearch.toLowerCase();
                    if (search.length === 0) {
                        this.filteredIndivBonuses = this.bonusTypes.slice(0, 10);
                    } else {
                        this.filteredIndivBonuses = this.bonusTypes.filter(b => 
                            b.toLowerCase().includes(search)
                        ).slice(0, 15);
                    }
                    this.showIndivBonusList = true;
                },
                
                selectIndivBonus(bonus) {
                    this.individualForm.bonus_name = bonus;
                    this.indivBonusSearch = '';
                    this.showIndivBonusList = false;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                clearIndivBonus() {
                    this.individualForm.bonus_name = '';
                    this.indivBonusSearch = '';
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                async addBulk() {
                    if (!this.bulkForm.bonus_name || !this.bulkForm.amount) {
                        alert('Please select bonus type and enter amount');
                        return;
                    }
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'add_bulk',
                                ...this.bulkForm,
                                month: this.period.month,
                                year: this.period.year,
                                session_id: this.sessionId
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.showSuccess(data.message);
                            this.bulkForm = { department: '', category: '', bonus_name: '', amount: 0, notes: '' };
                            this.bulkBonusSearch = '';
                            this.refreshStaging();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                async searchEmployees() {
                    if (this.searchQuery.length < 2) {
                        this.searchResults = [];
                        return;
                    }
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'search_employees', query: this.searchQuery })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.searchResults = data.employees;
                            this.showSearch = true;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
                
                selectEmployee(emp) {
                    this.individualForm.employee_id = emp.id;
                    this.individualForm.employee_name = emp.first_name + ' ' + emp.last_name;
                    this.searchQuery = '';
                    this.searchResults = [];
                    this.showSearch = false;
                },
                
                clearEmployee() {
                    this.individualForm.employee_id = null;
                    this.individualForm.employee_name = '';
                },
                
                async addIndividual() {
                    if (!this.individualForm.employee_id || !this.individualForm.bonus_name || !this.individualForm.amount) {
                        alert('Please select employee, bonus type, and enter amount');
                        return;
                    }
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'add_individual',
                                ...this.individualForm,
                                month: this.period.month,
                                year: this.period.year,
                                session_id: this.sessionId
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.showSuccess('Entry added');
                            this.clearEmployee();
                            this.individualForm.bonus_name = '';
                            this.indivBonusSearch = '';
                            this.individualForm.amount = 0;
                            this.refreshStaging();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                async refreshStaging() {
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'get_staging',
                                session_id: this.sessionId,
                                month: this.period.month,
                                year: this.period.year
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.staging = data.entries;
                            this.stagingTotal = data.total_amount;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                async removeEntry(entryId) {
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'remove_entry', entry_id: entryId })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.refreshStaging();
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
                
                async clearStaging() {
                    if (!confirm('Clear all staging entries?')) return;
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'clear_staging', session_id: this.sessionId })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.refreshStaging();
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
                
                async executePayroll() {
                    if (!confirm('Generate supplementary payroll? PAYE tax will be calculated and deducted.')) return;
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'execute',
                                session_id: this.sessionId,
                                month: this.period.month,
                                year: this.period.year
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.showSuccess(`Supplementary payroll generated! ${data.employees_processed} employees processed.`);
                            this.refreshStaging();
                            // Fetch generated sheet
                            await this.fetchGeneratedSheet(data.run_id);
                            // Reset session for next run
                            this.sessionId = 'supp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                async fetchGeneratedSheet(runId) {
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'get_sheet', run_id: runId })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.generatedSheet = data.entries;
                            this.originalSheet = JSON.parse(JSON.stringify(data.entries));
                            this.sheetTotals = data.totals;
                            this.currentRunId = runId;
                            this.sheetExpanded = true;
                            this.hasSheetEdits = false;
                            if (data.audit) {
                                this.sheetAudit = data.audit;
                            }
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                recalculateEntry(idx) {
                    // Mark as edited
                    this.hasSheetEdits = true;
                    const entry = this.generatedSheet[idx];
                    const gross = parseFloat(entry.gross_salary) || 0;
                    // Recalculate PAYE (simplified - 10% for supplementary)
                    const paye = gross * 0.10;
                    entry.total_deductions = paye;
                    entry.net_pay = gross - paye;
                    this.recalculateTotals();
                },
                
                recalculateTotals() {
                    let gross = 0, deductions = 0, net = 0;
                    for (const emp of this.generatedSheet) {
                        gross += parseFloat(emp.gross_salary) || 0;
                        deductions += parseFloat(emp.total_deductions) || 0;
                        net += parseFloat(emp.net_pay) || 0;
                    }
                    this.sheetTotals = { gross, deductions, net };
                },
                
                async saveSheetEdits() {
                    if (!confirm('Save changes to this payroll? An audit trail will be recorded.')) return;
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/supplementary_payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'save_sheet_edits',
                                run_id: this.currentRunId,
                                entries: this.generatedSheet.map(e => ({
                                    id: e.id,
                                    gross_salary: e.gross_salary,
                                    total_deductions: e.total_deductions,
                                    net_pay: e.net_pay
                                }))
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.showSuccess('Changes saved successfully');
                            this.hasSheetEdits = false;
                            this.originalSheet = JSON.parse(JSON.stringify(this.generatedSheet));
                            this.sheetAudit = {
                                last_edited: new Date().toLocaleString(),
                                edited_by: data.edited_by || 'Current User'
                            };
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                printSheet() {
                    const printWindow = window.open('', '_blank');
                    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
                    const periodStr = monthNames[this.period.month - 1] + ' ' + this.period.year;
                    
                    let rows = '';
                    this.generatedSheet.forEach(emp => {
                        rows += `<tr>
                            <td style="padding:8px;border:1px solid #ddd;">${emp.employee_name}</td>
                            <td style="padding:8px;border:1px solid #ddd;">${emp.department || '-'}</td>
                            <td style="padding:8px;border:1px solid #ddd;text-align:right;">${this.formatCurrency(emp.gross_salary)}</td>
                            <td style="padding:8px;border:1px solid #ddd;text-align:right;">${this.formatCurrency(emp.total_deductions)}</td>
                            <td style="padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;">${this.formatCurrency(emp.net_pay)}</td>
                        </tr>`;
                    });
                    
                    printWindow.document.write(`
                        <html><head><title>Supplementary Payroll - ${periodStr}</title>
                        <style>body{font-family:Arial,sans-serif;padding:20px;}table{width:100%;border-collapse:collapse;}
                        th{background:#f3f4f6;padding:10px;border:1px solid #ddd;text-align:left;}
                        .header{text-align:center;margin-bottom:20px;}.totals{font-weight:bold;background:#f9fafb;}</style></head>
                        <body>
                        <div class="header">
                            <h2><?php echo htmlspecialchars($company_name); ?></h2>
                            <h3>Supplementary Payroll Sheet - ${periodStr}</h3>
                            <p>Generated: ${new Date().toLocaleString()}</p>
                        </div>
                        <table>
                            <thead><tr><th>Employee</th><th>Department</th><th>Gross Bonus</th><th>PAYE Tax</th><th>Net Pay</th></tr></thead>
                            <tbody>${rows}</tbody>
                            <tfoot><tr class="totals">
                                <td colspan="2" style="padding:10px;border:1px solid #ddd;">Total</td>
                                <td style="padding:10px;border:1px solid #ddd;text-align:right;">${this.formatCurrency(this.sheetTotals.gross)}</td>
                                <td style="padding:10px;border:1px solid #ddd;text-align:right;">${this.formatCurrency(this.sheetTotals.deductions)}</td>
                                <td style="padding:10px;border:1px solid #ddd;text-align:right;">${this.formatCurrency(this.sheetTotals.net)}</td>
                            </tr></tfoot>
                        </table>
                        ${this.sheetAudit.last_edited ? '<p style="margin-top:20px;color:#666;">Last edited by ' + this.sheetAudit.edited_by + ' on ' + this.sheetAudit.last_edited + '</p>' : ''}
                        </body></html>
                    `);
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                },
                
                exportSheet() {
                    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
                    const periodStr = monthNames[this.period.month - 1] + '_' + this.period.year;
                    
                    let csv = 'Employee,Department,Gross Bonus,PAYE Tax,Net Pay\n';
                    this.generatedSheet.forEach(emp => {
                        csv += `"${emp.employee_name}","${emp.department || ''}",${emp.gross_salary},${emp.total_deductions},${emp.net_pay}\n`;
                    });
                    csv += `\nTotal,,${this.sheetTotals.gross},${this.sheetTotals.deductions},${this.sheetTotals.net}\n`;
                    if (this.sheetAudit.last_edited) {
                        csv += `\nLast Edited By,${this.sheetAudit.edited_by}\nEdit Date,${this.sheetAudit.last_edited}\n`;
                    }
                    csv += `Export Date,${new Date().toLocaleString()}\n`;
                    
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Supplementary_Payroll_${periodStr}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                },
                
                async loadRuns() {
                    alert('View past runs - coming soon');
                },
                
                formatCurrency(amount) {
                    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(parseFloat(amount) || 0);
                },
                
                showSuccess(msg) {
                    this.successMessage = msg;
                    setTimeout(() => this.successMessage = '', 3000);
                }
            }
        }
    </script>
</body>
</html>
