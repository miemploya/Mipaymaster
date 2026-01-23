<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mipaymaster - Payroll & HR Compliance</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, .font-serif { font-family: 'Inter', sans-serif; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .dark ::-webkit-scrollbar-track { background: #0f172a; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
    </style>
</head>
<body class="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300 selection:bg-brand-600 selection:text-white">

    <!-- 1. TOP NAVIGATION -->
    <nav class="sticky top-0 z-50 w-full border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/90 backdrop-blur-md transition-colors duration-300">
        <div class="container mx-auto flex h-20 items-center justify-between px-6 lg:px-12">
            <!-- Logo -->
            <div class="flex items-center gap-2">
                <!-- Master Logo (Switched) -->
                <img src="assets/images/logo-light.png" alt="Mipaymaster" class="h-12 w-auto object-contain block dark:hidden">
                <img src="assets/images/logo-dark.png" alt="Mipaymaster" class="h-12 w-auto object-contain hidden dark:block">
            </div>

            <!-- Desktop Nav -->
            <div class="hidden items-center gap-8 md:flex">
                <a href="#features" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Features</a>
                <a href="pricing.php" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Pricing</a>
                <a href="contact.php" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Contact</a>
            </div>

            <!-- Desktop Actions -->
            <div class="hidden items-center gap-4 md:flex">
                <!-- Theme Toggle Button -->
                <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors mr-2">
                    <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                    <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                </button>

                <button onclick="window.location.href='auth/login.php'" class="text-sm font-semibold text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    Sign In
                </button>
                <button onclick="window.location.href='auth/signup.php'" class="rounded-full bg-slate-900 dark:bg-white px-6 py-2.5 text-sm font-semibold text-white dark:text-slate-900 transition-all hover:bg-slate-800 dark:hover:bg-slate-200 hover:shadow-lg active:scale-95">
                    Get Started
                </button>
            </div>

            <!-- Mobile Menu Toggle -->
            <div class="flex items-center gap-4 md:hidden">
                <button id="theme-toggle-mobile" class="p-2 text-slate-900 dark:text-white">
                    <i data-lucide="moon" class="w-6 h-6 block dark:hidden"></i>
                    <i data-lucide="sun" class="w-6 h-6 hidden dark:block"></i>
                </button>
                <button id="mobile-menu-btn" class="text-slate-900 dark:text-white">
                    <i data-lucide="menu" class="w-7 h-7"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-6 md:hidden shadow-xl">
            <div class="flex flex-col gap-4">
                <a href="#features" class="text-base font-medium text-slate-600 dark:text-slate-300">Features</a>
                <a href="pricing.php" class="text-base font-medium text-slate-600 dark:text-slate-300">Pricing</a>
                <a href="auth/login.php" class="text-base font-medium text-slate-600 dark:text-slate-300">Sign In</a>
                <button onclick="window.location.href='auth/signup.php'" class="w-full rounded-lg bg-brand-600 py-3 text-center font-semibold text-white">
                    Get Started
                </button>
            </div>
        </div>
    </nav>

    <!-- 2. HERO SECTION -->
    <section class="relative overflow-hidden pt-16 pb-24 lg:pt-24">
        <!-- Background Blobs -->
        <div class="absolute -left-20 top-0 h-[30rem] w-[30rem] rounded-full bg-blue-100/50 dark:bg-blue-900/20 blur-3xl filter mix-blend-multiply dark:mix-blend-normal opacity-70 dark:opacity-30"></div>
        <div class="absolute right-0 top-20 h-[30rem] w-[30rem] rounded-full bg-purple-100/50 dark:bg-purple-900/20 blur-3xl filter mix-blend-multiply dark:mix-blend-normal opacity-70 dark:opacity-30"></div>

        <div class="container mx-auto px-6 lg:px-12 relative">
            <div class="flex flex-col items-center text-center lg:flex-row lg:text-left lg:justify-between">
                
                <!-- Hero Content -->
                <div class="max-w-2xl lg:w-1/2">
                    <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 px-3 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300 mb-6 shadow-sm backdrop-blur-sm">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-brand-500"></span>
                        </span>
                        Now available in Nigeria
                    </div>
                    <!-- Classic Modern Typography: Serif Heading -->
                    <h1 class="mb-6 text-5xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-6xl lg:text-7xl lg:leading-[1.1]">
                        Payroll & HR <br />
                        <span class="italic text-brand-600 dark:text-brand-400">
                            Compliance Simplified
                        </span>
                    </h1>
                    <p class="mb-8 text-lg text-slate-600 dark:text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0 font-light">
                        Automate your PAYE taxes, manage employee attendance, and ensure 100% regulatory compliance with Mipaymaster.
                    </p>
                    
                    <div class="flex flex-col gap-4 sm:flex-row sm:justify-center lg:justify-start">
                        <!-- Updated Buttons -->
                        <button onclick="window.location.href='auth/signup.php'" class="inline-flex items-center justify-center gap-2 rounded-full bg-slate-900 dark:bg-white px-8 py-4 text-base font-bold text-white dark:text-slate-900 transition-all hover:bg-slate-800 dark:hover:bg-slate-200 hover:shadow-xl active:scale-95">
                            Get Started
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                        <button onclick="window.location.href='auth/login.php'" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 bg-transparent px-8 py-4 text-base font-semibold text-slate-700 dark:text-slate-200 transition-all hover:border-brand-600 dark:hover:border-brand-400 hover:text-brand-600 dark:hover:text-brand-400 active:scale-95">
                            <i data-lucide="log-in" class="w-4 h-4 opacity-80"></i>
                            Sign In
                        </button>
                    </div>

                    <div class="mt-8 flex items-center justify-center gap-4 lg:justify-start">
                        <div class="flex -space-x-3">
                            <div class="h-10 w-10 rounded-full border-2 border-white dark:border-slate-900 bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-500 dark:text-slate-300 shadow-sm">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <div class="h-10 w-10 rounded-full border-2 border-white dark:border-slate-900 bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-500 dark:text-slate-300 shadow-sm">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <div class="h-10 w-10 rounded-full border-2 border-white dark:border-slate-900 bg-slate-300 dark:bg-slate-600 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-200 shadow-sm">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                             <div class="h-10 w-10 rounded-full border-2 border-white dark:border-slate-900 bg-slate-900 dark:bg-brand-600 flex items-center justify-center text-xs font-bold text-white shadow-sm">
                                +500
                            </div>
                        </div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Trusted businesses</p>
                    </div>
                </div>

                <!-- Hero Visual -->
                <div class="mt-16 lg:mt-0 lg:w-1/2 lg:pl-12">
                    <div class="relative rounded-2xl bg-white dark:bg-slate-900 p-2 shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 transition-colors duration-300">
                        <!-- Mac-style Header -->
                        <div class="mb-2 flex items-center gap-2 px-4 py-2 border-b border-slate-50 dark:border-slate-800">
                            <div class="h-3 w-3 rounded-full bg-red-400"></div>
                            <div class="h-3 w-3 rounded-full bg-amber-400"></div>
                            <div class="h-3 w-3 rounded-full bg-green-400"></div>
                        </div>
                        <!-- UI Body -->
                        <div class="aspect-[4/3] w-full overflow-hidden rounded-lg bg-slate-50 dark:bg-slate-950 relative p-4">
                            <!-- Abstract Dashboard -->
                            <div class="flex flex-col gap-4 h-full">
                                <!-- Top Bar -->
                                <div class="flex justify-between items-center mb-2">
                                    <div class="h-8 w-32 bg-white dark:bg-slate-800 rounded-md shadow-sm"></div>
                                    <div class="h-8 w-8 bg-brand-600 rounded-full shadow-md shadow-brand-600/20 flex items-center justify-center text-white text-xs">M</div>
                                </div>
                                
                                <!-- Updated Graphic Illustration (Widgets) -->
                                <div class="grid grid-cols-2 gap-3 mb-2">
                                    <!-- Payroll Widget (Full Width) -->
                                    <div class="col-span-2 bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between">
                                        <div>
                                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider mb-1">Total Payroll</div>
                                            <div class="text-lg font-bold text-slate-900 dark:text-white">₦ 4.5M</div>
                                        </div>
                                        <div class="h-10 w-24">
                                             <!-- Simple Sparkline SVG -->
                                             <svg viewBox="0 0 100 40" class="w-full h-full overflow-visible">
                                                 <path d="M0 35 L 20 25 L 40 30 L 60 15 L 80 20 L 100 5" fill="none" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                                 <circle cx="100" cy="5" r="3" fill="#4f46e5" />
                                             </svg>
                                        </div>
                                    </div>
                                    
                                    <!-- Taxes Widget -->
                                    <div class="bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">TAXES</div>
                                        </div>
                                        <div class="flex items-center gap-3 mt-2">
                                             <!-- CSS Conic Gradient Pie Chart -->
                                             <div class="w-10 h-10 rounded-full" style="background: conic-gradient(#10b981 75%, #e2e8f0 0);"></div>
                                             <div>
                                                 <div class="text-xs font-bold text-slate-900 dark:text-white">75%</div>
                                                 <div class="text-[9px] text-slate-400">Remitted</div>
                                             </div>
                                        </div>
                                    </div>
                
                                    <!-- Pending Widget -->
                                    <div class="bg-white dark:bg-slate-800 rounded-xl p-3 shadow-sm border border-slate-100 dark:border-slate-700 flex flex-col justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-bold">PENDING</div>
                                        </div>
                                        <div class="flex -space-x-2 mt-2 pl-1">
                                            <div class="w-7 h-7 rounded-full bg-slate-200 dark:bg-slate-600 border-2 border-white dark:border-slate-800"></div>
                                            <div class="w-7 h-7 rounded-full bg-slate-300 dark:bg-slate-500 border-2 border-white dark:border-slate-800 flex items-center justify-center text-[9px] font-bold text-slate-600 dark:text-slate-200">3</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Table Placeholder -->
                                <div class="flex-1 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-3 flex flex-col gap-2">
                                    <div class="h-2 w-full bg-slate-100 dark:bg-slate-700 rounded-full"></div>
                                    <div class="h-2 w-3/4 bg-slate-100 dark:bg-slate-700 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Floating Badge -->
                    <div class="absolute -bottom-6 -left-6 bg-white dark:bg-slate-800 p-4 rounded-xl shadow-xl border border-slate-100 dark:border-slate-700 hidden lg:block animate-bounce" style="animation-duration: 3s;">
                        <div class="flex items-center gap-3">
                            <div class="bg-green-100 dark:bg-green-900/30 p-2 rounded-full text-green-600 dark:text-green-400">
                                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Status</p>
                                <p class="text-sm font-bold text-slate-800 dark:text-white">Tax Remitted</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 3. FEATURES SECTION -->
    <section id="features" class="bg-slate-50 dark:bg-slate-900 py-24 transition-colors duration-300">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="mb-16 text-center">
                <span class="text-brand-600 dark:text-brand-400 font-bold tracking-widest uppercase text-xs">Features</span>
                <h2 class="mt-3 mb-4 text-3xl font-bold text-slate-900 dark:text-white md:text-4xl">Everything required to manage your team</h2>
                <p class="mx-auto max-w-2xl text-slate-600 dark:text-slate-400 font-light">Streamline your operations with our comprehensive suite of tools designed for the modern African workforce.</p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <!-- Feature 1 -->
                <div class="group rounded-2xl bg-white dark:bg-slate-800 p-8 transition-all hover:-translate-y-1 hover:shadow-xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <i data-lucide="credit-card" class="w-6 h-6"></i>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900 dark:text-white">Smart Payroll</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Automate salary calculations, deductions, and bonuses with zero errors. Supports multi-currency payments.</p>
                </div>

                <!-- Feature 2 -->
                <div class="group rounded-2xl bg-white dark:bg-slate-800 p-8 transition-all hover:-translate-y-1 hover:shadow-xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 group-hover:bg-green-600 group-hover:text-white transition-colors">
                        <i data-lucide="file-check" class="w-6 h-6"></i>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900 dark:text-white">PAYE Compliance</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Automatically calculate and remit PAYE taxes. Stay compliant with state and federal laws.</p>
                </div>

                <!-- Feature 3 -->
                <div class="group rounded-2xl bg-white dark:bg-slate-800 p-8 transition-all hover:-translate-y-1 hover:shadow-xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 group-hover:bg-orange-600 group-hover:text-white transition-colors">
                        <i data-lucide="clock" class="w-6 h-6"></i>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900 dark:text-white">Attendance</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Digital clock-in/out, leave management, and overtime tracking linked directly to payroll processing.</p>
                </div>

                <!-- Feature 4 -->
                <div class="group rounded-2xl bg-white dark:bg-slate-800 p-8 transition-all hover:-translate-y-1 hover:shadow-xl shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <h3 class="mb-3 text-xl font-bold text-slate-900 dark:text-white">HR Management</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Centralized employee database, digital document storage, and smooth onboarding workflows.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. HOW IT WORKS -->
    <section class="py-24 bg-white dark:bg-slate-950 relative transition-colors duration-300">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="flex flex-col lg:flex-row gap-16 items-center">
                
                <div class="lg:w-1/2">
                    <span class="text-brand-600 dark:text-brand-400 font-bold tracking-widest uppercase text-xs">Workflow</span>
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-8 mt-3">Get running in 3 simple steps</h2>
                    
                    <div class="space-y-10">
                        <!-- Step 1 -->
                        <div class="flex gap-6 relative">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center font-bold text-slate-900 dark:text-white text-lg shadow-sm">1</div>
                            </div>
                            <div class="pt-1">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Create your account</h4>
                                <p class="text-slate-600 dark:text-slate-400">Sign up in minutes. Configure your company settings and tax information.</p>
                            </div>
                            <!-- Connector Line -->
                            <div class="absolute left-6 top-14 bottom-[-30px] w-px bg-slate-200 dark:bg-slate-800 lg:block hidden"></div>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex gap-6 relative">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center font-bold text-slate-900 dark:text-white text-lg shadow-sm">2</div>
                            </div>
                            <div class="pt-1">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Import Employee Data</h4>
                                <p class="text-slate-600 dark:text-slate-400">Bulk upload employee details via Excel or add them manually with our intuitive form.</p>
                            </div>
                            <!-- Connector Line -->
                            <div class="absolute left-6 top-14 bottom-[-30px] w-px bg-slate-200 dark:bg-slate-800 lg:block hidden"></div>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex gap-6">
                            <div class="flex-shrink-0">
                                <div class="h-12 w-12 rounded-full bg-slate-900 dark:bg-white flex items-center justify-center font-bold text-white dark:text-slate-900 text-lg shadow-lg">3</div>
                            </div>
                            <div class="pt-1">
                                <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Run Payroll</h4>
                                <p class="text-slate-600 dark:text-slate-400">One click to process salaries, generate payslips, and remit taxes automatically.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:w-1/2 w-full">
                    <div class="bg-slate-900 dark:bg-slate-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden text-white border border-slate-800 dark:border-slate-700">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-bl-full"></div>
                        <div class="space-y-6 relative z-10">
                            <div class="flex justify-between items-center text-sm text-slate-400 border-b border-white/10 pb-4">
                                <span>Month: <span class="text-white font-medium">January 2026</span></span>
                                <span class="flex items-center gap-2 px-2 py-1 rounded bg-yellow-500/20 text-yellow-400 text-xs font-bold">
                                    <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                    DRAFT
                                </span>
                            </div>
                            <div class="bg-slate-800/50 dark:bg-slate-900/50 p-6 rounded-xl border border-white/5 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Total Gross Pay</span>
                                    <span class="font-mono text-white">₦ 4,500,000.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Total Deductions</span>
                                    <span class="font-mono text-red-400">- ₦ 450,000.00</span>
                                </div>
                                <div class="h-px bg-white/10 my-2"></div>
                                <div class="flex justify-between text-xl font-bold">
                                    <span class="text-white">Net Payable</span>
                                    <span class="font-mono text-emerald-400">₦ 4,050,000.00</span>
                                </div>
                            </div>
                            <button class="w-full py-4 bg-white text-slate-900 font-bold rounded-xl shadow-lg transition-colors flex items-center justify-center gap-2 hover:bg-slate-200">
                                <i data-lucide="zap" class="w-5 h-5 fill-current"></i>
                                Process Payroll
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- NEW SECTION: Demo Video (RESIZED & CLASSIC) -->
    <section id="demo" class="py-24 bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
        <div class="container mx-auto px-6 lg:px-12 text-center">
            <div class="mb-12">
                <span class="text-brand-600 dark:text-brand-400 font-bold tracking-widest uppercase text-xs">Watch the Demo</span>
                <h2 class="mt-3 mb-4 text-3xl font-bold text-slate-900 dark:text-white md:text-4xl">See Mipaymaster in Action</h2>
                <p class="mx-auto max-w-2xl text-slate-600 dark:text-slate-400 font-light">Discover how easy it is to manage payroll, taxes, and HR in just a few minutes.</p>
            </div>
            
            <!-- Resized Container: Reduced to max-w-2xl for ~20% smaller size -->
            <div class="relative mx-auto max-w-2xl rounded-xl bg-white dark:bg-slate-800 shadow-xl overflow-hidden aspect-video border-4 border-white dark:border-slate-800 ring-1 ring-slate-200 dark:ring-slate-700">
                 <!-- Placeholder for Video -->
                 <div class="absolute inset-0 flex items-center justify-center bg-slate-900 group cursor-pointer">
                    <div class="absolute inset-0 bg-gradient-to-tr from-brand-900/50 to-purple-900/50 opacity-60"></div>
                 
                    
                    <!-- Classic Play Button -->
                    <div class="relative z-10 h-16 w-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/40 transition-transform transform group-hover:scale-105 shadow-2xl">
                        <i data-lucide="play" class="w-6 h-6 text-white fill-current ml-1"></i>
                    </div>
                    <p class="absolute bottom-8 text-white/90 font-medium tracking-wide text-xs uppercase">Click to watch walkthrough</p>
                 </div>
            </div>
        </div>
    </section>

    <!-- 5. COMPLIANCE & TRUST -->
    <section id="compliance" class="py-20 bg-white dark:bg-slate-950 border-y border-slate-200 dark:border-slate-800 transition-colors duration-300">
        <div class="container mx-auto px-6 text-center">
            <div class="inline-flex items-center gap-2 rounded-full border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 px-4 py-1.5 text-sm font-semibold text-green-700 dark:text-green-400 mb-6">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                100% Compliant & Secure
            </div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-12">Trust built into every transaction</h2>
            
            <!-- Logos Grid -->
            <div class="flex flex-wrap justify-center items-center gap-12 md:gap-20 opacity-60 dark:opacity-50">
                <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-bold text-2xl grayscale hover:grayscale-0 hover:text-slate-900 dark:hover:text-white transition-all cursor-default">
                    <div class="w-8 h-8 bg-slate-400 dark:bg-slate-600 rounded-sm"></div> NRS
                </div>
                <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-bold text-2xl grayscale hover:grayscale-0 hover:text-slate-900 dark:hover:text-white transition-all cursor-default">
                    <div class="w-8 h-8 bg-slate-400 dark:bg-slate-600 rounded-full"></div> PENCOM
                </div>
                <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-bold text-2xl grayscale hover:grayscale-0 hover:text-slate-900 dark:hover:text-white transition-all cursor-default">
                    <div class="w-8 h-8 bg-slate-400 dark:bg-slate-600 rounded-sm"></div> NSITF
                </div>
                <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 font-bold text-2xl grayscale hover:grayscale-0 hover:text-slate-900 dark:hover:text-white transition-all cursor-default">
                    <div class="w-8 h-8 bg-slate-400 dark:bg-slate-600 rounded-sm"></div> ITF
                </div>
            </div>
        </div>
    </section>

    <!-- 6. SUPPORT HIGHLIGHT -->
    <section class="py-24 bg-white dark:bg-slate-950 transition-colors duration-300">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="relative overflow-hidden rounded-3xl bg-brand-900 px-8 py-16 text-center md:px-16 md:text-left lg:flex lg:items-center lg:justify-between shadow-2xl">
                <!-- Decor -->
                <div class="absolute top-0 right-0 h-96 w-96 translate-x-1/3 -translate-y-1/3 rounded-full bg-white/10 blur-3xl"></div>
                
                <div class="relative z-10 lg:w-2/3">
                    <h2 class="text-3xl font-bold text-white mb-4">Mipaymaster Payroll Support</h2>
                    <p class="text-brand-100 text-lg mb-8 md:mb-0 max-w-xl">
                        Payroll issues can't wait. Our dedicated support team in Lagos & Benin is available to ensure your employees get paid on time, every time.
                    </p>
                </div>
                
                <div class="relative z-10 flex flex-col gap-4 sm:flex-row lg:w-1/3 lg:justify-end">
                    <div class="bg-white/10 backdrop-blur-sm p-1 rounded-2xl">
                        <div class="flex items-center gap-4 bg-white text-brand-900 px-8 py-5 rounded-xl font-bold shadow-lg transition-transform hover:scale-[1.02]">
                            <div class="relative">
                                <div class="h-12 w-12 rounded-full bg-brand-50 overflow-hidden flex items-center justify-center border border-brand-100">
                                    <i data-lucide="headset" class="w-6 h-6 text-brand-600"></i>
                                </div>
                                <div class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full bg-green-500 border-2 border-white"></div>
                            </div>
                            <div class="text-left">
                                <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Talk to us</div>
                                <div class="text-lg">+234 800 MIPAYMASTER</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include 'includes/public_footer.php'; ?>

    <!-- Initialize Theme Logic Only (Lucide handled in footer) -->
    <!-- Initialize Theme Logic Only (Lucide handled in footer) -->
    <!-- Scripts now handled by public_footer.php to avoid conflicts -->
</body>
</html>
