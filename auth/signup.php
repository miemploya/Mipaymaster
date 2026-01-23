<?php
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = clean_input($_POST['company_name']);
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    // Basic Validation
    if (empty($company_name) || empty($email) || empty($password)) {
        set_flash_message('error', 'All fields are required.');
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            set_flash_message('error', 'Email is already registered.');
        } else {
            // Register
            $result = register_company_and_user($company_name, $email, $password, $first_name, $last_name);
            if ($result['status']) {
                // Login immediately
                $_SESSION['user_id'] = $result['user_id'];
                $_SESSION['role'] = $result['role'];
                $_SESSION['company_id'] = $result['company_id'];
                $_SESSION['user_name'] = $first_name;
                
                redirect('../dashboard/index.php');
            } else {
                set_flash_message('error', 'Registration failed: ' . $result['message']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Mipaymaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 100: '#e0e7ff', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81' } }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6 relative bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">

    <!-- Background Blobs -->
    <div class="absolute -left-20 top-0 h-[30rem] w-[30rem] rounded-full bg-blue-100/50 dark:bg-blue-900/20 blur-3xl filter mix-blend-multiply dark:mix-blend-normal opacity-70 dark:opacity-30 -z-10"></div>
    <div class="absolute right-0 bottom-0 h-[30rem] w-[30rem] rounded-full bg-purple-100/50 dark:bg-purple-900/20 blur-3xl filter mix-blend-multiply dark:mix-blend-normal opacity-70 dark:opacity-30 -z-10"></div>

    <!-- Back to Home Button -->
    <a href="../index.php" class="absolute top-6 left-6 flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors z-20">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Home
    </a>

    <!-- Theme Toggle -->
    <button id="theme-toggle" class="absolute top-6 right-6 p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors z-20">
        <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
        <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
    </button>

    <!-- SIGN UP CARD -->
    <div class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-8 border border-slate-100 dark:border-slate-800 relative z-10">
        <div class="text-center mb-8">
            <a href="../index.php">
                <img src="../assets/images/logo-light.png" alt="Mipaymaster" class="h-12 w-auto object-contain mx-auto mb-4 block dark:hidden transition-all duration-300 cursor-pointer">
                <img src="../assets/images/logo-dark.png" alt="Mipaymaster" class="h-12 w-auto object-contain mx-auto mb-4 hidden dark:block transition-all duration-300 cursor-pointer">
            </a>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Create an account</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">Start managing your payroll in minutes.</p>
        </div>
        
        <?php display_flash_message(); ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Company Name</label>
                <input type="text" name="company_name" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="e.g. Acme Industries Ltd" required>
            </div>
            
            <div class="flex gap-4">
                <div class="w-1/2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">First Name</label>
                    <input type="text" name="first_name" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="John" required>
                </div>
                <div class="w-1/2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Last Name</label>
                    <input type="text" name="last_name" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="Doe" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Work Email</label>
                <input type="email" name="email" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="you@company.com" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                <div class="relative">
                    <input id="signup-password" name="password" type="password" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="Create a strong password" required>
                    <button type="button" onclick="togglePassword('signup-password', this)" class="absolute right-3 top-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                        <i data-lucide="eye-off" class="w-5 h-5 hidden"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="w-full py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-lg hover:bg-slate-800 dark:hover:bg-slate-200 transition-all shadow-lg active:scale-95">Create Account</button>
        </form>
        
        <div class="mt-8 text-center">
            <p class="text-sm text-slate-600 dark:text-slate-400">Already have an account? <a href="login.php" class="font-bold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">Sign in</a></p>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const themeBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;

        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        themeBtn.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
            lucide.createIcons();
        });

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icons = btn.querySelectorAll('svg');
            if (input.type === 'password') {
                input.type = 'text';
                icons[0].classList.add('hidden');
                icons[1].classList.remove('hidden');
            } else {
                input.type = 'password';
                icons[0].classList.remove('hidden');
                icons[1].classList.add('hidden');
            }
        }
    </script>
</body>
</html>
