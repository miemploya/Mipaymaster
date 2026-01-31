<?php
require_once '../includes/functions.php';
require_login();

// 1. Verify Role
if ($_SESSION['role'] !== 'employee') {
    // If Admin tries to access, maybe allow or redirect?
    // STRICT: Only employees.
    // Allow admins to view for testing?
    // Let's stick to strict role check for now, or check if user has a linked employee record.
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];

// 2. Fetch Employee Record
$stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found. Please contact HR.");
}

$employee_id = $employee['id'];

// Fetch Employee Category
$category_name = 'Uncategorized';
if (!empty($employee['salary_category_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM salary_categories WHERE id = ?");
    $stmt->execute([$employee['salary_category_id']]);
    $cat = $stmt->fetch();
    if ($cat) $category_name = $cat['name'];
}

// 3. Fetch Today's Attendance
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?");
$stmt->execute([$employee_id, $today]);
$attendance = $stmt->fetch();

// 4. Fetch Recent Payslips (Limit 5)
$stmt = $pdo->prepare("
    SELECT pe.*, pr.period_month, pr.period_year, pr.status
    FROM payroll_entries pe
    JOIN payroll_runs pr ON pe.payroll_run_id = pr.id
    WHERE pe.employee_id = ? AND pr.status = 'approved'
    ORDER BY pr.period_year DESC, pr.period_month DESC
    LIMIT 6
");
$stmt->execute([$employee_id]);
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Attendance History (Current Month)
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT date, check_in_time, check_out_time, status, auto_deduction_amount 
    FROM attendance_logs 
    WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
    ORDER BY date DESC
");
$stmt->execute([$employee_id, $current_month]);
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Fetch Leave Types for this company
$stmt = $pdo->prepare("SELECT id, name FROM leave_types WHERE company_id = ? AND is_active = 1 ORDER BY is_system DESC, name");
$stmt->execute([$company_id]);
$leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Fetch Leave Balances for this employee (current year)
$current_year = date('Y');
$stmt = $pdo->prepare("
    SELECT lt.name as leave_type, lb.balance_days, lb.used_days, lb.carry_over_days,
           (lb.balance_days + lb.carry_over_days - lb.used_days) as available
    FROM employee_leave_balances lb
    JOIN leave_types lt ON lb.leave_type_id = lt.id
    WHERE lb.employee_id = ? AND lb.year = ?
      AND (lb.balance_days > 0 OR lb.used_days > 0 OR lb.carry_over_days > 0)
    ORDER BY lt.name
");
$stmt->execute([$employee_id, $current_year]);
$leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total available leave days
$total_leave_available = array_sum(array_column($leave_balances, 'available'));

// 8. Fetch Loan Data for this employee
$stmt = $pdo->prepare("SELECT * FROM loans WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$employee_id]);
$all_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate by status
$approved_loans = array_filter($all_loans, fn($l) => $l['status'] === 'approved');
$pending_loans = array_filter($all_loans, fn($l) => $l['status'] === 'pending');
$rejected_loans = array_filter($all_loans, fn($l) => $l['status'] === 'rejected');

// Calculate totals
$total_loan_balance = array_sum(array_column($approved_loans, 'balance'));
$total_monthly_repayment = array_sum(array_column($approved_loans, 'repayment_amount'));

// 9. Fetch Today's Schedule using the centralized resolver
// This handles all shift types: Fixed, Rotational, Weekly, Monthly, and Daily Mode
require_once '../includes/shift_schedule_resolver.php';

$today = date('Y-m-d');
$employee_schedule = resolve_employee_schedule($pdo, $employee_id, $today, $company_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal - Mipaymaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81' } }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'); body { font-family: 'Inter', sans-serif; } [x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 min-h-screen" x-data="staffPortal()">

    <!-- Navbar -->
    <nav class="bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-6 h-16 flex items-center justify-between shadow-sm sticky top-0 z-30">
        <div class="flex items-center gap-4">
            <img src="../assets/images/logo-light.png" alt="Logo" class="h-8 block dark:hidden">
            <img src="../assets/images/logo-dark.png" alt="Logo" class="h-8 hidden dark:block">
            <div class="h-6 w-px bg-slate-200 dark:bg-slate-700"></div>
            <h1 class="text-lg font-bold text-slate-700 dark:text-slate-300">Staff Portal</h1>
        </div>
        <div class="flex items-center gap-4">
            <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
            </button>
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($employee['job_title']); ?></p>
                    <p class="text-xs text-brand-600 dark:text-brand-400 font-medium"><?php echo htmlspecialchars($employee['payroll_id'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($category_name); ?></p>
                </div>
                <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-brand-600 dark:text-brand-400 font-bold">
                    <?php echo strtoupper(substr($employee['first_name'],0,1).substr($employee['last_name'],0,1)); ?>
                </div>
                <a href="../auth/logout.php" class="ml-2 text-slate-400 hover:text-red-500"><i data-lucide="log-out" class="w-5 h-5"></i></a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto p-6 lg:p-8">
        
        <!-- Welcome Banner -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Good <?php echo (date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening')); ?>, <?php echo htmlspecialchars($employee['first_name']); ?>! 👋</h2>
            <p class="text-slate-500 dark:text-slate-400">Here's your dashboard overview.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- 1. TIME CLOCK WIDGET -->
            <div class="bg-white dark:bg-slate-950 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i data-lucide="clock" class="w-24 h-24 text-brand-600"></i>
                </div>
                
                <div class="flex justify-between items-start mb-1">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Time Clock</h3>
                    <?php if ($employee_schedule['mode'] === 'shift' && $employee_schedule['shift_name']): ?>
                    <span class="px-2 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs font-bold rounded-full">
                        <?php echo htmlspecialchars($employee_schedule['shift_name']); ?>
                    </span>
                    <?php else: ?>
                    <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-full">
                        Daily Schedule
                    </span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-500 mb-4" x-text="todayDate"></p>

                <!-- Expected Times Info -->
                <?php if ($employee_schedule['is_working_day']): ?>
                <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-900 rounded-lg px-3 py-2 text-xs mb-4">
                    <div class="flex items-center gap-2">
                        <i data-lucide="log-in" class="w-3.5 h-3.5 text-green-500"></i>
                        <span class="text-slate-500">Expected In:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300"><?php echo $employee_schedule['expected_in']; ?></span>
                    </div>
                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700"></div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="log-out" class="w-3.5 h-3.5 text-red-500"></i>
                        <span class="text-slate-500">Expected Out:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300"><?php echo $employee_schedule['expected_out']; ?></span>
                    </div>
                </div>
                <p class="text-xs text-slate-400 text-center mb-4">Grace period: <?php echo $employee_schedule['grace']; ?> mins</p>
                <?php else: ?>
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-4 text-center">
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-300">
                        <i data-lucide="info" class="w-4 h-4 inline-block mr-1"></i>
                        Today is not a scheduled working day
                    </p>
                </div>
                <?php endif; ?>

                <div class="text-center mb-4">
                    <div class="text-4xl font-mono font-bold text-slate-900 dark:text-white tracking-wider" x-text="currentTime"></div>
                </div>

                <!-- ACTIONS -->
                <div class="space-y-3">
                    <?php if (!$employee_schedule['is_working_day']): ?>
                        <div class="p-3 bg-slate-100 dark:bg-slate-900 rounded-xl text-center text-sm text-slate-500">
                            Clock-in is not available on non-working days
                        </div>
                    <?php elseif (!$attendance): ?>
                        <button @click="clockIn()" :disabled="loading" class="w-full py-4 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold shadow-lg shadow-brand-500/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="log-in" class="w-5 h-5"></i> <span x-text="loading ? 'Processing...' : 'Clock In'"></span>
                        </button>
                    <?php elseif (!$attendance['check_out_time']): ?>
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg text-center text-sm font-medium mb-2 border border-green-100 dark:border-green-900/30">
                            Clocked In at <?php echo date('H:i', strtotime($attendance['check_in_time'])); ?>
                        </div>
                        <button @click="clockOut()" :disabled="loading" class="w-full py-4 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white rounded-xl font-bold transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="log-out" class="w-5 h-5"></i> <span x-text="loading ? 'Processing...' : 'Clock Out'"></span>
                        </button>
                    <?php else: ?>
                        <div class="p-4 bg-slate-100 dark:bg-slate-900 rounded-xl text-center">
                            <p class="text-sm text-slate-500">Attendance Completed</p>
                            <div class="flex justify-center gap-4 mt-2 font-mono text-sm font-bold text-slate-700 dark:text-slate-300">
                                <span>IN: <?php echo date('H:i', strtotime($attendance['check_in_time'])); ?></span>
                                <span class="text-slate-300">|</span>
                                <span>OUT: <?php echo date('H:i', strtotime($attendance['check_out_time'])); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. QUICK STATS -->
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                 <!-- Leave -->
                 <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl p-6 border border-indigo-100 dark:border-indigo-900/30">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg text-indigo-600"><i data-lucide="calendar" class="w-5 h-5"></i></div>
                            <h3 class="font-bold text-indigo-900 dark:text-indigo-100">Leave Balance</h3>
                        </div>
                        <?php if (!empty($leave_balances)): ?>
                        <button @click="leaveBalanceModalOpen = true" class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg text-indigo-600 hover:bg-indigo-200 dark:hover:bg-indigo-800/50 transition-colors" title="View All Leave Balances">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($leave_balances)): ?>
                    <div class="text-sm text-indigo-600 dark:text-indigo-400">No leave balances assigned yet.</div>
                    <?php else: ?>
                    <div class="space-y-2 mb-3">
                        <?php foreach (array_slice($leave_balances, 0, 3) as $bal): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-700 dark:text-indigo-300"><?= htmlspecialchars($bal['leave_type']) ?></span>
                            <span class="font-bold text-indigo-900 dark:text-indigo-100"><?= number_format($bal['available'], 1) ?> days</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-xs text-indigo-500 mt-2">Total: <strong><?= number_format($total_leave_available, 1) ?></strong> days available</div>
                    <?php endif; ?>
                    <button @click="leaveModalOpen = true" class="mt-4 text-xs font-bold text-indigo-600 hover:underline">Request Leave &rarr;</button>
                 </div>

                 <!-- Loan -->
                 <div class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl p-6 border border-amber-100 dark:border-amber-900/30">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-amber-100 dark:bg-amber-900/50 rounded-lg text-amber-600"><i data-lucide="credit-card" class="w-5 h-5"></i></div>
                            <h3 class="font-bold text-amber-900 dark:text-amber-100">Active Loans</h3>
                        </div>
                        <?php if (!empty($all_loans)): ?>
                        <button @click="loanViewModalOpen = true" class="p-2 bg-amber-100 dark:bg-amber-900/50 rounded-lg text-amber-600 hover:bg-amber-200 dark:hover:bg-amber-800/50 transition-colors" title="View Loan Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-bold text-amber-700 dark:text-amber-300">₦ <?php echo number_format($total_loan_balance); ?></span>
                    </div>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Monthly: ₦<?= number_format($total_monthly_repayment) ?></p>
                    <button @click="loanModalOpen = true" class="mt-4 text-xs font-bold text-amber-600 hover:underline">Apply for Loan &rarr;</button>
                 </div>
                 
                 <!-- Messages / HR Contact -->
                 <div class="bg-rose-50 dark:bg-rose-900/20 rounded-2xl p-6 border border-rose-100 dark:border-rose-900/30">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-rose-100 dark:bg-rose-900/50 rounded-lg text-rose-600"><i data-lucide="message-square" class="w-5 h-5"></i></div>
                            <h3 class="font-bold text-rose-900 dark:text-rose-100">HR Messages</h3>
                        </div>
                        <button @click="messagesModalOpen = true" class="p-2 bg-rose-100 dark:bg-rose-900/50 rounded-lg text-rose-600 hover:bg-rose-200 dark:hover:bg-rose-800/50 transition-colors" title="View Messages">
                            <i data-lucide="inbox" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <p class="text-sm text-rose-700 dark:text-rose-300 mb-2">Contact HR for support</p>
                    <p class="text-xs text-rose-500">Raise complaints, feedback, or inquiries</p>
                    <button @click="newCaseModalOpen = true" class="mt-4 text-xs font-bold text-rose-600 hover:underline">Submit a Case &rarr;</button>
                 </div>
                 
                 <!-- ID Card -->
                 <div class="bg-teal-50 dark:bg-teal-900/20 rounded-2xl p-6 border border-teal-100 dark:border-teal-900/30">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-teal-100 dark:bg-teal-900/50 rounded-lg text-teal-600"><i data-lucide="id-card" class="w-5 h-5"></i></div>
                            <h3 class="font-bold text-teal-900 dark:text-teal-100">My ID Card</h3>
                        </div>
                        <a href="../ajax/id_card_ajax.php?action=download&employee_id=<?php echo $employee_id; ?>" target="_blank" class="p-2 bg-teal-100 dark:bg-teal-900/50 rounded-lg text-teal-600 hover:bg-teal-200 dark:hover:bg-teal-800/50 transition-colors" title="Download ID Card">
                            <i data-lucide="download" class="w-4 h-4"></i>
                        </a>
                    </div>
                    <p class="text-sm text-teal-700 dark:text-teal-300 mb-2">Download your employee ID card</p>
                    <p class="text-xs text-teal-500">Printable format with QR code</p>
                    <a href="../ajax/id_card_ajax.php?action=download&employee_id=<?php echo $employee_id; ?>" target="_blank" class="mt-4 inline-block text-xs font-bold text-teal-600 hover:underline">Download ID Card &rarr;</a>
                 </div>
            </div>
        </div>

        <!-- 3. PAYSLIP HISTORY -->
        <div class="mb-6">
            <button onclick="toggleSection('payslips')" class="flex items-center justify-between w-full text-left mb-4 p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Recent Payslips</h3>
                <i data-lucide="chevron-down" id="payslips-icon" class="w-5 h-5 text-slate-500 transition-transform" style="transform: rotate(-90deg)"></i>
            </button>
            <div id="payslips-content" class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm" style="display: none;">
                <!-- Filter Bar -->
                <div class="p-4 border-b-2 border-brand-100 dark:border-brand-900/30 bg-gradient-to-r from-slate-50 to-brand-50/30 dark:from-slate-900 dark:to-brand-950/20">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-1.5 text-brand-600 dark:text-brand-400">
                            <i data-lucide="filter" class="w-4 h-4"></i>
                            <span class="text-xs font-bold uppercase tracking-wide">Filters</span>
                        </div>
                        <div class="h-5 w-px bg-slate-300 dark:bg-slate-700"></div>
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-lg px-3 py-1.5 shadow-sm border border-slate-200 dark:border-slate-700">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400">Year:</label>
                            <select id="payslip-year-filter" onchange="filterPayslips()" class="text-sm bg-transparent border-0 focus:ring-0 py-0 pr-6 pl-0 text-slate-800 dark:text-slate-200">
                                <option value="">All</option>
                                <?php 
                                $years = array_unique(array_column($payslips, 'period_year'));
                                rsort($years);
                                foreach ($years as $yr): ?>
                                <option value="<?= $yr ?>"><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-lg px-3 py-1.5 shadow-sm border border-slate-200 dark:border-slate-700">
                            <i data-lucide="calendar-days" class="w-3.5 h-3.5 text-slate-400"></i>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400">Month:</label>
                            <select id="payslip-month-filter" onchange="filterPayslips()" class="text-sm bg-transparent border-0 focus:ring-0 py-0 pr-6 pl-0 text-slate-800 dark:text-slate-200">
                                <option value="">All</option>
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
                        <button onclick="clearPayslipFilters()" class="flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-2 py-1 rounded transition-colors">
                            <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                            Clear
                        </button>
                    </div>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                        <tr>
                            <th class="px-6 py-4 font-medium">Period</th>
                            <th class="px-6 py-4 font-medium text-right">Net Pay</th>
                            <th class="px-6 py-4 font-medium text-center">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="payslips-tbody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($payslips as $slip): ?>
                        <tr class="payslip-row hover:bg-slate-50 dark:hover:bg-slate-900/50" data-year="<?= $slip['period_year'] ?>" data-month="<?= $slip['period_month'] ?>">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                <?php echo date("F Y", mktime(0, 0, 0, $slip['period_month'], 10)); ?>
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-300">
                                ₦ <?php echo number_format($slip['net_pay'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Paid</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="payslip.php?id=<?php echo $slip['id']; ?>" target="_blank" class="text-brand-600 hover:underline font-medium">Download</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payslips)): ?>
                            <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No payslips found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. ATTENDANCE HISTORY -->
        <div class="mb-6">
            <button onclick="toggleSection('attendance')" class="flex items-center justify-between w-full text-left mb-4 p-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Attendance This Month</h3>
                <i data-lucide="chevron-down" id="attendance-icon" class="w-5 h-5 text-slate-500 transition-transform" style="transform: rotate(-90deg)"></i>
            </button>
            <div id="attendance-content" class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm" style="display: none;">
                <!-- Filter Bar -->
                <div class="p-4 border-b-2 border-brand-100 dark:border-brand-900/30 bg-gradient-to-r from-slate-50 to-brand-50/30 dark:from-slate-900 dark:to-brand-950/20">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-1.5 text-brand-600 dark:text-brand-400">
                            <i data-lucide="filter" class="w-4 h-4"></i>
                            <span class="text-xs font-bold uppercase tracking-wide">Filters</span>
                        </div>
                        <div class="h-5 w-px bg-slate-300 dark:bg-slate-700"></div>
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-lg px-3 py-1.5 shadow-sm border border-slate-200 dark:border-slate-700">
                            <i data-lucide="activity" class="w-3.5 h-3.5 text-slate-400"></i>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400">Status:</label>
                            <select id="attendance-status-filter" onchange="filterAttendance()" class="text-sm bg-transparent border-0 focus:ring-0 py-0 pr-6 pl-0 text-slate-800 dark:text-slate-200">
                                <option value="">All</option>
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-lg px-3 py-1.5 shadow-sm border border-slate-200 dark:border-slate-700">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400">From:</label>
                            <input type="date" id="attendance-from-date" onchange="filterAttendance()" class="text-sm bg-transparent border-0 focus:ring-0 py-0 text-slate-800 dark:text-slate-200">
                        </div>
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-lg px-3 py-1.5 shadow-sm border border-slate-200 dark:border-slate-700">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-400">To:</label>
                            <input type="date" id="attendance-to-date" onchange="filterAttendance()" class="text-sm bg-transparent border-0 focus:ring-0 py-0 text-slate-800 dark:text-slate-200">
                        </div>
                        <button onclick="clearAttendanceFilters()" class="flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-2 py-1 rounded transition-colors">
                            <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                            Clear
                        </button>
                    </div>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                        <tr>
                            <th class="px-6 py-4 font-medium">Date</th>
                            <th class="px-6 py-4 font-medium">Check In</th>
                            <th class="px-6 py-4 font-medium">Check Out</th>
                            <th class="px-6 py-4 font-medium text-center">Status</th>
                            <th class="px-6 py-4 font-medium text-right">Deduction</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-tbody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($attendance_history as $log): 
                        $status = strtolower($log['status']);
                        ?>
                        <tr class="attendance-row hover:bg-slate-50 dark:hover:bg-slate-900/50" data-status="<?= $status ?>" data-date="<?= $log['date'] ?>">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                <?php echo date("D, M j", strtotime($log['date'])); ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-300">
                                <?php echo $log['check_in_time'] ? date('h:i A', strtotime($log['check_in_time'])) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-300">
                                <?php echo $log['check_out_time'] ? date('h:i A', strtotime($log['check_out_time'])) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php 
                                $status_classes = [
                                    'present' => 'bg-green-100 text-green-700',
                                    'late' => 'bg-amber-100 text-amber-700',
                                    'absent' => 'bg-red-100 text-red-700'
                                ];
                                $class = $status_classes[$status] ?? 'bg-slate-100 text-slate-700';
                                ?>
                                <span class="px-2 py-1 <?php echo $class; ?> rounded-full text-xs font-bold"><?php echo ucfirst($status); ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($log['auto_deduction_amount'] > 0): ?>
                                    <span class="text-red-600 font-medium">-₦<?php echo number_format($log['auto_deduction_amount'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attendance_history)): ?>
                            <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No attendance records this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- LEAVE REQUEST MODAL -->
    <div x-show="leaveModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="leaveModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Request Leave</h3>
                <button @click="leaveModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form @submit.prevent="submitLeave" class="p-6 space-y-4">
                <div>
                   <label class="block text-xs font-bold text-slate-500 mb-1">Leave Type</label>
                   <select x-model="leaveForm.type" @change="checkBalance()" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                       <option value="">Select Type</option>
                       <?php foreach ($leave_types as $lt): ?>
                       <option value="<?= htmlspecialchars($lt['name']) ?>"><?= htmlspecialchars($lt['name']) ?></option>
                       <?php endforeach; ?>
                   </select>
                   <!-- Balance Warning -->
                   <div x-show="balanceWarning" class="mt-2 p-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-700 dark:text-amber-400">
                       <i data-lucide="alert-triangle" class="w-3 h-3 inline-block mr-1"></i>
                       <span x-text="balanceWarning"></span>
                   </div>
                   <div x-show="selectedBalance !== null" class="mt-1 text-xs text-slate-500">
                       Available: <span class="font-bold" x-text="selectedBalance"></span> days
                   </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Start Date</label>
                        <input type="date" x-model="leaveForm.start_date" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">End Date</label>
                        <input type="date" x-model="leaveForm.end_date" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Reason</label>
                    <textarea x-model="leaveForm.reason" required rows="3" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="Reason for leave..."></textarea>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="leaveModalOpen = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-md">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LEAVE BALANCE VIEW MODAL -->
    <div x-show="leaveBalanceModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="leaveBalanceModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-indigo-50 dark:bg-indigo-900/30">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg text-indigo-600"><i data-lucide="calendar-check" class="w-5 h-5"></i></div>
                    <h3 class="text-lg font-bold text-indigo-900 dark:text-indigo-100">My Leave Balances</h3>
                </div>
                <button @click="leaveBalanceModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6">
                <div class="text-center mb-4">
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"><?= number_format($total_leave_available, 1) ?></p>
                    <p class="text-xs text-slate-500">Total Days Available</p>
                </div>
                <div class="space-y-3 max-h-80 overflow-y-auto">
                    <?php if (empty($leave_balances)): ?>
                    <div class="text-center py-8 text-slate-500">
                        <i data-lucide="calendar-off" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                        <p>No leave balances assigned</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($leave_balances as $bal): ?>
                    <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($bal['leave_type']) ?></p>
                            <p class="text-xs text-slate-500">
                                Entitled: <?= number_format($bal['balance_days'], 1) ?> 
                                <?php if ($bal['carry_over_days'] > 0): ?>
                                + <?= number_format($bal['carry_over_days'], 1) ?> carry-over
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold <?= $bal['available'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>"><?= number_format($bal['available'], 1) ?></p>
                            <p class="text-xs text-slate-500">Available</p>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-slate-400 -mt-1 px-2">
                        <span>Used: <?= number_format($bal['used_days'], 1) ?> days</span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 text-center">
                    <button @click="leaveBalanceModalOpen = false; leaveModalOpen = true" class="text-sm font-bold text-indigo-600 hover:underline">
                        <i data-lucide="plus" class="w-4 h-4 inline-block"></i> Request Leave
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- LOAN VIEW MODAL -->
    <div x-show="loanViewModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="loanViewModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-amber-50 dark:bg-amber-900/30">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-amber-100 dark:bg-amber-900/50 rounded-lg text-amber-600"><i data-lucide="credit-card" class="w-5 h-5"></i></div>
                    <h3 class="text-lg font-bold text-amber-900 dark:text-amber-100">My Loans</h3>
                </div>
                <button @click="loanViewModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6">
                <!-- Summary -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="text-center p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                        <p class="text-xl font-bold text-amber-700 dark:text-amber-300">₦<?= number_format($total_loan_balance) ?></p>
                        <p class="text-xs text-amber-600">Outstanding Balance</p>
                    </div>
                    <div class="text-center p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                        <p class="text-xl font-bold text-slate-700 dark:text-slate-300">₦<?= number_format($total_monthly_repayment) ?></p>
                        <p class="text-xs text-slate-500">Monthly Repayment</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex gap-2 mb-4 border-b border-slate-200 dark:border-slate-700">
                    <button @click="loanViewTab = 'approved'" :class="loanViewTab === 'approved' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-500'" class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">
                        Active (<?= count($approved_loans) ?>)
                    </button>
                    <button @click="loanViewTab = 'pending'" :class="loanViewTab === 'pending' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-500'" class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">
                        Pending (<?= count($pending_loans) ?>)
                    </button>
                    <button @click="loanViewTab = 'rejected'" :class="loanViewTab === 'rejected' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-500'" class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">
                        Rejected (<?= count($rejected_loans) ?>)
                    </button>
                    <button @click="loanViewTab = 'history'" :class="loanViewTab === 'history' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-500'" class="px-3 py-2 text-xs font-bold border-b-2 transition-colors">
                        All
                    </button>
                </div>

                <!-- Loan List -->
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php if (empty($all_loans)): ?>
                    <div class="text-center py-8 text-slate-500">
                        <i data-lucide="wallet" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                        <p>No loans found</p>
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($all_loans as $loan): 
                        $statusClass = match($loan['status']) {
                            'approved' => 'bg-green-100 text-green-700',
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'rejected' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-700'
                        };
                    ?>
                    <div x-show="loanViewTab === '<?= $loan['status'] ?>' || loanViewTab === 'history'" class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white capitalize"><?= htmlspecialchars(str_replace('_', ' ', $loan['loan_type'])) ?></p>
                                <p class="text-xs text-slate-500">Applied: <?= date('M d, Y', strtotime($loan['created_at'])) ?></p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-bold <?= $statusClass ?> capitalize"><?= $loan['status'] ?></span>
                        </div>
                        <div class="mt-2 flex justify-between text-sm">
                            <span class="text-slate-600 dark:text-slate-400">Amount: ₦<?= number_format($loan['principal_amount'] ?? 0) ?></span>
                            <?php if ($loan['status'] === 'approved'): ?>
                            <span class="text-amber-600 font-bold">Balance: ₦<?= number_format($loan['balance']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($loan['status'] === 'approved'): ?>
                        <div class="mt-1 text-xs text-slate-500">Monthly: ₦<?= number_format($loan['repayment_amount'] ?? 0) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 text-center">
                    <button @click="loanViewModalOpen = false; loanModalOpen = true" class="text-sm font-bold text-amber-600 hover:underline">
                        <i data-lucide="plus" class="w-4 h-4 inline-block"></i> Apply for Loan
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- LOAN APPLICATION MODAL -->
    <div x-show="loanModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="loanModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
             <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Apply for Loan</h3>
                <button @click="loanModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form @submit.prevent="submitLoan" class="p-6 space-y-4">
                 <div class="bg-amber-50 text-amber-800 p-3 rounded-lg text-xs mb-2">
                    Note: Loan applications are subject to HR and Management approval. Interest rates may apply.
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Loan Type</label>
                        <select x-model="loanForm.type" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            <option value="salary_advance">Salary Advance</option>
                            <option value="housing">Housing Loan</option>
                            <option value="car">Car Loan</option>
                            <option value="personal">Personal Loan</option>
                            <option value="medical">Medical Loan</option>
                            <option value="education">Education Loan</option>
                            <option value="emergency">Emergency Loan</option>
                            <option value="festival">Festival Advance</option>
                            <option value="wedding">Wedding Loan</option>
                            <option value="compassionate">Compassionate Loan</option>
                            <option value="furniture">Furniture/Appliance</option>
                            <option value="training">Training/Development</option>
                            <option value="travel">Travel Loan</option>
                            <option value="relocation">Relocation Loan</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                     <div x-show="loanForm.type === 'other'">
                        <label class="block text-xs font-bold text-slate-500 mb-1">Specify</label>
                        <input type="text" x-model="loanForm.custom_type" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Amount Needed</label>
                         <input type="number" x-model="loanForm.amount" required min="1" step="0.01" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                    <div>
                         <label class="block text-xs font-bold text-slate-500 mb-1">Monthly Repayment</label>
                         <input type="number" x-model="loanForm.repayment" required min="1" step="0.01" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Start Month</label>
                        <select x-model="loanForm.start_month" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                     <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Start Year</label>
                        <input type="number" x-model="loanForm.start_year" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Reason for Loan</label>
                    <textarea x-model="loanForm.reason" required rows="3" placeholder="Please explain why you need this loan..." class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="loanModalOpen = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg font-medium text-sm">Cancel</button>
                    <button type="submit" :disabled="loading" class="px-6 py-2.5 bg-amber-600 text-white rounded-lg font-bold hover:bg-amber-700 disabled:opacity-50 flex items-center gap-2 text-sm">
                        <i data-lucide="send" class="w-4 h-4"></i> 
                        <span x-text="loading ? 'Submitting...' : 'Submit Application'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MY MESSAGES MODAL (Tabbed: My Cases + HR Inbox) -->
    <div x-show="messagesModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" x-init="$watch('messagesModalOpen', v => v && loadMyCases())">
        <div @click.outside="messagesModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-rose-50 dark:bg-rose-900/30">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-rose-100 dark:bg-rose-900/50 rounded-lg text-rose-600"><i data-lucide="inbox" class="w-5 h-5"></i></div>
                    <h3 class="text-lg font-bold text-rose-900 dark:text-rose-100">Messages & Cases</h3>
                </div>
                <button @click="messagesModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            
            <!-- Tabs -->
            <div class="flex border-b border-slate-200 dark:border-slate-800">
                <button @click="messageTab = 'inbox'" :class="messageTab === 'inbox' ? 'border-rose-600 text-rose-600 bg-rose-50 dark:bg-rose-900/20' : 'border-transparent text-slate-500'" class="flex-1 px-4 py-3 text-sm font-bold border-b-2 transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="mail" class="w-4 h-4"></i> HR Inbox
                    <template x-if="hrInbox.length > 0">
                        <span class="bg-rose-600 text-white text-xs px-1.5 py-0.5 rounded-full" x-text="hrInbox.length"></span>
                    </template>
                </button>
                <button @click="messageTab = 'cases'" :class="messageTab === 'cases' ? 'border-rose-600 text-rose-600 bg-rose-50 dark:bg-rose-900/20' : 'border-transparent text-slate-500'" class="flex-1 px-4 py-3 text-sm font-bold border-b-2 transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="folder" class="w-4 h-4"></i> My Cases
                </button>
            </div>
            
            <div class="p-6 max-h-80 overflow-y-auto">
                <!-- HR INBOX TAB -->
                <div x-show="messageTab === 'inbox'">
                    <template x-if="hrInbox.length === 0">
                        <div class="text-center py-8 text-slate-500">
                            <i data-lucide="mail-open" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                            <p>No messages from HR</p>
                            <p class="text-xs mt-2">Messages from HR will appear here</p>
                        </div>
                    </template>
                    <template x-for="c in hrInbox" :key="c.id">
                        <div @click="openCase(c.id)" class="p-4 mb-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800 hover:border-blue-300 cursor-pointer transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-blue-600" x-text="c.case_number"></span>
                                <span class="px-2 py-1 rounded text-xs font-bold capitalize"
                                      :class="{'bg-orange-100 text-orange-700': c.status === 'awaiting_response', 'bg-green-100 text-green-700': c.status === 'resolved', 'bg-slate-100 text-slate-700': c.status === 'closed', 'bg-blue-100 text-blue-700': c.status === 'in_review'}"
                                      x-text="c.status.replace('_', ' ')"></span>
                            </div>
                            <p class="font-bold text-slate-900 dark:text-white" x-text="c.subject"></p>
                            <div class="flex justify-between items-center mt-2 text-xs text-slate-500">
                                <span class="text-blue-600 font-bold">From: HR</span>
                                <span x-text="new Date(c.created_at).toLocaleDateString()"></span>
                            </div>
                        </div>
                    </template>
                </div>
                
                <!-- MY CASES TAB -->
                <div x-show="messageTab === 'cases'">
                    <template x-if="myCases.length === 0">
                        <div class="text-center py-8 text-slate-500">
                            <i data-lucide="folder-open" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                            <p>No cases submitted yet</p>
                            <button @click="messagesModalOpen = false; newCaseModalOpen = true" class="mt-3 text-sm text-rose-600 font-bold hover:underline">Submit your first case</button>
                        </div>
                    </template>
                    <template x-for="c in myCases" :key="c.id">
                        <div @click="openCase(c.id)" class="p-4 mb-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 hover:border-rose-300 cursor-pointer transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-slate-500" x-text="c.case_number"></span>
                                <span class="px-2 py-1 rounded text-xs font-bold capitalize"
                                      :class="{'bg-yellow-100 text-yellow-700': c.status === 'open', 'bg-blue-100 text-blue-700': c.status === 'in_review', 'bg-orange-100 text-orange-700': c.status === 'awaiting_response', 'bg-green-100 text-green-700': c.status === 'resolved', 'bg-slate-100 text-slate-700': c.status === 'closed'}"
                                      x-text="c.status.replace('_', ' ')"></span>
                            </div>
                            <p class="font-bold text-slate-900 dark:text-white" x-text="c.subject"></p>
                            <div class="flex justify-between items-center mt-2 text-xs text-slate-500">
                                <span x-text="c.case_type" class="capitalize"></span>
                                <span x-text="new Date(c.created_at).toLocaleDateString()"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 text-center">
                <button @click="messagesModalOpen = false; newCaseModalOpen = true" class="text-sm font-bold text-rose-600 hover:underline">
                    <i data-lucide="plus" class="w-4 h-4 inline-block"></i> Submit New Case
                </button>
            </div>
        </div>
    </div>

    <!-- NEW CASE MODAL -->
    <div x-show="newCaseModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="newCaseModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Submit a Case</h3>
                <button @click="newCaseModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form @submit.prevent="submitCase" class="p-6 space-y-4">
                <div class="bg-rose-50 text-rose-800 p-3 rounded-lg text-xs">
                    <i data-lucide="info" class="w-4 h-4 inline-block"></i>
                    Submit complaints, grievances, feedback, or inquiries. HR will respond within 24-48 hours.
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Case Type</label>
                        <select x-model="caseForm.case_type" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            <option value="inquiry">Inquiry</option>
                            <option value="complaint">Complaint</option>
                            <option value="grievance">Grievance</option>
                            <option value="report">Report Issue</option>
                            <option value="feedback">Feedback</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Priority</label>
                        <select x-model="caseForm.priority" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Subject</label>
                    <input type="text" x-model="caseForm.subject" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="Brief summary of your issue">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Description</label>
                    <textarea x-model="caseForm.description" required rows="4" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="Provide details..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Attachment (Optional)</label>
                    <input type="file" id="case-attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                    <p class="text-xs text-slate-400 mt-1">Max 2MB. Formats: PDF, JPG, PNG only.</p>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="newCaseModalOpen = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-rose-600 text-white rounded-lg text-sm font-bold hover:bg-rose-700 shadow-md">Submit Case</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CASE DETAIL MODAL -->
    <div x-show="caseDetailModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="caseDetailModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-900">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white" x-text="selectedCase?.subject"></h3>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-xs font-bold text-slate-500" x-text="selectedCase?.case_number"></span>
                        <span class="px-2 py-0.5 rounded text-xs font-bold capitalize"
                              :class="{'bg-yellow-100 text-yellow-700': selectedCase?.status === 'open', 'bg-blue-100 text-blue-700': selectedCase?.status === 'in_review', 'bg-orange-100 text-orange-700': selectedCase?.status === 'awaiting_response', 'bg-green-100 text-green-700': selectedCase?.status === 'resolved'}"
                              x-text="selectedCase?.status?.replace('_', ' ')"></span>
                    </div>
                </div>
                <button @click="caseDetailModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6">
                <!-- Original Description -->
                <div class="mb-4 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                    <p class="text-xs font-bold text-slate-500 mb-1">Original Message</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300" x-text="selectedCase?.description"></p>
                </div>
                
                <!-- Message Thread -->
                <div class="space-y-3 max-h-60 overflow-y-auto mb-4">
                    <template x-for="msg in caseMessages" :key="msg.id">
                        <div :class="msg.sender_role === 'employee' ? 'ml-8 bg-rose-50 dark:bg-rose-900/20' : 'mr-8 bg-blue-50 dark:bg-blue-900/20'" class="p-3 rounded-lg">
                            <div class="flex justify-between items-center mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold" :class="msg.sender_role === 'employee' ? 'text-rose-700' : 'text-blue-700'" x-text="msg.sender_role === 'employee' ? 'You' : 'HR Team'"></span>
                                    <template x-if="msg.message_title">
                                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-bold" x-text="msg.message_title"></span>
                                    </template>
                                </div>
                                <span class="text-xs text-slate-500" x-text="new Date(msg.created_at).toLocaleString()"></span>
                            </div>
                            <p class="text-sm text-slate-700 dark:text-slate-300" x-text="msg.message"></p>
                            <template x-if="msg.attachment_path">
                                <a :href="msg.attachment_path" download :title="msg.attachment_name" 
                                   class="inline-flex items-center gap-1 mt-2 text-xs text-blue-600 hover:underline bg-blue-50 px-2 py-1 rounded">
                                    <i data-lucide="paperclip" class="w-3 h-3"></i>
                                    <span x-text="msg.attachment_name || 'Download Attachment'"></span>
                                </a>
                            </template>
                            <!-- Acknowledgement for HR messages (staff can acknowledge) -->
                            <template x-if="msg.sender_role === 'hr'">
                                <div class="mt-2">
                                    <template x-if="msg.acknowledged_at">
                                        <span class="inline-flex items-center gap-1 text-xs text-green-600 bg-green-50 px-2 py-1 rounded font-bold">
                                            <i data-lucide="check-circle" class="w-3 h-3"></i>
                                            Acknowledged
                                        </span>
                                    </template>
                                    <template x-if="!msg.acknowledged_at">
                                        <button @click="acknowledgeMessage(msg.id)" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:bg-blue-50 px-2 py-1 rounded border border-blue-200 font-bold">
                                            <i data-lucide="check" class="w-3 h-3"></i>
                                            Acknowledge
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <!-- Acknowledgement display for staff-sent messages (when HR acknowledges) -->
                            <template x-if="msg.sender_role === 'employee' && msg.acknowledged_at">
                                <div class="mt-2">
                                    <span class="inline-flex items-center gap-1 text-xs text-green-600 bg-green-50 px-2 py-1 rounded font-bold">
                                        <i data-lucide="check-circle" class="w-3 h-3"></i>
                                        HR Acknowledged
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Reply Form -->
                <template x-if="selectedCase?.status !== 'closed' && selectedCase?.status !== 'resolved'">
                    <div class="space-y-2">
                        <div class="flex gap-2">
                            <input type="text" x-model="replyMessage" @keydown.enter="replyCase()" class="flex-1 rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="Type your reply...">
                            <button @click="replyCase()" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-bold hover:bg-rose-700">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="file" id="staff-attachment" accept=".pdf,.jpg,.jpeg,.png" class="flex-1 text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                            <span class="text-xs text-slate-400">Max 2MB</span>
                        </div>
                    </div>
                    </div>
                </template>
                <template x-if="selectedCase?.status === 'closed' || selectedCase?.status === 'resolved'">
                    <div class="text-center py-2 text-sm text-slate-500 bg-slate-50 rounded-lg">
                        <i data-lucide="check-circle" class="w-4 h-4 inline-block text-green-600"></i> This case has been closed.
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const themeBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) html.classList.add('dark');
        themeBtn.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
        });

        // Collapsible sections toggle
        function toggleSection(sectionId) {
            var content = document.getElementById(sectionId + '-content');
            var icon = document.getElementById(sectionId + '-icon');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                icon.style.transform = 'rotate(-90deg)';
            }
        }
        
        // Filter Payslips by Year and Month
        function filterPayslips() {
            var year = document.getElementById('payslip-year-filter').value;
            var month = document.getElementById('payslip-month-filter').value;
            var rows = document.querySelectorAll('.payslip-row');
            
            rows.forEach(function(row) {
                var rowYear = row.getAttribute('data-year');
                var rowMonth = row.getAttribute('data-month');
                var matchYear = (year === '' || rowYear === year);
                var matchMonth = (month === '' || rowMonth === month);
                
                if (matchYear && matchMonth) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function clearPayslipFilters() {
            document.getElementById('payslip-year-filter').value = '';
            document.getElementById('payslip-month-filter').value = '';
            filterPayslips();
        }
        
        // Filter Attendance by Status and Date Range
        function filterAttendance() {
            var status = document.getElementById('attendance-status-filter').value;
            var fromDate = document.getElementById('attendance-from-date').value;
            var toDate = document.getElementById('attendance-to-date').value;
            var rows = document.querySelectorAll('.attendance-row');
            
            rows.forEach(function(row) {
                var rowStatus = row.getAttribute('data-status');
                var rowDate = row.getAttribute('data-date');
                
                var matchStatus = (status === '' || rowStatus === status);
                var matchFrom = (fromDate === '' || rowDate >= fromDate);
                var matchTo = (toDate === '' || rowDate <= toDate);
                
                if (matchStatus && matchFrom && matchTo) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function clearAttendanceFilters() {
            document.getElementById('attendance-status-filter').value = '';
            document.getElementById('attendance-from-date').value = '';
            document.getElementById('attendance-to-date').value = '';
            filterAttendance();
        }

        function staffPortal() {
            return {
                leaveModalOpen: false,
                loanModalOpen: false,
                leaveBalanceModalOpen: false,
                loanViewModalOpen: false,
                loanViewTab: 'approved',
                messagesModalOpen: false,
                newCaseModalOpen: false,
                caseDetailModalOpen: false,
                messageTab: 'inbox',
                myCases: [],
                hrInbox: [],
                selectedCase: null,
                caseMessages: [],
                caseForm: {
                    case_type: 'inquiry', subject: '', description: '', priority: 'medium'
                },
                replyMessage: '',
                leaveForm: {
                    type: '', start_date: '', end_date: '', reason: ''
                },
                loanForm: {
                    type: 'salary_advance', custom_type: '', amount: '', repayment: '', start_month: new Date().getMonth() + 1, start_year: new Date().getFullYear(), reason: ''
                },
                leaveBalances: <?= json_encode($leave_balances) ?>,
                balanceWarning: '',
                selectedBalance: null,

                // Time Clock Logic
                currentTime: '',
                todayDate: '',
                loading: false,
                init() {
                    this.updateTime();
                    setInterval(() => this.updateTime(), 1000);
                },
                updateTime() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute:'2-digit', second:'2-digit' });
                    this.todayDate = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                },
                async clockIn() { 
                    console.log('clockIn() called');
                    await this.submitAttendance('in'); 
                },
                async clockOut() { 
                    console.log('clockOut() called');
                    await this.submitAttendance('out'); 
                },
                async submitAttendance(type) {
                    console.log('submitAttendance() called with type:', type);
                    if (!confirm('Confirm ' + (type === 'in' ? 'Clock In' : 'Clock Out') + '?')) {
                        console.log('User cancelled');
                        return;
                    }
                    this.loading = true;
                    try {
                        const fd = new FormData(); 
                        fd.append('type', type);
                        console.log('Sending request to attendance_clock.php');
                        const res = await fetch('../ajax/attendance_clock.php', { method: 'POST', body: fd });
                        console.log('Response status:', res.status);
                        const data = await res.json();
                        console.log('Response data:', data);
                        alert(data.message);
                        if (data.status) window.location.reload();
                    } catch (e) { 
                        console.error('Exception:', e);
                        alert('Connection error: ' + e.message); 
                    } finally { 
                        this.loading = false; 
                    }
                },

                // Leave Logic
                checkBalance() {
                    this.balanceWarning = '';
                    this.selectedBalance = null;
                    
                    if (!this.leaveForm.type) return;
                    
                    const balance = this.leaveBalances.find(b => b.leave_type === this.leaveForm.type);
                    if (balance) {
                        this.selectedBalance = parseFloat(balance.available).toFixed(1);
                        if (parseFloat(balance.available) <= 0) {
                            this.balanceWarning = 'You have no remaining balance for this leave type.';
                        }
                    } else {
                        this.balanceWarning = 'No balance assigned for this leave type. Contact HR.';
                    }
                    lucide.createIcons();
                },
                
                async submitLeave() {
                    // Calculate requested days
                    if (this.leaveForm.start_date && this.leaveForm.end_date) {
                        const start = new Date(this.leaveForm.start_date);
                        const end = new Date(this.leaveForm.end_date);
                        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                        
                        const balance = this.leaveBalances.find(b => b.leave_type === this.leaveForm.type);
                        if (balance && parseFloat(balance.available) < days) {
                            alert(`Insufficient balance. You have ${balance.available} days available but are requesting ${days} days.`);
                            return;
                        }
                    }
                    
                    const fd = new FormData();
                    fd.append('action', 'request_leave');
                    fd.append('leave_type', this.leaveForm.type);
                    fd.append('start_date', this.leaveForm.start_date);
                    fd.append('end_date', this.leaveForm.end_date);
                    fd.append('reason', this.leaveForm.reason);

                    try {
                        const res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) { this.leaveModalOpen = false; this.leaveForm = { type: '', start_date: '', end_date: '', reason: '' }; this.balanceWarning = ''; this.selectedBalance = null; }
                    } catch (e) { alert('Error: ' + e); }
                },

                // Loan Logic
                async submitLoan() {
                    if (!this.loanForm.amount || !this.loanForm.repayment || !this.loanForm.reason) {
                        alert('Please fill in all required fields including reason.');
                        return;
                    }
                    
                    this.loading = true;
                    const fd = new FormData();
                    fd.append('action', 'create_loan');
                    // Backend auto-detects employee_id for 'employee' role - no need to send it
                    fd.append('loan_type', this.loanForm.type);
                    if (this.loanForm.type === 'other') fd.append('custom_type', this.loanForm.custom_type);
                    fd.append('principal_amount', this.loanForm.amount);
                    fd.append('repayment_amount', this.loanForm.repayment);
                    fd.append('interest_rate', '0'); // Default to 0% interest for employee self-service
                    fd.append('start_month', this.loanForm.start_month);
                    fd.append('start_year', this.loanForm.start_year);
                    fd.append('reason', this.loanForm.reason);

                    console.log('Employee submitting loan:', {
                        type: this.loanForm.type,
                        amount: this.loanForm.amount,
                        repayment: this.loanForm.repayment,
                        start: `${this.loanForm.start_month}/${this.loanForm.start_year}`
                    });

                    try {
                        const res = await fetch('../ajax/loan_operations.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        
                        console.log('Server response:', data);
                        
                        if (data.status) {
                            alert('Loan application submitted successfully! (ID: ' + data.loan_id + ')');
                            this.loanModalOpen = false;
                            this.loanForm = { type: 'salary_advance', custom_type: '', amount: '', repayment: '', start_month: new Date().getMonth() + 1, start_year: new Date().getFullYear(), reason: '' };
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        console.error('Submission error:', e);
                        alert('Error: ' + e);
                    } finally {
                        this.loading = false;
                    }
                },

                // Case/Messaging Logic
                async loadMyCases() {
                    try {
                        // Load staff-submitted cases
                        const fd1 = new FormData();
                        fd1.append('action', 'get_my_cases');
                        const res1 = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd1 });
                        const data1 = await res1.json();
                        if (data1.status) {
                            this.myCases = data1.cases.filter(c => c.case_number.startsWith('CASE-'));
                            this.hrInbox = data1.cases.filter(c => c.case_number.startsWith('MSG-') || c.case_number.startsWith('MANUAL-'));
                        }
                    } catch (e) { console.error('Error loading cases:', e); }
                },

                async submitCase() {
                    if (!this.caseForm.subject.trim() || !this.caseForm.description.trim()) {
                        alert('Please fill in subject and description.');
                        return;
                    }
                    try {
                        const fd = new FormData();
                        fd.append('action', 'create_case');
                        fd.append('case_type', this.caseForm.case_type);
                        fd.append('subject', this.caseForm.subject);
                        fd.append('description', this.caseForm.description);
                        fd.append('priority', this.caseForm.priority);
                        
                        // Handle attachment
                        const fileInput = document.getElementById('case-attachment');
                        if (fileInput && fileInput.files[0]) {
                            const file = fileInput.files[0];
                            if (file.size > 2 * 1024 * 1024) {
                                alert('File too large. Maximum 2MB allowed.');
                                return;
                            }
                            fd.append('attachment', file);
                        }
                        
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.newCaseModalOpen = false;
                            this.caseForm = { case_type: 'inquiry', subject: '', description: '', priority: 'medium' };
                            if (fileInput) fileInput.value = '';
                            this.loadMyCases();
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async openCase(caseId) {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_case_detail');
                        fd.append('case_id', caseId);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.selectedCase = data.case;
                            this.caseMessages = data.messages;
                            this.caseDetailModalOpen = true;
                            this.replyMessage = '';
                            // Recreate icons after DOM updates
                            setTimeout(() => lucide.createIcons(), 100);
                        } else {
                            alert(data.message);
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async replyCase() {
                    if (!this.replyMessage.trim()) return;
                    try {
                        const fd = new FormData();
                        fd.append('action', 'reply_case');
                        fd.append('case_id', this.selectedCase.id);
                        fd.append('message', this.replyMessage);
                        
                        // Handle attachment
                        const fileInput = document.getElementById('staff-attachment');
                        if (fileInput && fileInput.files[0]) {
                            const file = fileInput.files[0];
                            if (file.size > 2 * 1024 * 1024) {
                                alert('File too large. Maximum 2MB allowed.');
                                return;
                            }
                            fd.append('attachment', file);
                        }
                        
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.replyMessage = '';
                            if (fileInput) fileInput.value = '';
                            this.openCase(this.selectedCase.id); // Refresh messages
                        } else {
                            alert(data.message);
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async acknowledgeMessage(messageId) {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'acknowledge_message');
                        fd.append('message_id', messageId);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.openCase(this.selectedCase.id); // Refresh to show acknowledged status
                            lucide.createIcons(); // Refresh icons
                        } else {
                            alert(data.message);
                        }
                    } catch (e) { alert('Error: ' + e); }
                }
            }
        }
    </script>
</body>
</html>
