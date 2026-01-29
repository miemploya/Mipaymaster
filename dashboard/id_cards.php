<?php
/**
 * Enhanced ID Card Management v2.0
 * Features: 20 Templates, 3 Shapes, Logo Positioning, Template Gallery
 */

require_once '../includes/functions.php';
require_once '../includes/id_card_generator.php';
require_login();

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'] ?? 'Company';

// Fetch employees
$stmt = $pdo->prepare("SELECT e.*, d.name as department_name 
                       FROM employees e 
                       LEFT JOIN departments d ON e.department_id = d.id 
                       WHERE e.company_id = ? AND e.employment_status = 'active'
                       ORDER BY e.first_name, e.last_name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current settings
$settings = get_id_card_settings($company_id);
$templates = get_template_definitions();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Management - Mipaymaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { 
                        brand: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81' }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .template-card { transition: all 0.2s ease; }
        .template-card:hover { transform: translateY(-2px); }
        .template-card.selected { ring: 3px; ring-color: #4f46e5; }
        .shape-btn.active { background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 min-h-screen" x-data="idCardManager()">
    
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <?php include '../includes/dashboard_header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <!-- Page Title -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">ID Card Management</h1>
                    <p class="text-slate-500 dark:text-slate-400">Design and generate employee ID cards with custom templates</p>
                </div>
                
                <!-- Tabs -->
                <div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-slate-700">
                    <button @click="activeTab = 'templates'" 
                            :class="activeTab === 'templates' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'"
                            class="px-4 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i> Templates
                    </button>
                    <button @click="activeTab = 'settings'" 
                            :class="activeTab === 'settings' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'"
                            class="px-4 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2">
                        <i data-lucide="settings" class="w-4 h-4"></i> Settings
                    </button>
                    <button @click="activeTab = 'employees'" 
                            :class="activeTab === 'employees' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'"
                            class="px-4 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4"></i> Generate Cards
                    </button>
                </div>
                
                <!-- Templates Tab -->
                <div x-show="activeTab === 'templates'" x-cloak>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
                        <!-- Shape Selector -->
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">Card Shape</label>
                            <div class="flex gap-3">
                                <button @click="settings.card_shape = 'horizontal'" 
                                        :class="settings.card_shape === 'horizontal' ? 'active' : 'bg-slate-100 dark:bg-slate-800'"
                                        class="shape-btn flex items-center gap-2 px-4 py-3 rounded-lg font-medium transition-all">
                                    <div class="w-10 h-6 border-2 border-current rounded"></div>
                                    <span>Horizontal</span>
                                </button>
                                <button @click="settings.card_shape = 'vertical'" 
                                        :class="settings.card_shape === 'vertical' ? 'active' : 'bg-slate-100 dark:bg-slate-800'"
                                        class="shape-btn flex items-center gap-2 px-4 py-3 rounded-lg font-medium transition-all">
                                    <div class="w-6 h-10 border-2 border-current rounded"></div>
                                    <span>Vertical</span>
                                </button>
                                <button @click="settings.card_shape = 'square'" 
                                        :class="settings.card_shape === 'square' ? 'active' : 'bg-slate-100 dark:bg-slate-800'"
                                        class="shape-btn flex items-center gap-2 px-4 py-3 rounded-lg font-medium transition-all">
                                    <div class="w-8 h-8 border-2 border-current rounded"></div>
                                    <span>Square</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Template Categories -->
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">Choose Template Style</label>
                            <div class="flex gap-2 flex-wrap">
                                <button @click="templateFilter = 'all'" 
                                        :class="templateFilter === 'all' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                        class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors">All</button>
                                <button @click="templateFilter = 'Corporate'" 
                                        :class="templateFilter === 'Corporate' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                        class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors">Corporate</button>
                                <button @click="templateFilter = 'Modern'" 
                                        :class="templateFilter === 'Modern' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                        class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors">Modern</button>
                                <button @click="templateFilter = 'Creative'" 
                                        :class="templateFilter === 'Creative' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                        class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors">Creative</button>
                                <button @click="templateFilter = 'Elegant'" 
                                        :class="templateFilter === 'Elegant' ? 'bg-brand-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                        class="px-3 py-1.5 rounded-full text-xs font-bold transition-colors">Elegant</button>
                            </div>
                        </div>
                        
                        <!-- Template Gallery -->
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            <template x-for="(tpl, id) in filteredTemplates" :key="id">
                                <div @click="selectTemplate(id)" 
                                     :class="settings.template_id == id ? 'ring-3 ring-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'bg-slate-50 dark:bg-slate-900'"
                                     class="template-card cursor-pointer rounded-xl p-4 border border-slate-200 dark:border-slate-700 hover:border-brand-300 transition-all">
                                    <!-- Template Preview -->
                                    <div class="aspect-[1.6/1] rounded-lg mb-3 flex items-center justify-center text-2xl font-bold"
                                         :style="getTemplatePreviewStyle(id)">
                                        <span class="opacity-50" x-text="id"></span>
                                    </div>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white" x-text="tpl.name"></p>
                                    <p class="text-xs text-slate-500" x-text="tpl.category"></p>
                                    <div x-show="settings.template_id == id" class="mt-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-brand-600 text-white rounded text-xs font-bold">
                                            <i data-lucide="check" class="w-3 h-3"></i> Selected
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Save Button -->
                        <div class="mt-6 flex justify-end">
                            <button @click="saveSettings()" 
                                    :disabled="saving"
                                    class="px-6 py-3 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 disabled:opacity-50 flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                <span x-text="saving ? 'Saving...' : 'Save Template Selection'"></span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Tab -->
                <div x-show="activeTab === 'settings'" x-cloak>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Settings Form -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Card Settings</h3>
                            
                            <div class="space-y-5">
                                <!-- Validity -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Validity Period</label>
                                    <div class="flex gap-3">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" x-model="settings.validity_years" value="1" class="text-brand-600">
                                            <span>1 Year</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" x-model="settings.validity_years" value="2" class="text-brand-600">
                                            <span>2 Years</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Code Type -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Code Type (Scannable)</label>
                                    <select x-model="settings.code_type" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2.5">
                                        <option value="qr">QR Code (Recommended)</option>
                                        <option value="barcode">Barcode</option>
                                        <option value="none">None</option>
                                    </select>
                                    <p class="text-xs text-slate-500 mt-1">QR codes link to a public verification page when scanned</p>
                                </div>
                                
                                <!-- Logo Position -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Logo Position</label>
                                    <div class="flex gap-3">
                                        <button @click="settings.logo_position = 'left'" 
                                                :class="settings.logo_position === 'left' ? 'bg-brand-100 border-brand-500 text-brand-700' : 'bg-slate-50 border-slate-200'"
                                                class="flex-1 py-2 rounded-lg border-2 font-medium text-sm">Left</button>
                                        <button @click="settings.logo_position = 'center'" 
                                                :class="settings.logo_position === 'center' ? 'bg-brand-100 border-brand-500 text-brand-700' : 'bg-slate-50 border-slate-200'"
                                                class="flex-1 py-2 rounded-lg border-2 font-medium text-sm">Center</button>
                                        <button @click="settings.logo_position = 'right'" 
                                                :class="settings.logo_position === 'right' ? 'bg-brand-100 border-brand-500 text-brand-700' : 'bg-slate-50 border-slate-200'"
                                                class="flex-1 py-2 rounded-lg border-2 font-medium text-sm">Right</button>
                                    </div>
                                </div>
                                
                                <!-- Colors -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Primary Color</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="settings.primary_color" class="w-10 h-10 rounded cursor-pointer">
                                            <input type="text" x-model="settings.primary_color" class="flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2 text-sm font-mono">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Secondary Color</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="settings.secondary_color" class="w-10 h-10 rounded cursor-pointer">
                                            <input type="text" x-model="settings.secondary_color" class="flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2 text-sm font-mono">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Accent Color</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="settings.accent_color" class="w-10 h-10 rounded cursor-pointer">
                                            <input type="text" x-model="settings.accent_color" class="flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2 text-sm font-mono">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Text Color</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="settings.text_color" class="w-10 h-10 rounded cursor-pointer">
                                            <input type="text" x-model="settings.text_color" class="flex-1 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2 text-sm font-mono">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Display Options -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Display Options</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" x-model="settings.show_employee_id" class="rounded text-brand-600">
                                            <span class="text-sm">Show Employee ID</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" x-model="settings.show_department" class="rounded text-brand-600">
                                            <span class="text-sm">Show Department</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" x-model="settings.show_designation" class="rounded text-brand-600">
                                            <span class="text-sm">Show Job Title</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Emergency Contact -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Emergency Contact (Back)</label>
                                    <input type="text" x-model="settings.emergency_contact" 
                                           placeholder="+234 xxx xxxx" 
                                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2.5">
                                </div>
                                
                                <!-- Custom Text -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Custom Back Text</label>
                                    <textarea x-model="settings.custom_back_text" rows="2" 
                                              placeholder="This card is property of the company..."
                                              class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2.5 text-sm"></textarea>
                                </div>
                                
                                <button @click="saveSettings()" 
                                        :disabled="saving"
                                        class="w-full py-3 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 disabled:opacity-50 flex items-center justify-center gap-2">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    <span x-text="saving ? 'Saving...' : 'Save Settings'"></span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Live Preview -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Live Preview</h3>
                                <button @click="loadPreview()" class="text-sm text-brand-600 hover:underline flex items-center gap-1">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh
                                </button>
                            </div>
                            <div id="preview-container" class="flex flex-col gap-4 items-center">
                                <div class="text-center text-slate-500 py-8">
                                    <i data-lucide="id-card" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                                    <p>Click "Refresh" to see preview</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Employees Tab -->
                <div x-show="activeTab === 'employees'" x-cloak>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                            <input type="text" x-model="searchQuery" 
                                   placeholder="Search employees..." 
                                   class="w-full md:w-80 rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 p-2.5">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                            <?php foreach ($employees as $emp): ?>
                            <div class="bg-slate-50 dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-700"
                                 x-show="'<?php echo strtolower($emp['first_name'] . ' ' . $emp['last_name']); ?>'.includes(searchQuery.toLowerCase()) || searchQuery === ''">
                                <div class="flex items-center gap-4 mb-4">
                                    <?php if (!empty($emp['photo_path'])): ?>
                                    <img src="../uploads/photos/<?php echo htmlspecialchars($emp['photo_path']); ?>" 
                                         class="w-14 h-14 rounded-xl object-cover">
                                    <?php else: ?>
                                    <div class="w-14 h-14 rounded-xl bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center text-brand-600 font-bold text-lg">
                                        <?php echo strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1)); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </p>
                                        <p class="text-sm text-slate-500"><?php echo htmlspecialchars($emp['job_title'] ?? 'Staff'); ?></p>
                                        <p class="text-xs text-brand-600"><?php echo htmlspecialchars($emp['payroll_id'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="previewEmployee(<?php echo $emp['id']; ?>)" 
                                            class="flex-1 py-2 px-3 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium hover:bg-slate-300 dark:hover:bg-slate-700 flex items-center justify-center gap-1">
                                        <i data-lucide="eye" class="w-4 h-4"></i> Preview
                                    </button>
                                    <a href="../ajax/id_card_ajax.php?action=download&employee_id=<?php echo $emp['id']; ?>" 
                                       target="_blank"
                                       class="flex-1 py-2 px-3 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 flex items-center justify-center gap-1">
                                        <i data-lucide="download" class="w-4 h-4"></i> Download
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($employees)): ?>
                            <div class="col-span-full text-center py-12 text-slate-500">
                                <i data-lucide="users" class="w-16 h-16 mx-auto mb-4 opacity-30"></i>
                                <p class="text-lg font-medium">No employees found</p>
                                <p class="text-sm">Add employees to generate ID cards</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Preview Modal -->
    <div x-show="previewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div @click.outside="previewModal = false" class="bg-white dark:bg-slate-950 rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-auto">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">ID Card Preview</h3>
                <button @click="previewModal = false" class="text-slate-500 hover:text-slate-700">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6">
                <style x-text="previewCss"></style>
                <div class="id-card-container" x-html="previewFront + previewBack"></div>
                <div class="mt-6 flex gap-3 justify-center">
                    <a :href="'../ajax/id_card_ajax.php?action=download&employee_id=' + previewEmployeeId" 
                       target="_blank"
                       class="px-6 py-2.5 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i> Download Card
                    </a>
                    <a :href="getVerificationUrl()" 
                       target="_blank"
                       class="px-6 py-2.5 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg font-medium hover:bg-slate-300 flex items-center gap-2">
                        <i data-lucide="qr-code" class="w-4 h-4"></i> View Public Link
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
         class="fixed bottom-6 right-6 px-6 py-3 text-white rounded-lg shadow-lg flex items-center gap-2 z-50">
        <i :data-lucide="toast.type === 'success' ? 'check-circle' : 'alert-circle'" class="w-5 h-5"></i>
        <span x-text="toast.message"></span>
    </div>
    
    <script>
        function idCardManager() {
            return {
                activeTab: 'templates',
                templateFilter: 'all',
                searchQuery: '',
                saving: false,
                previewModal: false,
                previewEmployeeId: null,
                previewFront: '',
                previewBack: '',
                previewCss: '',
                verificationUrl: '',
                toast: { show: false, type: 'success', message: '' },
                
                settings: <?php echo json_encode($settings); ?>,
                templates: <?php echo json_encode($templates); ?>,
                
                get filteredTemplates() {
                    if (this.templateFilter === 'all') return this.templates;
                    return Object.fromEntries(
                        Object.entries(this.templates).filter(([id, tpl]) => tpl.category === this.templateFilter)
                    );
                },
                
                selectTemplate(id) {
                    this.settings.template_id = parseInt(id);
                },
                
                getTemplatePreviewStyle(id) {
                    const styles = {
                        1: 'background: linear-gradient(135deg, ' + this.settings.primary_color + ', ' + this.settings.secondary_color + ')',
                        2: 'background: ' + this.settings.primary_color + '; border-left: 4px solid ' + this.settings.accent_color,
                        3: 'background: white; border: 2px solid ' + this.settings.primary_color,
                        4: 'background: linear-gradient(180deg, ' + this.settings.primary_color + ' 40%, white 40%)',
                        5: 'background: white; border: 3px double ' + this.settings.primary_color,
                        6: 'background: #f8fafc; border-bottom: 4px solid ' + this.settings.primary_color,
                        7: 'background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1)',
                        8: 'background: linear-gradient(135deg, #1a1a2e, #16213e); color: white',
                        9: 'background: ' + this.settings.primary_color + '; color: white',
                        10: 'background: #f1f5f9; border-left: 4px solid ' + this.settings.primary_color,
                        11: 'background: linear-gradient(to bottom, white 60%, ' + this.settings.primary_color + ' 60%)',
                        12: 'background: linear-gradient(135deg, ' + this.settings.primary_color + ', ' + this.settings.secondary_color + ', ' + this.settings.accent_color + ')',
                        13: 'background: white; position: relative',
                        14: 'background: linear-gradient(135deg, white 60%, ' + this.settings.primary_color + ' 60%)',
                        15: 'background: #0f172a; color: white',
                        16: 'background: linear-gradient(135deg, #1a1a1a, #2d2d2d); border: 2px solid #d4af37',
                        17: 'background: linear-gradient(135deg, #1a1a1a, #374151); border: 2px solid #c0c0c0',
                        18: 'background: linear-gradient(135deg, #1e3a5f, #0d1b2a); color: white',
                        19: 'background: linear-gradient(135deg, #f5f5f5, #e0e0e0)',
                        20: 'background: linear-gradient(135deg, #667eea, #764ba2); color: white'
                    };
                    return styles[id] || 'background: #e2e8f0';
                },
                
                async saveSettings() {
                    this.saving = true;
                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_settings');
                        Object.entries(this.settings).forEach(([key, val]) => {
                            if (typeof val === 'boolean') val = val ? 1 : 0;
                            formData.append(key, val);
                        });
                        
                        const resp = await fetch('../ajax/id_card_ajax.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await resp.json();
                        
                        this.showToast(data.success ? 'success' : 'error', data.message);
                    } catch (e) {
                        this.showToast('error', 'Failed to save settings');
                    }
                    this.saving = false;
                },
                
                async loadPreview() {
                    try {
                        const resp = await fetch('../ajax/id_card_ajax.php?action=preview_sample');
                        const data = await resp.json();
                        if (data.success) {
                            document.getElementById('preview-container').innerHTML = 
                                '<style>' + data.css + '</style>' +
                                '<div class="id-card-container">' + data.front + data.back + '</div>';
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },
                
                async previewEmployee(empId) {
                    this.previewEmployeeId = empId;
                    try {
                        const resp = await fetch('../ajax/id_card_ajax.php?action=preview&employee_id=' + empId);
                        const data = await resp.json();
                        if (data.success) {
                            this.previewFront = data.front;
                            this.previewBack = data.back;
                            this.previewCss = data.css;
                            this.previewModal = true;
                            
                            // Fetch verification URL
                            const urlResp = await fetch('../ajax/id_card_ajax.php?action=get_verification_url&employee_id=' + empId);
                            const urlData = await urlResp.json();
                            if (urlData.success) {
                                this.verificationUrl = urlData.url;
                            }
                        }
                    } catch (e) {
                        this.showToast('error', 'Failed to load preview');
                    }
                },
                
                getVerificationUrl() {
                    return this.verificationUrl || '#';
                },
                
                showToast(type, message) {
                    this.toast = { show: true, type, message };
                    setTimeout(() => this.toast.show = false, 3000);
                },
                
                init() {
                    lucide.createIcons();
                    this.$watch('previewModal', () => setTimeout(() => lucide.createIcons(), 100));
                    this.$watch('activeTab', () => setTimeout(() => lucide.createIcons(), 100));
                }
            };
        }
    </script>
</body>
</html>
