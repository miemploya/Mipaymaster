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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'); body { font-family: 'Inter', sans-serif; }</style>
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
                
                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Time Clock</h3>
                <p class="text-sm text-slate-500 mb-6" x-text="todayDate"></p>

                <div class="text-center mb-6">
                    <div class="text-4xl font-mono font-bold text-slate-900 dark:text-white tracking-wider" x-text="currentTime"></div>
                </div>

                <!-- ACTIONS -->
                <div class="space-y-3">
                    <?php if (!$attendance): ?>
                        <button @click="clockIn()" :disabled="loading" class="w-full py-4 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold shadow-lg shadow-brand-500/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="log-in" class="w-5 h-5"></i> <span x-text="loading ? 'Processing...' : 'Clock In'"></span>
                        </button>
                    <?php elseif (!$attendance['time_out']): ?>
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg text-center text-sm font-medium mb-2 border border-green-100 dark:border-green-900/30">
                            Clocked In at <?php echo date('H:i', strtotime($attendance['time_in'])); ?>
                        </div>
                        <button @click="clockOut()" :disabled="loading" class="w-full py-4 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white rounded-xl font-bold transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="log-out" class="w-5 h-5"></i> <span x-text="loading ? 'Processing...' : 'Clock Out'"></span>
                        </button>
                    <?php else: ?>
                        <div class="p-4 bg-slate-100 dark:bg-slate-900 rounded-xl text-center">
                            <p class="text-sm text-slate-500">Attendance Completed</p>
                            <div class="flex justify-center gap-4 mt-2 font-mono text-sm font-bold text-slate-700 dark:text-slate-300">
                                <span>IN: <?php echo date('H:i', strtotime($attendance['time_in'])); ?></span>
                                <span class="text-slate-300">|</span>
                                <span>OUT: <?php echo date('H:i', strtotime($attendance['time_out'])); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. QUICK STATS -->
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                 <!-- Leave -->
                 <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl p-6 border border-indigo-100 dark:border-indigo-900/30">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg text-indigo-600"><i data-lucide="calendar" class="w-5 h-5"></i></div>
                        <h3 class="font-bold text-indigo-900 dark:text-indigo-100">Leave Balance</h3>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-bold text-indigo-700 dark:text-indigo-300">12</span>
                        <span class="text-sm text-indigo-600 dark:text-indigo-400 mb-1">Days Available</span>
                    </div>
                    <button @click="leaveModalOpen = true" class="mt-4 text-xs font-bold text-indigo-600 hover:underline">Request Leave &rarr;</button>
                 </div>

                 <!-- Loan -->
                 <div class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl p-6 border border-amber-100 dark:border-amber-900/30">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-amber-100 dark:bg-amber-900/50 rounded-lg text-amber-600"><i data-lucide="credit-card" class="w-5 h-5"></i></div>
                        <h3 class="font-bold text-amber-900 dark:text-amber-100">Active Loans</h3>
                    </div>
                    <?php 
                        // Quick fetch active loans
                        $stmt_loan = $pdo->prepare("SELECT SUM(balance) FROM loans WHERE employee_id = ? AND status = 'approved'");
                        $stmt_loan->execute([$employee_id]);
                        $loan_bal = $stmt_loan->fetchColumn() ?: 0;
                    ?>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-bold text-amber-700 dark:text-amber-300">₦ <?php echo number_format($loan_bal); ?></span>
                    </div>
                    <button @click="loanModalOpen = true" class="mt-4 text-xs font-bold text-amber-600 hover:underline">Apply for Loan &rarr;</button>
                 </div>
            </div>
        </div>

        <!-- 3. PAYSLIP HISTORY -->
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Recent Payslips</h3>
        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                    <tr>
                        <th class="px-6 py-4 font-medium">Period</th>
                        <th class="px-6 py-4 font-medium text-right">Net Pay</th>
                        <th class="px-6 py-4 font-medium text-center">Status</th>
                        <th class="px-6 py-4 font-medium text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($payslips as $slip): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
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
                   <select x-model="leaveForm.type" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                       <option value="">Select Type</option>
                       <option value="Annual">Annual Leave</option>
                       <option value="Sick">Sick Leave</option>
                       <option value="Casual">Casual Leave</option>
                       <option value="Maternity">Maternity/Paternity</option>
                       <option value="Unpaid">Unpaid Leave</option>
                   </select>
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
                            <option value="personal">Personal Loan</option>
                            <option value="emergency">Emergency Loan</option>
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
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="loanModalOpen = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-amber-600 text-white rounded-lg text-sm font-bold hover:bg-amber-700 shadow-md">Apply Now</button>
                </div>
            </form>
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

        function staffPortal() {
            return {
                leaveModalOpen: false,
                loanModalOpen: false,
                leaveForm: {
                    type: '', start_date: '', end_date: '', reason: ''
                },
                loanForm: {
                    type: 'salary_advance', custom_type: '', amount: '', repayment: '', start_month: new Date().getMonth() + 1, start_year: new Date().getFullYear()
                },

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
                async clockIn() { await this.submitAttendance('in'); },
                async clockOut() { await this.submitAttendance('out'); },
                async submitAttendance(type) {
                    if (!confirm('Confirm ' + (type === 'in' ? 'Clock In' : 'Clock Out') + '?')) return;
                    this.loading = true;
                    try {
                        const fd = new FormData(); fd.append('type', type);
                        const res = await fetch('../ajax/attendance_clock.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) window.location.reload();
                    } catch (e) { alert('Connection error.'); } finally { this.loading = false; }
                },

                // Leave Logic
                async submitLeave() {
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
                        if (data.status) { this.leaveModalOpen = false; this.leaveForm = { type: '', start_date: '', end_date: '', reason: '' }; }
                    } catch (e) { alert('Error: ' + e); }
                },

                // Loan Logic
                async submitLoan() {
                    const fd = new FormData();
                    fd.append('action', 'create_loan');
                    // No employee_id needed, backend safely infers it for 'employee' role
                    fd.append('employee_id', 'SELF'); 
                    fd.append('loan_type', this.loanForm.type);
                    if (this.loanForm.type === 'other') fd.append('custom_type', this.loanForm.custom_type);
                    fd.append('principal_amount', this.loanForm.amount);
                    fd.append('repayment_amount', this.loanForm.repayment);
                    fd.append('start_month', this.loanForm.start_month);
                    fd.append('start_year', this.loanForm.start_year);

                    try {
                        const res = await fetch('../ajax/loan_operations.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) { this.loanModalOpen = false; window.location.reload(); }
                    } catch (e) { alert('Error: ' + e); }
                }
            }
        }
    </script>
</body>
</html>
