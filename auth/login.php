<?php
require_once '../includes/functions.php';

// Login debug log file
$loginDebugLog = 'C:\xampp\htdocs\Mipaymaster\login_debug.log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    
    // Log login attempt
    file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "Login attempt for email: $email\n", FILE_APPEND);
    
    if (empty($email) || empty($password)) {
        file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "FAILED: Empty email or password\n", FILE_APPEND);
        set_flash_message('error', 'Please enter both email and password.');
    } else {
        try {
            // Check database connection
            if (!isset($pdo)) {
                file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "FAILED: PDO connection not established\n", FILE_APPEND);
                set_flash_message('error', 'Database connection error. Please try again.');
            } else {
                file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "Database connected, querying user...\n", FILE_APPEND);
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "FAILED: User not found for email: $email\n", FILE_APPEND);
                    set_flash_message('error', 'Invalid email or password.');
                } else {
                    file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "User found (ID: {$user['id']}), verifying password...\n", FILE_APPEND);
                    
                    if (password_verify($password, $user['password_hash'])) {
                        // Success
                        file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "SUCCESS: Password verified for user {$user['id']}\n", FILE_APPEND);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['company_id'] = $user['company_id'];
                        $_SESSION['user_name'] = $user['first_name'];
                        
                        log_audit($user['company_id'], $user['id'], 'LOGIN', 'User logged in successfully');

                        file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "Session set, redirecting to dashboard...\n", FILE_APPEND);
                        redirect('../dashboard/index.php');
                    } else {
                        file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "FAILED: Password verification failed for user {$user['id']}\n", FILE_APPEND);
                        set_flash_message('error', 'Invalid email or password.');
                    }
                }
            }
        } catch (Exception $e) {
            file_put_contents($loginDebugLog, date('[Y-m-d H:i:s] ') . "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            set_flash_message('error', 'An error occurred. Please try again.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Mipaymaster</title>
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

    <!-- SIGN IN CARD -->
    <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-8 border border-slate-100 dark:border-slate-800 relative z-10">
        <div class="text-center mb-8">
            <a href="../index.php">
                <img src="../assets/images/logo-light.png" alt="Mipaymaster" class="h-12 w-auto object-contain mx-auto mb-4 block dark:hidden transition-all duration-300 cursor-pointer">
                <img src="../assets/images/logo-dark.png" alt="Mipaymaster" class="h-12 w-auto object-contain mx-auto mb-4 hidden dark:block transition-all duration-300 cursor-pointer">
            </a>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Welcome back</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">Please enter your details to sign in.</p>
        </div>
        
        <?php display_flash_message(); ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                <input type="email" name="email" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="name@company.com" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                <div class="relative">
                    <input id="signin-password" name="password" type="password" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="••••••••" required>
                    <button type="button" onclick="togglePassword('signin-password', this)" class="absolute right-3 top-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i data-lucide="eye" class="w-5 h-5"></i>
                        <i data-lucide="eye-off" class="w-5 h-5 hidden"></i>
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span class="ml-2 text-sm text-slate-600 dark:text-slate-400">Remember me</span>
                </label>
                <a href="forgot-password.php" class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">Forgot password?</a>
            </div>
            <button type="submit" class="w-full py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-lg hover:bg-slate-800 dark:hover:bg-slate-200 transition-all shadow-lg active:scale-95">Sign In</button>
        </form>
        
        <div class="mt-8 text-center">
            <p class="text-sm text-slate-600 dark:text-slate-400">Don't have an account? <a href="signup.php" class="font-bold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300">Sign up</a></p>
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
