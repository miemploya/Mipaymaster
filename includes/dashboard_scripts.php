<script>
    // Initialize Icons
    lucide.createIcons();

    // --- SHARED DOM ELEMENTS ---
    const themeBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const notifToggle = document.getElementById('notif-toggle');
    const notifClose = document.getElementById('notif-close');
    const notifPanel = document.getElementById('notif-panel');
    const overlay = document.getElementById('overlay');
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
    const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
    const collapsedToolbar = document.getElementById('collapsed-toolbar');

    // --- THEME LOGIC ---
    // Check local storage on load
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }

    if(themeBtn) {
        themeBtn.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
            // Trigger chart update if exists
            if (typeof updateChartTheme === 'function') {
                updateChartTheme();
            }
        });
    }

    // --- OVERLAY LOGIC ---
    function toggleOverlay(show) {
        if(!overlay) return;
        if (show) overlay.classList.remove('hidden');
        else overlay.classList.add('hidden');
    }

    // --- NOTIFICATION LOGIC ---
    if(notifToggle && notifPanel) {
        notifToggle.addEventListener('click', () => {
            notifPanel.style.visibility = 'visible'; // Make visible first
            notifPanel.classList.remove('translate-x-full');
            toggleOverlay(true);
        });

        if(notifClose) {
            notifClose.addEventListener('click', () => {
                notifPanel.classList.add('translate-x-full');
                // Hide after transition completes
                setTimeout(() => { notifPanel.style.visibility = 'hidden'; }, 300);
                toggleOverlay(false);
            });
        }
    }

    if(overlay) {
        overlay.addEventListener('click', () => {
            if(notifPanel) {
                notifPanel.classList.add('translate-x-full');
                setTimeout(() => { notifPanel.style.visibility = 'hidden'; }, 300);
            }
            if(sidebar) sidebar.classList.add('-translate-x-full'); 
            toggleOverlay(false);
        });
    }

    // --- MOBILE SIDEBAR ---
    if(mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            if (!sidebar.classList.contains('-translate-x-full')) {
                toggleOverlay(true);
            } else {
                toggleOverlay(false);
            }
        });
    }

    // --- DESKTOP SIDEBAR COLLAPSE LOGIC ---
    function toggleSidebar() {
        if(!sidebar) return;
        
        // Toggle Width
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-0');
        sidebar.classList.toggle('p-0'); 
        
        // Toggle Controls
        if (sidebar.classList.contains('w-0')) {
            if(collapsedToolbar) {
                collapsedToolbar.classList.remove('toolbar-hidden');
                collapsedToolbar.classList.add('toolbar-visible');
            }
        } else {
            if(collapsedToolbar) {
                collapsedToolbar.classList.add('toolbar-hidden');
                collapsedToolbar.classList.remove('toolbar-visible');
            }
        }
    }

    if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
    if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);

</script>
