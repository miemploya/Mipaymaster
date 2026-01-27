<div class="px-6 py-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Payroll Suite</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm">Manage payroll runs, salary increments, loans, and attendance records.</p>
    </div>
    
    <!-- Tab Navigation -->
    <div class="flex space-x-1 overflow-x-auto pb-1 border-b border-transparent">
        <a href="payroll.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'payroll') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Run Payroll
        </a>
        <a href="increments.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'increments') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Increments
        </a>
        <a href="loans.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'loans') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Loans & Advances
        </a>
        <a href="attendance.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'attendance') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Attendance & Lateness
        </a>
    </div>
</div>
