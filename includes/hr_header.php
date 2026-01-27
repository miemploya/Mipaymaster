<div class="px-6 py-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">HR Management</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm">Manage your recruitment pipeline, employee onboarding, and internal relations.</p>
    </div>
    
    <!-- Tab Navigation -->
    <div class="flex space-x-1 overflow-x-auto pb-1 border-b border-transparent">
        <a href="hr_recruitment.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_tab == 'recruitment') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Recruitment
        </a>
        <a href="hr_onboarding.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_tab == 'onboarding') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Onboarding
        </a>
        <a href="hr_relations.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_tab == 'relations') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Employee Relations
        </a>
        <a href="hr_performance.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_tab == 'performance') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Performance Evaluation
        </a>
        <a href="leaves.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_tab == 'leaves') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Leave Management
        </a>
        <a href="hr_templates.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_tab == 'templates') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Templates
        </a>
    </div>
</div>
