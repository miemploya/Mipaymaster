<?php
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Placeholder logic for password reset
    $email = clean_input($_POST['email']);
    set_flash_message('success', 'If an account exists for this email, a reset link has been sent.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Mipaymaster</title>
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

    <!-- FORGOT PASSWORD CARD -->
    <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-8 border border-slate-100 dark:border-slate-800 relative z-10">
        <div class="text-center mb-8">
            <a href="../index.php">
                <img src="../assets/images/logo-light.png" alt="Mipaymaster" class="h-12 w-auto object-contain mx-auto mb-4 block dark:hidden transition-all duration-300 cursor-pointer">
                <img src="../assets/images/logo-dark.png" alt="Mipaymaster" class="h-12 w-auto object-contain mx-auto mb-4 hidden dark:block transition-all duration-300 cursor-pointer">
            </a>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Forgot Password?</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">No worries, we'll send you reset instructions.</p>
        </div>
        
        <?php display_flash_message(); ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                <input type="email" name="email" class="w-full px-4 py-3 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all" placeholder="name@company.com" required>
            </div>
            <button type="submit" class="w-full py-3 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold rounded-lg hover:bg-slate-800 dark:hover:bg-slate-200 transition-all shadow-lg active:scale-95">Reset Password</button>
        </form>
        
        <div class="mt-8 text-center">
            <a href="login.php" class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white mx-auto">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Sign In
            </a>
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
    </script>
</body>
</html>
