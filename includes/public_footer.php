
    <!-- Footer -->
    <footer class="bg-slate-900 dark:bg-black pt-16 pb-8 text-slate-400 border-t border-transparent dark:border-slate-800 transition-colors duration-300">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                
                <!-- Brand -->
                <div>
                    <div class="flex items-center gap-2 mb-6">
                        <!-- Footer Logo -->
                        <img src="assets/images/logo-light.png" alt="Mipaymaster" class="h-10 w-auto object-contain block dark:hidden">
                        <img src="assets/images/logo-dark.png" alt="Mipaymaster" class="h-10 w-auto object-contain hidden dark:block">
                    </div>
                    <p class="text-sm leading-relaxed mb-6">
                        Simplifying payroll and HR compliance for forward-thinking African businesses.
                    </p>
                    <div class="flex gap-4">
                        <a href="#" class="hover:text-white transition-colors"><i data-lucide="twitter" class="w-5 h-5"></i></a>
                        <a href="#" class="hover:text-white transition-colors"><i data-lucide="linkedin" class="w-5 h-5"></i></a>
                        <a href="#" class="hover:text-white transition-colors"><i data-lucide="facebook" class="w-5 h-5"></i></a>
                    </div>
                </div>

                <!-- Links 1 -->
                <div>
                    <h4 class="text-white font-bold mb-6">Product</h4>
                    <ul class="space-y-4 text-sm">
                        <li><a href="features.php" class="hover:text-brand-400 transition-colors">Features</a></li>
                        <li><a href="pricing.php" class="hover:text-brand-400 transition-colors">Pricing</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Security</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Updates</a></li>
                    </ul>
                </div>

                <!-- Links 2 -->
                <div>
                    <h4 class="text-white font-bold mb-6">Company</h4>
                    <ul class="space-y-4 text-sm">
                        <li><a href="#" class="hover:text-brand-400 transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Careers</a></li>
                        <li><a href="contact.php" class="hover:text-brand-400 transition-colors">Contact</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Partners</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h4 class="text-white font-bold mb-6">Contact</h4>
                    <ul class="space-y-4 text-sm">
                        <li class="flex items-center gap-2">
                            <i data-lucide="mail" class="w-4 h-4 text-brand-500"></i>
                            support@mipaymaster.com
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="phone" class="w-4 h-4 text-brand-500"></i>
                            +234 800 123 4567
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-4 h-4 text-brand-500"></i>
                            Benin City, Nigeria
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-800 pt-8 text-center md:text-left flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm">© <?php echo date('Y'); ?> Mipaymaster. All rights reserved.</p>
                <div class="flex gap-6 text-sm">
                    <a href="#" class="hover:text-white">Privacy Policy</a>
                    <a href="#" class="hover:text-white">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Initialize Lucide Icons if not already (Common script block) -->
    <script>
        // Icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Theme Toggle Logic
        const themeBtns = [document.getElementById('theme-toggle'), document.getElementById('theme-toggle-mobile')];
        const html = document.documentElement;

        themeBtns.forEach(btn => {
            if(btn) {
                btn.addEventListener('click', () => {
                    html.classList.toggle('dark');
                    if (html.classList.contains('dark')) {
                        localStorage.theme = 'dark';
                    } else {
                        localStorage.theme = 'light';
                    }
                    // Re-render icons if needed (unlikely for simple class switch but safe)
                    /* lucide.createIcons(); */ 
                });
            }
        });

        // Mobile Menu Logic
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
