<?php
// Default title if not set
$page_title = $page_title ?? 'Dashboard'; 
?>
<!-- B. TOP HEADER BAR -->
<style>
    /* Sidebar Transitions */
    .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
    
    /* Toolbar transition */
    #collapsed-toolbar { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
    .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
    .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
</style>
<header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
    <div class="flex items-center gap-4">
        <!-- Mobile Toggle (Visible only on mobile) -->
        <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
        
        <!-- Mobile Logo -->
        <a href="index.php" class="md:hidden flex items-center gap-2">
            <img src="../assets/images/logo-light.png" alt="Mipaymaster" class="h-10 w-auto object-contain block dark:hidden">
            <img src="../assets/images/logo-dark.png" alt="Mipaymaster" class="h-10 w-auto object-contain hidden dark:block">
        </a>

        <h2 class="hidden md:block text-xl font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($page_title); ?></h2>
    </div>

    <div class="flex items-center gap-4">
        <!-- Theme Toggle -->
        <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
            <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
            <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
        </button>

        <button class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
            <i data-lucide="help-circle" class="w-5 h-5"></i>
        </button>

        <!-- Notification Bell -->
        <button id="notif-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors relative">
            <i data-lucide="bell" class="w-5 h-5"></i>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white dark:border-slate-950"></span>
        </button>

        <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 mx-2"></div>

        <!-- User Avatar -->
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-3 cursor-pointer focus:outline-none">
                <div class="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 flex items-center justify-center overflow-hidden">
                    <?php if (!empty($_SESSION['user_photo'])): ?>
                        <img src="../uploads/avatars/<?php echo htmlspecialchars($_SESSION['user_photo']); ?>" alt="User" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i data-lucide="user" class="w-5 h-5 text-slate-500 dark:text-slate-400"></i>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Role'); ?></p>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block"></i>
            </button>
            
            <!-- Dropdown Menu -->
            <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-100 dark:border-slate-700 py-1 z-50" style="display: none;">
                <a href="company.php" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Profile</a>
                <a href="company.php" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Settings</a>
                <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">Log Out</a>
            </div>
        </div>
    </div>
</header>
