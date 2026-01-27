<div class="px-6 py-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Admin & Support</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm">System configuration, user management, audit logs, and support.</p>
    </div>
    
    <!-- Tab Navigation -->
    <div class="flex space-x-1 overflow-x-auto pb-1 border-b border-transparent">
        <a href="users.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'users') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Users
        </a>
        <a href="settings.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'settings') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Configuration
        </a>
        <a href="audit.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'audit') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Audit Trail
        </a>
        <a href="report.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'reports') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Reports
        </a>
        <a href="support.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'support') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Support
        </a>
    </div>
</div>
