<?php
/**
 * Public ID Card Verification Page
 * Anyone can scan QR code/barcode and view the full ID card
 * No login required
 */

// Allow public access - no login required
require_once 'config/db.php';
require_once 'includes/id_card_generator.php';

$token = $_GET['token'] ?? '';
$error = '';
$employee = null;
$settings = null;

if (empty($token)) {
    $error = 'Invalid verification link. No token provided.';
} else {
    // Find employee by token
    $employee = get_employee_by_token($token);
    
    if (!$employee) {
        $error = 'Invalid or expired verification link.';
    } else {
        $settings = get_id_card_settings($employee['company_id']);
        
        // Check validity
        $validity_years = $settings['validity_years'] ?? 1;
        $hire_date = $employee['hire_date'] ?? $employee['created_at'] ?? date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime($hire_date . " +{$validity_years} years"));
        $is_valid = strtotime($expiry_date) >= time();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee ID Verification - Mipaymaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81' } }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        <?php if ($employee): ?>
        <?php echo get_id_card_css($settings); ?>
        <?php endif; ?>
    </style>
</head>
<body class="bg-gradient-to-br from-slate-100 to-slate-200 min-h-screen">
    
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-brand-600 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-900">ID Card Verification</h1>
                    <p class="text-xs text-slate-500">Powered by Mipaymaster</p>
                </div>
            </div>
            <?php if ($employee && $is_valid): ?>
            <div class="flex items-center gap-2 px-4 py-2 bg-green-100 text-green-700 rounded-full">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span class="font-bold text-sm">VERIFIED</span>
            </div>
            <?php elseif ($employee && !$is_valid): ?>
            <div class="flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-full">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <span class="font-bold text-sm">EXPIRED</span>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <main class="max-w-4xl mx-auto px-4 py-8">
        <?php if ($error): ?>
        <!-- Error State -->
        <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Verification Failed</h2>
            <p class="text-slate-500 mb-6"><?php echo htmlspecialchars($error); ?></p>
            <a href="/" class="inline-flex items-center gap-2 px-6 py-3 bg-brand-600 text-white rounded-lg font-medium hover:bg-brand-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Return Home
            </a>
        </div>
        <?php else: ?>
        <!-- Success State - Show Full ID Card -->
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-slate-900 mb-2">Employee ID Card</h2>
            <p class="text-slate-500">This ID card has been verified as <?php echo $is_valid ? 'valid' : 'expired'; ?></p>
        </div>
        
        <!-- ID Card Display -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="id-card-container">
                <?php echo generate_id_card_front_html($employee['id'], $employee['company_id'], true); ?>
                <?php echo generate_id_card_back_html($employee['id'], $employee['company_id'], true); ?>
            </div>
        </div>
        
        <!-- Verification Details -->
        <div class="mt-6 bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Verification Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-slate-50 rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Employee Name</p>
                    <p class="font-bold text-slate-900"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                </div>
                <div class="p-4 bg-slate-50 rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Employee ID</p>
                    <p class="font-bold text-slate-900"><?php echo htmlspecialchars($employee['payroll_id'] ?? 'N/A'); ?></p>
                </div>
                <div class="p-4 bg-slate-50 rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Status</p>
                    <p class="font-bold <?php echo $is_valid ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $is_valid ? 'VALID' : 'EXPIRED'; ?>
                    </p>
                </div>
                <div class="p-4 bg-slate-50 rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Valid Until</p>
                    <p class="font-bold text-slate-900"><?php echo date('F Y', strtotime($expiry_date)); ?></p>
                </div>
            </div>
            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong>Note:</strong> This verification confirms the employee's identity card was issued by <?php echo htmlspecialchars($employee['company_name'] ?? 'the company'); ?>. 
                    For any concerns, please contact the issuing organization directly.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="mt-12 py-6 border-t border-slate-200 bg-white">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <p class="text-sm text-slate-500">
                ID Verification System &copy; <?php echo date('Y'); ?> Mipaymaster
            </p>
        </div>
    </footer>
    
</body>
</html>
