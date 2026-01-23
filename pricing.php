<?php include 'includes/public_header.php'; ?>

<!-- Content Wrapper -->
<div class="bg-slate-50 dark:bg-slate-950 min-h-screen font-sans transition-colors duration-300" x-data="{ billingCycle: 'monthly' }">
    
    <!-- Hero Section -->
    <div class="pt-24 pb-12 text-center px-4">
        <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-white mb-4">Flexible Plans for Growing Teams</h1>
        <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto mb-8">Choose the perfect plan for your payroll, HR, and compliance needs. No hidden fees.</p>
        
        <!-- Billing Toggle -->
        <div class="flex items-center justify-center gap-4 mb-4">
            <span class="text-sm font-medium transition-colors" :class="billingCycle === 'monthly' ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">Monthly</span>
            
            <div class="relative inline-flex h-8 w-48 bg-slate-200 dark:bg-slate-800 rounded-full p-1 cursor-pointer transition-colors" @click="billingCycle = (billingCycle === 'monthly' ? 'yearly' : 'monthly')">
                <div class="w-full h-full flex items-center justify-between px-3 text-xs font-bold z-10 relative">
                    <span class="transition-colors" :class="billingCycle === 'monthly' ? 'text-slate-900 dark:text-slate-900' : 'text-slate-500 dark:text-slate-400'" @click.stop="billingCycle = 'monthly'">Monthly</span>
                    <span class="transition-colors" :class="billingCycle === 'quarterly' ? 'text-slate-900 dark:text-slate-900' : 'text-slate-500 dark:text-slate-400'" @click.stop="billingCycle = 'quarterly'">Quarterly</span>
                    <span class="transition-colors" :class="billingCycle === 'yearly' ? 'text-slate-900 dark:text-slate-900' : 'text-slate-500 dark:text-slate-400'" @click.stop="billingCycle = 'yearly'">Yearly</span>
                </div>
                <!-- Pill slider visualization placeholder -->
                <div class="absolute top-1 left-1 bottom-1 w-1/3 bg-white dark:bg-slate-200 rounded-full shadow transition-all duration-300"
                     :style="billingCycle === 'monthly' ? 'transform: translateX(0)' : (billingCycle === 'quarterly' ? 'transform: translateX(100%)' : 'transform: translateX(200%)')"></div>
            </div>
            
            <span class="text-sm font-medium transition-colors" :class="billingCycle === 'yearly' ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">Yearly <span class="text-xs text-green-600 dark:text-green-400 font-bold ml-1">-20%</span></span>
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500">Quarterly and Yearly prices calculated at checkout.</p>
    </div>

    <!-- Pricing Grid -->
    <div class="max-w-[1400px] mx-auto px-4 pb-24 overflow-x-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 min-w-[300px]">
            
            <!-- TIER 1: STARTER -->
            <div class="group bg-white/70 dark:bg-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-slate-200/60 dark:border-slate-800/60 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-white via-transparent to-transparent dark:from-slate-800 dark:via-transparent opacity-50"></div>
                <div class="relative z-10 mb-4">
                    <h3 class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">Starter</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-slate-900 dark:text-white">₦0</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">/mo</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Free Forever</p>
                </div>
                
                <div class="space-y-3 mb-6 flex-1 relative z-10">
                    <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 font-medium">
                        <i data-lucide="users" class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-brand-500 transition-colors"></i> Up to 5 Employees
                    </div>
                    <div class="flex items-center gap-2 text-sm text-red-500">
                        <i data-lucide="x-circle" class="w-4 h-4"></i> No Templates
                    </div>
                    
                    <div class="h-px bg-slate-100 dark:bg-slate-800 my-3"></div>
                    
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Employee Biodata</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Manual Payroll</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Payslip PDF</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Manual Attendance</li>
                    </ul>
                </div>
                
                <button class="relative z-10 w-full py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-900 hover:text-white hover:border-slate-900 dark:hover:bg-brand-600 dark:hover:text-white dark:hover:border-brand-600 transition-all duration-300">
                    Get Started Free
                </button>
            </div>

            <!-- TIER 2: BASIC PAYROLL -->
            <div class="group bg-white/80 dark:bg-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-blue-200/60 dark:border-blue-900/30 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 dark:bg-blue-900/20 rounded-bl-full -mr-16 -mt-16 transition-transform group-hover:scale-110 duration-500"></div>
                <div class="relative z-10 mb-4">
                    <h3 class="text-sm font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Basic Payroll</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-slate-900 dark:text-white">₦15k</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">/mo</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">For Small Business</p>
                </div>
                
                <div class="space-y-3 mb-6 flex-1 relative z-10">
                    <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 font-medium">
                        <i data-lucide="users" class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-blue-500 transition-colors"></i> Up to 25 Employees
                    </div>
                    <div class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-500">
                        <i data-lucide="alert-circle" class="w-4 h-4"></i> Limited Templates
                    </div>
                    
                    <div class="h-px bg-slate-100 dark:bg-slate-800 my-3"></div>
                    
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> <strong>Everything in Starter</strong></li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Automated PAYE/Pension</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Email Payslips</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Monthly Reports</li>
                    </ul>
                </div>
                
                <button class="relative z-10 w-full py-2.5 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 font-bold hover:bg-blue-600 hover:text-white transition-all duration-300">
                    Upgrade to Basic
                </button>
            </div>

            <!-- TIER 3: PROFESSIONAL (RECOMMENDED) -->
            <div class="group bg-white dark:bg-slate-800 rounded-2xl p-6 border-2 border-brand-600 dark:border-brand-500 shadow-xl scale-105 hover:scale-110 hover:shadow-2xl transition-all duration-300 z-10 flex flex-col relative overflow-hidden ring-4 ring-brand-500/10 dark:ring-brand-400/20">
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-brand-600 dark:bg-brand-500 text-white px-4 py-1 rounded-full text-xs font-bold uppercase tracking-wide shadow-lg">
                    Recommended
                </div>
                <div class="absolute inset-0 bg-gradient-to-br from-brand-50/50 via-transparent to-transparent dark:from-brand-900/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                
                <div class="relative z-10 mb-4 mt-2">
                    <h3 class="text-sm font-bold text-brand-600 dark:text-brand-400 uppercase tracking-widest">Professional</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-4xl font-bold text-slate-900 dark:text-white group-hover:text-brand-700 dark:group-hover:text-brand-300 transition-colors">₦35k</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">/mo</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Growing Teams</p>
                </div>
                
                <div class="space-y-3 mb-6 flex-1 relative z-10">
                    <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 font-medium">
                        <i data-lucide="users" class="w-4 h-4 text-brand-500"></i> Up to 100 Employees
                    </div>
                    <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-400 font-bold">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Full Templates Access
                    </div>
                    
                    <div class="h-px bg-slate-100 dark:bg-slate-700 my-3"></div>
                    
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> <strong>Everything in Basic</strong></li>
                        <li class="flex gap-2"><i data-lucide="wallet" class="w-4 h-4 text-brand-500 shrink-0"></i> Wallet Disbursement</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Payroll Behaviour Rules</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Biometric Attendance</li>
                    </ul>
                </div>
                
                <button class="relative z-10 w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600 text-white font-bold transition-all duration-300 transform group-hover:-translate-y-0.5 shadow-lg shadow-brand-500/20">
                    Go Professional
                </button>
            </div>

            <!-- TIER 4: ENTERPRISE PLUS -->
            <div class="group bg-white/80 dark:bg-slate-900/50 backdrop-blur-xl rounded-2xl p-6 border border-amber-200/60 dark:border-amber-900/30 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-amber-50 dark:bg-amber-900/20 rounded-bl-full -mr-16 -mt-16 transition-transform group-hover:scale-110 duration-500"></div>
                <div class="relative z-10 mb-4">
                    <h3 class="text-sm font-bold text-amber-600 dark:text-amber-500 uppercase tracking-widest">Enterprise Plus</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-slate-900 dark:text-white">₦75k</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">/mo</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Includes Openclax Learning</p>
                </div>
                
                <div class="space-y-3 mb-6 flex-1 relative z-10">
                    <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 font-medium">
                        <i data-lucide="users" class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-amber-500 transition-colors"></i> Unlimited / Custom
                    </div>
                    <div class="flex items-center gap-2 text-sm text-green-600 dark:text-green-500 font-bold">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Full Templates Access
                    </div>
                    
                    <div class="h-px bg-slate-100 dark:bg-slate-800 my-3"></div>
                    
                    <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> <strong>Everything in Pro</strong></li>
                        <li class="flex gap-2"><i data-lucide="graduation-cap" class="w-4 h-4 text-amber-500 shrink-0"></i> Openclax Learning Platform</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Unlimited Video Uploads</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-green-500 shrink-0"></i> Dedicated Onboarding</li>
                    </ul>
                </div>
                
                <button class="relative z-10 w-full py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-900 hover:text-white dark:hover:bg-brand-600 transition-all duration-300">
                    Contact Sales
                </button>
            </div>

            <!-- TIER 5: ULTIMATE -->
            <div class="group bg-slate-900 rounded-2xl p-6 border border-slate-800 shadow-xl hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 flex flex-col relative text-white overflow-hidden">
                <div class="absolute top-0 right-0 w-48 h-48 bg-brand-500/20 rounded-full blur-3xl -mr-10 -mt-10 animate-pulse"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-purple-500/20 rounded-full blur-2xl -ml-10 -mb-10"></div>
                
                <div class="mb-4 relative z-10">
                    <h3 class="text-sm font-bold text-brand-400 uppercase tracking-widest group-hover:text-brand-300 transition-colors">Ultimate</h3>
                    <div class="mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-white">₦150k</span>
                        <span class="text-sm text-slate-400">/mo</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">MiAuditOps Suite</p>
                </div>
                
                <div class="space-y-3 mb-6 flex-1 relative z-10">
                    <div class="flex items-center gap-2 text-sm text-slate-300 font-medium">
                        <i data-lucide="infinity" class="w-4 h-4 text-slate-500 group-hover:text-white transition-colors"></i> Unlimited Employees
                    </div>
                    <div class="flex items-center gap-2 text-sm text-brand-400 font-bold">
                        <i data-lucide="shield-check" class="w-4 h-4"></i> Audit & Control Suite
                    </div>
                    
                    <div class="h-px bg-slate-800 my-3"></div>
                    
                    <ul class="space-y-2 text-sm text-slate-400 group-hover:text-slate-300 transition-colors">
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-500 shrink-0"></i> <strong>Everything in Ent+</strong></li>
                        <li class="flex gap-2"><i data-lucide="shield" class="w-4 h-4 text-brand-500 shrink-0"></i> Daily Audit Checks</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-500 shrink-0"></i> Inventory Reconciliation</li>
                        <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-brand-500 shrink-0"></i> Exception Reporting</li>
                    </ul>
                    <p class="text-[10px] text-slate-500 italic mt-2">* Not a statutory audit tool</p>
                </div>
                
                <button class="relative z-10 w-full py-2.5 rounded-xl bg-slate-800 text-white font-bold hover:bg-brand-600 transition-all duration-300 border border-slate-700 hover:border-brand-500">
                    Contact Support
                </button>
            </div>

        </div>
        
        <!-- Footnotes -->
        <div class="mt-12 text-center text-xs text-slate-500 space-y-1">
            <p>• Prices shown are monthly samples only.</p>
            <p>• Quarterly and yearly plans will be calculated automatically using backend discount logic.</p>
            <p>• Employee limits apply per plan. Wallet & disbursement depend on company settings.</p>
        </div>

    </div>
</div>

<?php include 'includes/public_footer.php'; ?>
