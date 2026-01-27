<div class="px-6 py-4 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Finance Tools</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm">Manage disbursements, tax calculations, and subscription billing.</p>
    </div>
    
    <!-- Tab Navigation -->
    <div class="flex space-x-1 overflow-x-auto pb-1 border-b border-transparent">
        <a href="wallet.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'wallet') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Disbursement
        </a>
        <a href="tax_calculator.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'tax_calculator') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Tax Calculator
        </a>
        <a href="billing.php" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2 <?php echo ($current_page == 'billing') ? 'text-brand-600 border-brand-600 bg-brand-50 dark:bg-brand-900/10' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border-transparent hover:border-slate-300'; ?>">
            Subscription
        </a>
    </div>
</div>
