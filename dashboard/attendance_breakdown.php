<?php
/**
 * Attendance Deduction Breakdown
 * Shows per-employee attendance deduction details for reconciliation
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'] ?? 'Company';

// Filters
$selected_employee = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get employees for dropdown
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.payroll_id, 
           sc.name as category_name, sc.base_gross_amount
    FROM employees e
    LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
    WHERE e.company_id = ? 
    AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')
    ORDER BY e.first_name, e.last_name
");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance policy for grace period
$stmt_policy = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
$stmt_policy->execute([$company_id]);
$policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
$grace_period = (int)($policy['grace_period_minutes'] ?? 15);

// Get selected employee details
$employee_data = null;
$attendance_records = [];
$summary = ['total_late_minutes' => 0, 'total_absent_days' => 0, 'total_deduction' => 0, 'grace_days' => 0];

if ($selected_employee > 0) {
    // Get employee info
    $stmt_emp = $pdo->prepare("
        SELECT e.*, sc.name as category_name, sc.base_gross_amount
        FROM employees e
        LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
        WHERE e.id = ? AND e.company_id = ?
    ");
    $stmt_emp->execute([$selected_employee, $company_id]);
    $employee_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);
    
    if ($employee_data) {
        // Calculate working days for this employee this month
        require_once '../includes/shift_schedule_resolver.php';
        $month_start = sprintf('%04d-%02d-01', $selected_year, $selected_month);
        $days_in_month = (int) date('t', strtotime($month_start));
        
        $working_days = 0;
        for ($d = 1; $d <= $days_in_month; $d++) {
            $date_str = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $d);
            $schedule = resolve_employee_schedule($pdo, $selected_employee, $date_str, $company_id);
            if (!empty($schedule['is_working_day'])) {
                $working_days++;
            }
        }
        
        $gross_salary = (float)($employee_data['base_gross_amount'] ?? 0);
        $daily_rate = $working_days > 0 ? round($gross_salary / $working_days, 2) : 0;
        
        // Get attendance records with deductions
        $stmt_att = $pdo->prepare("
            SELECT al.*, 
                   TIME_FORMAT(al.check_in_time, '%h:%i %p') as check_in_formatted,
                   TIME_FORMAT(al.check_out_time, '%h:%i %p') as check_out_formatted
            FROM attendance_logs al
            WHERE al.employee_id = ? 
            AND MONTH(al.date) = ? 
            AND YEAR(al.date) = ?
            AND (al.final_deduction_amount > 0 OR al.auto_deduction_amount > 0 
                 OR LOWER(al.status) IN ('late', 'absent'))
            ORDER BY al.date ASC
        ");
        $stmt_att->execute([$selected_employee, $selected_month, $selected_year]);
        $attendance_records = $stmt_att->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate summary
        foreach ($attendance_records as $rec) {
            $deduction = (float)($rec['final_deduction_amount'] ?? $rec['auto_deduction_amount'] ?? 0);
            $summary['total_deduction'] += $deduction;
            
            if (strtolower($rec['status']) === 'absent') {
                $summary['total_absent_days']++;
            } elseif (strtolower($rec['status']) === 'late') {
                $late_mins = (int)($rec['late_minutes'] ?? 0);
                $summary['total_late_minutes'] += $late_mins;
                
                if ($late_mins <= $grace_period && $deduction == 0) {
                    $summary['grace_days']++;
                }
            }
        }
    }
}

// Month names
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Deduction Breakdown - <?php echo htmlspecialchars($company_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    
    <?php include '../includes/dashboard_header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="flex-1 p-6 lg:p-8">
            <!-- Header -->
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Attendance Deduction Breakdown</h1>
                    <p class="text-slate-500 text-sm mt-1">Review per-employee deductions for reconciliation</p>
                </div>
                
                <?php if ($employee_data): ?>
                <div class="flex gap-2 no-print">
                    <button onclick="window.print()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print Report
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 mb-6 shadow-sm no-print">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <!-- Employee Selector -->
                    <div class="flex-1 min-w-[250px]">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Select Employee</label>
                        <select name="employee_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $selected_employee == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['payroll_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Month Selector -->
                    <div class="w-40">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Month</label>
                        <select name="month" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $selected_month == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Year Selector -->
                    <div class="w-28">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Year</label>
                        <select name="year" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium flex items-center gap-2 transition-colors">
                        <i data-lucide="search" class="w-4 h-4"></i> View Breakdown
                    </button>
                </form>
            </div>
            
            <?php if ($employee_data): ?>
            <!-- Print Header (only shows when printing) -->
            <div class="print-only mb-6">
                <h1 class="text-xl font-bold"><?php echo htmlspecialchars($company_name); ?></h1>
                <p class="text-sm">Attendance Deduction Breakdown - <?php echo $months[$selected_month] . ' ' . $selected_year; ?></p>
            </div>
            
            <!-- Employee Info Card -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-6 mb-6 text-white">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold"><?php echo htmlspecialchars($employee_data['first_name'] . ' ' . $employee_data['last_name']); ?></h2>
                        <p class="text-blue-100 text-sm"><?php echo htmlspecialchars($employee_data['payroll_id']); ?> • <?php echo htmlspecialchars($employee_data['category_name'] ?? 'No Category'); ?></p>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="bg-white/10 rounded-lg px-4 py-2">
                            <p class="text-blue-200 text-xs">Gross Salary</p>
                            <p class="font-bold">₦<?php echo number_format($employee_data['base_gross_amount'] ?? 0, 2); ?></p>
                        </div>
                        <div class="bg-white/10 rounded-lg px-4 py-2">
                            <p class="text-blue-200 text-xs">Working Days</p>
                            <p class="font-bold"><?php echo $working_days; ?> days</p>
                        </div>
                        <div class="bg-white/10 rounded-lg px-4 py-2">
                            <p class="text-blue-200 text-xs">Daily Rate</p>
                            <p class="font-bold">₦<?php echo number_format($daily_rate, 2); ?></p>
                        </div>
                        <div class="bg-white/10 rounded-lg px-4 py-2">
                            <p class="text-blue-200 text-xs">Grace Period</p>
                            <p class="font-bold"><?php echo $grace_period; ?> mins</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <p class="text-xs text-slate-500 uppercase font-medium">Total Late Minutes</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1"><?php echo $summary['total_late_minutes']; ?> mins</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <p class="text-xs text-slate-500 uppercase font-medium">Total Absent Days</p>
                    <p class="text-2xl font-bold text-red-600 mt-1"><?php echo $summary['total_absent_days']; ?> days</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <p class="text-xs text-slate-500 uppercase font-medium">Grace Period Days</p>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?php echo $summary['grace_days']; ?> days</p>
                    <p class="text-xs text-slate-400">No deduction applied</p>
                </div>
                <div class="bg-white rounded-xl border border-red-200 border-2 p-4 shadow-sm">
                    <p class="text-xs text-slate-500 uppercase font-medium">Total Deduction</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">-₦<?php echo number_format($summary['total_deduction'], 2); ?></p>
                </div>
            </div>
            
            <!-- Breakdown Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900">Daily Breakdown</h3>
                </div>
                
                <?php if (empty($attendance_records)): ?>
                <div class="p-12 text-center text-slate-500">
                    <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-4 text-green-400"></i>
                    <p class="font-medium">No deductions for this period</p>
                    <p class="text-sm">Employee has perfect attendance record for <?php echo $months[$selected_month] . ' ' . $selected_year; ?></p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">Date</th>
                                <th class="px-6 py-3 text-left font-medium">Day</th>
                                <th class="px-6 py-3 text-center font-medium">Type</th>
                                <th class="px-6 py-3 text-center font-medium">Expected</th>
                                <th class="px-6 py-3 text-center font-medium">Actual</th>
                                <th class="px-6 py-3 text-center font-medium">Late By</th>
                                <th class="px-6 py-3 text-right font-medium">Deduction</th>
                                <th class="px-6 py-3 text-center font-medium no-print">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($attendance_records as $rec): 
                                $date = new DateTime($rec['date']);
                                $status = strtolower($rec['status']);
                                $deduction = (float)($rec['final_deduction_amount'] ?? $rec['auto_deduction_amount'] ?? 0);
                                $late_mins = (int)($rec['late_minutes'] ?? 0);
                                $is_reversed = !empty($rec['deduction_reversed']);
                                $is_grace = ($status === 'late' && $late_mins <= $grace_period && $deduction == 0);
                            ?>
                            <tr class="<?php echo $is_reversed ? 'bg-green-50' : ($is_grace ? 'bg-yellow-50' : ''); ?>">
                                <td class="px-6 py-4 font-medium text-slate-900"><?php echo $date->format('M d, Y'); ?></td>
                                <td class="px-6 py-4 text-slate-500"><?php echo $date->format('l'); ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($status === 'absent'): ?>
                                    <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">ABSENT</span>
                                    <?php elseif ($status === 'late'): ?>
                                    <span class="px-2 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">LATE</span>
                                    <?php else: ?>
                                    <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold"><?php echo strtoupper($status); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-500">
                                    <?php echo isset($rec['expected_check_in']) && $rec['expected_check_in'] ? date('h:i A', strtotime($rec['expected_check_in'])) : '08:00 AM'; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($status === 'absent'): ?>
                                    <span class="text-red-500 font-medium">—</span>
                                    <?php else: ?>
                                    <?php echo $rec['check_in_formatted'] ?? '-'; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($status === 'absent'): ?>
                                    <span class="text-red-600 font-medium">Full Day</span>
                                    <?php elseif ($late_mins > 0): ?>
                                    <span class="<?php echo $is_grace ? 'text-green-600' : 'text-amber-600'; ?> font-medium"><?php echo $late_mins; ?> mins</span>
                                    <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right font-bold <?php echo $is_reversed ? 'text-green-600 line-through' : 'text-red-600'; ?>">
                                    <?php if ($is_grace): ?>
                                    <span class="text-green-600">₦0.00</span>
                                    <?php elseif ($deduction > 0): ?>
                                    -₦<?php echo number_format($deduction, 2); ?>
                                    <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center no-print">
                                    <?php if ($is_reversed): ?>
                                    <span class="text-xs text-green-600 font-medium">EXCUSED</span>
                                    <?php elseif ($is_grace): ?>
                                    <span class="text-xs text-green-600 font-medium">GRACE</span>
                                    <?php else: ?>
                                    <span class="text-xs text-red-500 font-medium">DEDUCTED</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-slate-100 font-bold">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-right text-slate-700">TOTAL DEDUCTION:</td>
                                <td class="px-6 py-4 text-center text-slate-600">
                                    <?php echo $summary['total_late_minutes']; ?> mins + <?php echo $summary['total_absent_days']; ?> day(s)
                                </td>
                                <td class="px-6 py-4 text-right text-red-700 text-lg">
                                    -₦<?php echo number_format($summary['total_deduction'], 2); ?>
                                </td>
                                <td class="px-6 py-4 no-print"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer Note -->
            <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                <p class="font-medium mb-1">Note:</p>
                <ul class="list-disc list-inside space-y-1 text-amber-700">
                    <li>Grace period of <strong><?php echo $grace_period; ?> minutes</strong> applies - arrivals within this window are not deducted.</li>
                    <li>Daily rate is calculated as: Gross Salary ÷ Working Days in Month</li>
                    <li>Deductions marked as <span class="text-green-600 font-medium">EXCUSED</span> have been reversed by admin.</li>
                </ul>
            </div>
            
            <?php elseif ($selected_employee == 0): ?>
            <!-- No Employee Selected -->
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <i data-lucide="users" class="w-16 h-16 mx-auto mb-4 text-slate-300"></i>
                <h3 class="text-lg font-medium text-slate-700 mb-2">Select an Employee</h3>
                <p class="text-slate-500">Choose an employee from the dropdown above to view their attendance deduction breakdown.</p>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
