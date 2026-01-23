<?php include 'includes/public_header.php'; ?>

<div class="min-h-screen bg-slate-50 dark:bg-slate-950 font-sans transition-colors duration-300">

    <!-- Hero Section -->
    <section class="bg-white dark:bg-slate-900 pt-24 pb-12 text-center px-4 border-b border-slate-200 dark:border-slate-800 transition-colors duration-300">
        <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-white mb-4">Get in touch</h1>
        <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">Have questions about our pricing, features, or need a custom demo? We're here to help.</p>
    </section>
    
    <div class="max-w-7xl mx-auto px-4 py-16">
        <div class="flex flex-col lg:flex-row gap-12 lg:gap-24">
            
            <!-- Contact Info -->
            <div class="lg:w-1/3 space-y-8">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-brand-600 dark:text-brand-400"></i> Office
                    </h3>
                    <p class="text-slate-600 dark:text-slate-400 pl-7">
                        123 Innovation Drive,<br>
                        Lagos, Nigeria.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <i data-lucide="mail" class="w-5 h-5 text-brand-600 dark:text-brand-400"></i> Email
                    </h3>
                    <div class="pl-7 flex flex-col gap-1">
                        <a href="mailto:support@mipaymaster.com" class="text-slate-600 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">support@mipaymaster.com</a>
                        <a href="mailto:sales@mipaymaster.com" class="text-slate-600 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">sales@mipaymaster.com</a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <i data-lucide="phone" class="w-5 h-5 text-brand-600 dark:text-brand-400"></i> Phone
                    </h3>
                    <p class="text-slate-600 dark:text-slate-400 pl-7">+234 123 456 7890</p>
                </div>

                <div class="p-6 bg-brand-50 dark:bg-brand-900/20 rounded-2xl border border-brand-100 dark:border-brand-900/30 mt-8">
                    <h4 class="font-bold text-brand-900 dark:text-brand-100 mb-2">Need immediate help?</h4>
                    <p class="text-sm text-brand-700 dark:text-brand-300 mb-4">Our support team is available Mon-Fri, 9am - 5pm WAT.</p>
                    <a href="#" class="text-sm font-bold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 flex items-center gap-1">
                        Visit Help Center <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="lg:w-2/3 bg-white dark:bg-slate-900 p-8 md:p-10 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-800 transition-colors duration-300">
                <form action="#" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Full Name</label>
                            <input type="text" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-900 outline-none transition-all" placeholder="John Doe" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Work Email</label>
                            <input type="email" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-900 outline-none transition-all" placeholder="john@company.com" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Company Size</label>
                        <select class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-900 outline-none transition-all">
                            <option>1-10 Employees</option>
                            <option>11-50 Employees</option>
                            <option>51-200 Employees</option>
                            <option>200+ Employees</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Message</label>
                        <textarea rows="5" class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-900 outline-none transition-all resize-none" placeholder="How can we help you?" required></textarea>
                    </div>

                    <button type="submit" class="w-full py-4 bg-brand-600 text-white font-bold rounded-xl hover:bg-brand-700 shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5">
                        Send Message
                    </button>
                    <p class="text-center text-xs text-slate-400 mt-4">By sending this message, you agree to our Terms of Service and Privacy Policy.</p>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/public_footer.php'; ?>
