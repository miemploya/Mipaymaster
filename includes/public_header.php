<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiPayMaster - Modern Payroll & HR Platform</title>
    <!-- Remove custom style.css if it conflicts, or keep it but ensure Tailwind takes precedence -->
    <!-- <link rel="stylesheet" href="assets/css/style.css"> -->
    
    <!-- Tailwind CSS & Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    <script>
        // Check for saved user preference, if any, on load of the website
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">

    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/80 dark:bg-slate-950/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 z-50 transition-colors duration-300">
        <div class="container mx-auto px-4 lg:px-6 h-16 flex items-center justify-between">
            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-2">
                <!-- Light Theme Logo -->
                <img src="assets/images/logo-light.png" alt="MiPayMaster" class="h-10 w-auto block dark:hidden">
                <!-- Dark Theme Logo -->
                <img src="assets/images/logo-dark.png" alt="MiPayMaster" class="h-10 w-auto hidden dark:block">
            </a>

            <!-- Desktop Nav -->
            <div class="hidden md:flex items-center gap-8">
                <a href="features.php" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Features</a>
                <a href="pricing.php" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Pricing</a>
                <a href="contact.php" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Contact</a>
            </div>

            <!-- Actions -->
            <div class="hidden md:flex items-center gap-4">
                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
                    <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                    <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                </button>

                <a href="auth/login.php" class="text-sm font-bold text-slate-700 dark:text-white hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Sign In</a>
                <a href="auth/signup.php" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold rounded-full transition-all shadow-lg shadow-brand-500/30">Get Started</a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden p-2 text-slate-600 dark:text-slate-300">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Mobile Menu (Hidden by default) -->
        <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 p-4 absolute w-full left-0 top-16 shadow-xl">
            <div class="flex flex-col gap-4">
                <a href="features.php" class="text-base font-medium text-slate-600 dark:text-slate-300">Features</a>
                <a href="pricing.php" class="text-base font-medium text-slate-600 dark:text-slate-300">Pricing</a>
                <a href="contact.php" class="text-base font-medium text-slate-600 dark:text-slate-300">Contact</a>
                <hr class="border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-300 font-medium">Theme</span>
                    <button id="theme-toggle-mobile" class="p-2 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                        <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                    </button>
                </div>
                <a href="auth/login.php" class="w-full text-center py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-bold">Sign In</a>
                <a href="auth/signup.php" class="w-full text-center py-3 rounded-xl bg-brand-600 text-white font-bold">Get Started</a>
            </div>
        </div>
    </nav>
    
    <!-- Spacer for fixed header -->
    <div class="h-16"></div>
