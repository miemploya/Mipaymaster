<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'hr'; 
$current_tab = 'relations';

// Check role
$role = $_SESSION['role'];
if (!in_array($role, ['super_admin', 'company_admin', 'hr_manager'])) {
    header('Location: index.php');
    exit;
}

$company_id = $_SESSION['company_id'];

// Fetch departments and categories for compose feature
global $pdo;
$stmt = $pdo->prepare("SELECT id, name FROM departments WHERE company_id = ?");
$stmt->execute([$company_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, name FROM salary_categories WHERE company_id = ?");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, first_name, last_name, payroll_id FROM employees WHERE company_id = ? ORDER BY first_name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Relations - HR Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81' }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" 
      x-data="relationsPanel()" x-init="loadStats(); loadCases();">

    <?php include '../includes/dashboard_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        
        <?php $page_title = 'HR Management'; include '../includes/dashboard_header.php'; ?>

        <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-900 flex flex-col">
            <?php include '../includes/hr_header.php'; ?>

            <div class="p-6 lg:p-8 flex-1">
                
                <!-- DASHBOARD CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Open Cases</h3>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1" x-text="stats.open_cases">0</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <i data-lucide="folder-open" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Resolved Cases</h3>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1" x-text="stats.resolved_cases">0</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/20 flex items-center justify-center text-green-600 dark:text-green-400">
                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-slate-500 dark:text-slate-400">Awaiting Response</h3>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1" x-text="stats.pending_cases">0</p>
                        </div>
                        <div class="w-12 h-12 rounded-full bg-orange-100 dark:bg-orange-900/20 flex items-center justify-center text-orange-600 dark:text-orange-400">
                            <i data-lucide="clock" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

                <!-- TABS -->
                <div class="flex gap-4 mb-6 border-b border-slate-200 dark:border-slate-800">
                    <button @click="activeTab = 'cases'" :class="activeTab === 'cases' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="px-4 py-3 font-bold border-b-2 transition-colors">
                        <i data-lucide="folder" class="w-4 h-4 inline-block mr-1"></i> Cases
                    </button>
                    <button @click="activeTab = 'inbox'" :class="activeTab === 'inbox' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="px-4 py-3 font-bold border-b-2 transition-colors">
                        <i data-lucide="inbox" class="w-4 h-4 inline-block mr-1"></i> Inbox
                    </button>
                    <button @click="activeTab = 'compose'" :class="activeTab === 'compose' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="px-4 py-3 font-bold border-b-2 transition-colors">
                        <i data-lucide="send" class="w-4 h-4 inline-block mr-1"></i> Compose
                    </button>
                    <button @click="activeTab = 'manual'" :class="activeTab === 'manual' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="px-4 py-3 font-bold border-b-2 transition-colors">
                        <i data-lucide="file-plus" class="w-4 h-4 inline-block mr-1"></i> Manual Exception
                    </button>
                    <button @click="activeTab = 'archive'; loadArchive()" :class="activeTab === 'archive' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500'" class="px-4 py-3 font-bold border-b-2 transition-colors">
                        <i data-lucide="archive" class="w-4 h-4 inline-block mr-1"></i> Archive
                    </button>
                </div>

                <!-- CASES TAB -->
                <div x-show="activeTab === 'cases'">
                    <!-- Header + Filters + Actions -->
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Case Log</h3>
                        <div class="flex gap-2 items-center">
                            <select x-model="filterStatus" @change="loadCases()" class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm px-3 py-2">
                                <option value="">All Status</option>
                                <option value="open">Open</option>
                                <option value="in_review">In Review</option>
                                <option value="awaiting_response">Awaiting Response</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                            <select x-model="filterType" @change="loadCases()" class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm px-3 py-2">
                                <option value="">All Types</option>
                                <option value="complaint">Complaint</option>
                                <option value="grievance">Grievance</option>
                                <option value="report">Report</option>
                                <option value="inquiry">Inquiry</option>
                                <option value="feedback">Feedback</option>
                            </select>
                            <button @click="exportCasesCSV()" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                                <i data-lucide="download" class="w-3 h-3"></i> Export
                            </button>
                            <button @click="printCases()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                                <i data-lucide="printer" class="w-3 h-3"></i> Print
                            </button>
                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                            <template x-if="selectedCases.length > 0">
                                <button @click="deleteMultipleCasesWithReason()" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Delete (<span x-text="selectedCases.length"></span>)
                                </button>
                            </template>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Case Table -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-4 font-medium">
                                        <input type="checkbox" @change="selectedCases = $event.target.checked ? cases.map(c => c.id) : []" :checked="selectedCases.length === cases.length && cases.length > 0" class="rounded">
                                    </th>
                                    <th class="px-4 py-4 font-medium">Case #</th>
                                    <th class="px-4 py-4 font-medium">Employee</th>
                                    <th class="px-4 py-4 font-medium">Subject</th>
                                    <th class="px-4 py-4 font-medium">Type</th>
                                    <th class="px-4 py-4 font-medium">Priority</th>
                                    <th class="px-4 py-4 font-medium text-center">Status</th>
                                    <th class="px-4 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-if="cases.length === 0">
                                    <tr><td colspan="8" class="px-6 py-8 text-center text-slate-500">No cases found</td></tr>
                                </template>
                                <template x-for="c in cases.filter(c => !hiddenCases.includes(c.id))" :key="c.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <td class="px-4 py-4">
                                            <input type="checkbox" :value="c.id" x-model.number="selectedCases" class="rounded">
                                        </td>
                                        <td class="px-4 py-4 font-bold text-brand-600" x-text="c.case_number"></td>
                                        <td class="px-4 py-4 font-bold text-slate-900 dark:text-white" x-text="c.first_name + ' ' + c.last_name"></td>
                                        <td class="px-4 py-4 text-slate-600 dark:text-slate-400 max-w-xs truncate" x-text="c.subject"></td>
                                        <td class="px-4 py-4 capitalize" x-text="c.case_type"></td>
                                        <td class="px-4 py-4">
                                            <span :class="{'text-red-600': c.priority === 'urgent' || c.priority === 'high', 'text-yellow-600': c.priority === 'medium', 'text-slate-500': c.priority === 'low'}" class="font-bold text-xs uppercase" x-text="c.priority"></span>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="px-2 py-1 rounded-full text-xs font-bold capitalize"
                                                  :class="{'bg-yellow-100 text-yellow-700': c.status === 'open', 'bg-blue-100 text-blue-700': c.status === 'in_review', 'bg-orange-100 text-orange-700': c.status === 'awaiting_response', 'bg-green-100 text-green-700': c.status === 'resolved', 'bg-slate-100 text-slate-700': c.status === 'closed'}"
                                                  x-text="c.status.replace('_', ' ')"></span>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <button @click="openCase(c.id)" class="text-blue-600 hover:text-blue-800 mr-1" title="View">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            <button @click="hideCase(c.id)" class="text-yellow-600 hover:text-yellow-800 mr-1" title="Hide">
                                                <i data-lucide="eye-off" class="w-4 h-4"></i>
                                            </button>
                                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                            <button @click="deleteCaseWithReason(c.id)" class="text-red-600 hover:text-red-800" title="Delete">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <!-- Show hidden count -->
                        <template x-if="hiddenCases.length > 0">
                            <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 flex justify-between">
                                <span x-text="hiddenCases.length + ' case(s) hidden'"></span>
                                <button @click="hiddenCases = []" class="text-brand-600 hover:underline">Show All</button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- INBOX TAB -->
                <div x-show="activeTab === 'inbox'">
                    <div class="grid grid-cols-3 gap-6">
                        <!-- Case List -->
                        <div class="col-span-1 bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 space-y-2">
                                <h4 class="font-bold text-slate-900 dark:text-white">Messages</h4>
                                <input type="text" x-model="inboxSearch" placeholder="Search by staff name..." class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm">
                                <div class="grid grid-cols-2 gap-2">
                                    <select x-model="inboxStatus" class="rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-xs">
                                        <option value="">All Status</option>
                                        <option value="open">Open</option>
                                        <option value="in_review">In Review</option>
                                        <option value="awaiting_response">Awaiting</option>
                                    </select>
                                    <input type="date" x-model="inboxDateFrom" class="rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-xs" title="From Date">
                                </div>
                                <input type="date" x-model="inboxDateTo" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-xs" title="To Date">
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <template x-for="c in cases.filter(c => {
                                    if (inboxSearch && !(c.first_name + ' ' + c.last_name).toLowerCase().includes(inboxSearch.toLowerCase())) return false;
                                    if (inboxStatus && c.status !== inboxStatus) return false;
                                    if (inboxDateFrom && new Date(c.created_at) < new Date(inboxDateFrom)) return false;
                                    if (inboxDateTo && new Date(c.created_at) > new Date(inboxDateTo + 'T23:59:59')) return false;
                                    return true;
                                })" :key="c.id">
                                    <div @click="openCase(c.id)" class="p-4 border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-900 cursor-pointer transition-colors" :class="selectedCase?.id === c.id ? 'bg-brand-50 dark:bg-brand-900/20' : ''">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="font-bold text-slate-900 dark:text-white text-sm" x-text="c.first_name + ' ' + c.last_name"></span>
                                            <span class="text-xs text-slate-500" x-text="new Date(c.created_at).toLocaleDateString()"></span>
                                        </div>
                                        <p class="text-sm text-slate-600 dark:text-slate-400 truncate" x-text="c.subject"></p>
                                        <span class="text-xs capitalize px-1.5 py-0.5 rounded mt-1 inline-block"
                                              :class="{'bg-yellow-100 text-yellow-700': c.status === 'open', 'bg-blue-100 text-blue-700': c.status === 'in_review', 'bg-orange-100 text-orange-700': c.status === 'awaiting_response', 'bg-green-100 text-green-700': c.status === 'resolved'}"
                                              x-text="c.status.replace('_', ' ')"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Message Thread -->
                        <div class="col-span-2 bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col">
                            <template x-if="!selectedCase">
                                <div class="flex-1 flex items-center justify-center text-slate-500">
                                    <div class="text-center">
                                        <i data-lucide="mail" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                                        <p>Select a case to view messages</p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedCase">
                                <div class="flex flex-col h-full">
                                    <!-- Header -->
                                    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-between items-center">
                                        <div>
                                            <h4 class="font-bold text-slate-900 dark:text-white" x-text="selectedCase.subject"></h4>
                                            <p class="text-xs text-slate-500" x-text="selectedCase.first_name + ' ' + selectedCase.last_name + ' • ' + selectedCase.case_number"></p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <select x-model="selectedCase.status" @change="updateCaseStatus()" class="text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-1">
                                                <option value="open">Open</option>
                                                <option value="in_review">In Review</option>
                                                <option value="awaiting_response">Awaiting Response</option>
                                                <option value="resolved">Resolved</option>
                                                <option value="closed">Closed</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Original Message -->
                                    <div class="p-4 bg-slate-50 dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800">
                                        <p class="text-xs font-bold text-slate-500 mb-1">Original Message:</p>
                                        <p class="text-sm text-slate-700 dark:text-slate-300" x-text="selectedCase.description"></p>
                                    </div>

                                    <!-- Messages -->
                                    <div class="flex-1 overflow-y-auto p-4 space-y-3 max-h-64">
                                        <template x-for="msg in caseMessages" :key="msg.id">
                                            <div :class="msg.sender_role === 'employee' ? 'mr-12 bg-slate-100 dark:bg-slate-800' : 'ml-12 bg-brand-50 dark:bg-brand-900/20'" class="p-3 rounded-lg">
                                                <div class="flex justify-between items-center mb-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-bold" :class="msg.sender_role === 'employee' ? 'text-slate-700' : 'text-brand-600'" x-text="msg.sender_role === 'employee' ? 'Employee' : 'HR Team'"></span>
                                                        <template x-if="msg.message_title">
                                                            <span class="text-xs bg-brand-100 text-brand-700 px-2 py-0.5 rounded font-bold" x-text="msg.message_title"></span>
                                                        </template>
                                                    </div>
                                                    <span class="text-xs text-slate-500" x-text="new Date(msg.created_at).toLocaleString()"></span>
                                                </div>
                                                <p class="text-sm text-slate-700 dark:text-slate-300" x-text="msg.message"></p>
                                                <template x-if="msg.attachment_path">
                                                    <a :href="'../' + msg.attachment_path" download :title="msg.attachment_name" 
                                                       class="inline-flex items-center gap-1 mt-2 text-xs text-brand-600 hover:underline bg-brand-50 px-2 py-1 rounded">
                                                        <i data-lucide="paperclip" class="w-3 h-3"></i>
                                                        <span x-text="msg.attachment_name || 'Download Attachment'"></span>
                                                    </a>
                                                </template>
                                                <template x-if="msg.is_internal == 1">
                                                    <span class="text-xs bg-yellow-100 text-yellow-700 px-1 rounded mt-1 inline-block ml-2">Internal Note</span>
                                                </template>
                                                <!-- Acknowledgement status for HR messages acknowledged by staff -->
                                                <template x-if="msg.sender_role === 'hr' && msg.acknowledged_at">
                                                    <span class="inline-flex items-center gap-1 text-xs text-green-600 bg-green-50 px-2 py-1 rounded font-bold mt-2">
                                                        <i data-lucide="check-circle" class="w-3 h-3"></i>
                                                        Acknowledged
                                                    </span>
                                                </template>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Reply Form -->
                                    <div class="p-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                                        <div class="grid grid-cols-3 gap-2">
                                            <select x-model="replyTitle" class="rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                                                <option value="">No Title</option>
                                                <option value="Memo">Memo</option>
                                                <option value="Query">Query</option>
                                                <option value="Policy">Policy</option>
                                                <option value="Warning">Warning</option>
                                                <option value="Request">Request</option>
                                                <option value="Disciplinary">Disciplinary</option>
                                                <option value="Promotion">Promotion</option>
                                                <option value="Others">Others</option>
                                            </select>
                                            <input type="text" x-model="replyTitleCustom" class="col-span-2 rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="Add context (e.g., 'Attendance Issue')">
                                        </div>
                                        <input type="text" x-model="replyMessage" @keydown.enter="replyCase()" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="Type your reply...">
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <div class="flex-1">
                                                <input type="file" id="inbox-attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100">
                                                <span class="text-xs text-slate-400">Max 2MB</span>
                                            </div>
                                            <label class="flex items-center gap-1 text-xs text-slate-500">
                                                <input type="checkbox" x-model="replyInternal" class="rounded"> Internal
                                            </label>
                                            <button @click="replyCase()" class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700">
                                                <i data-lucide="send" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- COMPOSE TAB -->
                <div x-show="activeTab === 'compose'">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 max-w-2xl">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Send Message to Employees</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-500 mb-2">Send To</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" x-model="composeForm.target_type" value="individual" class="text-brand-600"> Individual
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" x-model="composeForm.target_type" value="department" class="text-brand-600"> Department
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" x-model="composeForm.target_type" value="category" class="text-brand-600"> Category
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" x-model="composeForm.target_type" value="all" class="text-brand-600"> All Staff
                                    </label>
                                </div>
                            </div>

                            <div x-show="composeForm.target_type === 'individual'">
                                <label class="block text-sm font-bold text-slate-500 mb-2">Select Employee</label>
                                <select x-model="composeForm.employee_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($employees as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['payroll_id'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div x-show="composeForm.target_type === 'department'">
                                <label class="block text-sm font-bold text-slate-500 mb-2">Select Department</label>
                                <select x-model="composeForm.department_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div x-show="composeForm.target_type === 'category'">
                                <label class="block text-sm font-bold text-slate-500 mb-2">Select Category</label>
                                <select x-model="composeForm.category_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-500 mb-2">Message Type</label>
                                    <select x-model="composeForm.message_title" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                        <option value="">-- Select Type --</option>
                                        <option value="Memo">Memo</option>
                                        <option value="Query">Query</option>
                                        <option value="Policy">Policy</option>
                                        <option value="Warning">Warning</option>
                                        <option value="Request">Request</option>
                                        <option value="Disciplinary">Disciplinary</option>
                                        <option value="Promotion">Promotion</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-500 mb-2">Add Context</label>
                                    <input type="text" x-model="composeForm.message_title_custom" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2" placeholder="e.g., 'General Announcement'">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-500 mb-2">Subject or Title</label>
                                <input type="text" x-model="composeForm.subject" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2" placeholder="Message subject">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-500 mb-2">Message</label>
                                <textarea x-model="composeForm.message" rows="5" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2" placeholder="Write your message..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-500 mb-2">Attachment</label>
                                <input type="file" id="compose-attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                                <p class="text-xs text-slate-400 mt-1"><i data-lucide="info" class="w-3 h-3 inline-block"></i> Max 2MB. Formats: PDF, JPG, PNG only. Images will be compressed.</p>
                            </div>

                            <div class="flex justify-end">
                                <button @click="sendMessage()" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 flex items-center gap-2">
                                    <i data-lucide="send" class="w-4 h-4"></i> Send Message
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MANUAL EXCEPTION TAB -->
                <div x-show="activeTab === 'manual'">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Manual Exception Entry</h3>
                            <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                                <p class="text-sm text-amber-800 dark:text-amber-200 flex items-start gap-2">
                                    <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                                    <span><strong>How to use:</strong> Use this section for companies not using the Staff Portal. HR can manually file cases, complaints, grievances, or queries on behalf of employees. These cases will be managed entirely by HR without employee portal access. Select an employee, describe the issue, and track resolution internally.</span>
                                </p>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-500 mb-2">Select Employee *</label>
                                    <select x-model="manualForm.employee_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                        <option value="">-- Select Employee --</option>
                                        <?php foreach ($employees as $e): ?>
                                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['payroll_id'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-500 mb-2">Case Type *</label>
                                    <select x-model="manualForm.case_type" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                        <option value="complaint">Complaint</option>
                                        <option value="grievance">Grievance</option>
                                        <option value="misconduct">Misconduct Report</option>
                                        <option value="query">Query</option>
                                        <option value="warning">Verbal/Written Warning</option>
                                        <option value="inquiry">Inquiry</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-500 mb-2">Priority</label>
                                    <select x-model="manualForm.priority" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-500 mb-2">Subject *</label>
                                    <input type="text" x-model="manualForm.subject" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2" placeholder="e.g., Late Arrival - 3 Instances">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-500 mb-2">Description / Details *</label>
                                <textarea x-model="manualForm.description" rows="4" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2" placeholder="Provide detailed information about the case, incident, or issue..."></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-500 mb-2">Attachment (Optional)</label>
                                <input type="file" id="manual-attachment" accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                                <p class="text-xs text-slate-400 mt-1"><i data-lucide="info" class="w-3 h-3 inline-block"></i> Max 2MB. Formats: PDF, JPG, PNG only.</p>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button @click="manualForm = { employee_id: '', case_type: 'complaint', subject: '', description: '', priority: 'medium' }" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg font-medium">Clear</button>
                                <button @click="createManualCase()" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 flex items-center gap-2">
                                    <i data-lucide="file-plus" class="w-4 h-4"></i> Create Case
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ARCHIVE TAB -->
                <div x-show="activeTab === 'archive'">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="archive" class="w-5 h-5 text-brand-600"></i> Archived Cases
                                </h3>
                                <p class="text-sm text-slate-500 mt-1">Closed and resolved cases are automatically archived here.</p>
                            </div>
                            <div class="flex gap-2 items-center">
                                <button @click="exportArchiveCSV()" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                                    <i data-lucide="download" class="w-3 h-3"></i> Export
                                </button>
                                <button @click="printArchive()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                                    <i data-lucide="printer" class="w-3 h-3"></i> Print
                                </button>
                                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                <template x-if="selectedArchive.length > 0 && archiveExported">
                                    <button @click="deleteMultipleCases()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-bold hover:bg-red-700 flex items-center gap-2">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete Selected (<span x-text="selectedArchive.length"></span>)
                                    </button>
                                </template>
                                <template x-if="selectedArchive.length > 0 && !archiveExported">
                                    <span class="text-xs text-amber-600 bg-amber-50 px-3 py-2 rounded-lg font-medium">
                                        <i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> Export required before delete
                                    </span>
                                </template>
                                <?php endif; ?>
                        </div>
                        
                        <template x-if="archivedCases.length === 0">
                            <div class="p-8 text-center text-slate-400">
                                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-4 opacity-50"></i>
                                <p class="text-lg font-medium">No Archived Cases</p>
                                <p class="text-sm">Closed cases will appear here.</p>
                            </div>
                        </template>

                        <template x-if="archivedCases.length > 0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50 dark:bg-slate-900 text-left text-slate-500 text-xs uppercase">
                                        <tr>
                                            <th class="px-4 py-3 font-medium">
                                                <input type="checkbox" @change="selectedArchive = $event.target.checked ? archivedCases.map(c => c.id) : []" :checked="selectedArchive.length === archivedCases.length && archivedCases.length > 0" class="rounded">
                                            </th>
                                            <th class="px-4 py-3 font-medium">Case #</th>
                                            <th class="px-4 py-3 font-medium">Employee</th>
                                            <th class="px-4 py-3 font-medium">Subject</th>
                                            <th class="px-4 py-3 font-medium">Type</th>
                                            <th class="px-4 py-3 font-medium">Status</th>
                                            <th class="px-4 py-3 font-medium">Closed</th>
                                            <th class="px-4 py-3 font-medium text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <template x-for="c in archivedCases" :key="c.id">
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                                <td class="px-4 py-3">
                                                    <input type="checkbox" :value="c.id" x-model.number="selectedArchive" class="rounded">
                                                </td>
                                                <td class="px-4 py-3 font-mono text-xs text-brand-600" x-text="c.case_number"></td>
                                                <td class="px-4 py-3 font-medium" x-text="c.first_name + ' ' + c.last_name"></td>
                                                <td class="px-4 py-3" x-text="c.subject"></td>
                                                <td class="px-4 py-3 capitalize" x-text="c.case_type"></td>
                                                <td class="px-4 py-3">
                                                    <span class="px-2 py-1 rounded text-xs font-bold capitalize"
                                                          :class="c.status === 'closed' ? 'bg-slate-100 text-slate-600' : 'bg-green-100 text-green-700'"
                                                          x-text="c.status"></span>
                                                </td>
                                                <td class="px-4 py-3 text-slate-500" x-text="new Date(c.updated_at).toLocaleDateString()"></td>
                                                <td class="px-4 py-3 text-center">
                                                    <button @click="viewArchivedCase(c.id)" class="text-blue-600 hover:text-blue-800 font-medium text-xs mr-2">
                                                        <i data-lucide="eye" class="w-4 h-4 inline-block"></i> View
                                                    </button>
                                                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                                    <button @click="deleteCase(c.id)" x-show="archiveExported" class="text-red-600 hover:text-red-800 font-medium text-xs">
                                                        <i data-lucide="trash-2" class="w-4 h-4 inline-block"></i> Delete
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Archive Case View Modal -->
                <div x-show="archiveModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                    <div class="flex items-center justify-center min-h-screen px-4">
                        <div class="fixed inset-0 bg-black/50" @click="archiveModalOpen = false"></div>
                        <div class="relative bg-white dark:bg-slate-950 rounded-xl shadow-xl max-w-3xl w-full max-h-[80vh] overflow-hidden">
                            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white" x-text="'Case: ' + (archiveViewCase?.case_number || '')"></h3>
                                <button @click="archiveModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <div class="p-4 overflow-y-auto max-h-[60vh]">
                                <div class="mb-4 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                                    <p class="text-xs font-bold text-slate-500 mb-1">Subject</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white" x-text="archiveViewCase?.subject"></p>
                                </div>
                                <div class="mb-4 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                                    <p class="text-xs font-bold text-slate-500 mb-1">Original Message</p>
                                    <p class="text-sm text-slate-700 dark:text-slate-300" x-text="archiveViewCase?.description"></p>
                                </div>
                                <div class="space-y-3">
                                    <template x-for="msg in archiveMessages" :key="msg.id">
                                        <div :class="msg.sender_role === 'employee' ? 'mr-8 bg-slate-100 dark:bg-slate-800' : 'ml-8 bg-brand-50 dark:bg-brand-900/20'" class="p-3 rounded-lg">
                                            <div class="flex justify-between items-center mb-1">
                                                <span class="text-xs font-bold" x-text="msg.sender_role === 'employee' ? 'Employee' : 'HR Team'"></span>
                                                <span class="text-xs text-slate-500" x-text="new Date(msg.created_at).toLocaleString()"></span>
                                            </div>
                                            <p class="text-sm text-slate-700 dark:text-slate-300" x-text="msg.message"></p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- CASE DETAIL MODAL -->
    <div x-show="caseModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="caseModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white" x-text="selectedCase?.subject"></h3>
                    <p class="text-xs text-slate-500" x-text="selectedCase?.case_number + ' • ' + selectedCase?.first_name + ' ' + selectedCase?.last_name"></p>
                </div>
                <button @click="caseModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto">
                <div class="mb-4 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                    <p class="text-xs font-bold text-slate-500 mb-1">Original Message</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300" x-text="selectedCase?.description"></p>
                </div>
                <div class="space-y-3">
                    <template x-for="msg in caseMessages" :key="msg.id">
                        <div :class="msg.sender_role === 'employee' ? 'mr-8 bg-slate-100 dark:bg-slate-800' : 'ml-8 bg-brand-50 dark:bg-brand-900/20'" class="p-3 rounded-lg">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-bold" x-text="msg.sender_role === 'employee' ? 'Employee' : 'HR Team'"></span>
                                <span class="text-xs text-slate-500" x-text="new Date(msg.created_at).toLocaleString()"></span>
                            </div>
                            <p class="text-sm text-slate-700 dark:text-slate-300" x-text="msg.message"></p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function relationsPanel() {
            return {
                activeTab: 'cases',
                stats: { open_cases: 0, resolved_cases: 0, pending_cases: 0 },
                cases: [],
                filterStatus: '',
                filterType: '',
                inboxSearch: '',
                inboxStatus: '',
                inboxDateFrom: '',
                inboxDateTo: '',
                selectedArchive: [],
                archiveExported: false,
                selectedCases: [],
                hiddenCases: [],
                selectedCase: null,
                caseMessages: [],
                caseModalOpen: false,
                replyMessage: '',
                replyTitle: '',
                replyTitleCustom: '',
                replyInternal: false,
                composeForm: {
                    target_type: 'individual',
                    employee_id: '',
                    department_id: '',
                    category_id: '',
                    message_title: '',
                    message_title_custom: '',
                    subject: '',
                    message: ''
                },
                manualForm: {
                    employee_id: '',
                    case_type: 'complaint',
                    subject: '',
                    description: '',
                    priority: 'medium'
                },
                archivedCases: [],
                archiveModalOpen: false,
                archiveViewCase: null,
                archiveMessages: [],

                async loadStats() {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_stats');
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) this.stats = data.stats;
                    } catch (e) { console.error(e); }
                },

                async loadCases() {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_cases');
                        fd.append('status', this.filterStatus);
                        fd.append('type', this.filterType);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) this.cases = data.cases;
                    } catch (e) { console.error(e); }
                },

                async openCase(caseId) {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_case_detail');
                        fd.append('case_id', caseId);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.selectedCase = data.case;
                            this.caseMessages = data.messages;
                            this.replyMessage = '';
                            if (this.activeTab === 'cases') this.caseModalOpen = true;
                            // Recreate icons after DOM updates
                            setTimeout(() => lucide.createIcons(), 100);
                        }
                    } catch (e) { console.error(e); }
                },

                async updateCaseStatus() {
                    if (!this.selectedCase) return;
                    try {
                        const fd = new FormData();
                        fd.append('action', 'update_case_status');
                        fd.append('case_id', this.selectedCase.id);
                        fd.append('status', this.selectedCase.status);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.loadCases();
                            this.loadStats();
                        }
                    } catch (e) { console.error(e); }
                },

                async replyCase() {
                    if (!this.replyMessage.trim() || !this.selectedCase) return;
                    try {
                        const fd = new FormData();
                        fd.append('action', 'reply_case');
                        fd.append('case_id', this.selectedCase.id);
                        // Combine title and custom text
                        const fullTitle = this.replyTitle + (this.replyTitleCustom.trim() ? ': ' + this.replyTitleCustom.trim() : '');
                        fd.append('message_title', fullTitle || '');
                        fd.append('message', this.replyMessage);
                        fd.append('is_internal', this.replyInternal ? 1 : 0);
                        
                        // Handle attachment
                        const fileInput = document.getElementById('inbox-attachment');
                        if (fileInput && fileInput.files[0]) {
                            const file = fileInput.files[0];
                            if (file.size > 2 * 1024 * 1024) {
                                alert('File too large. Maximum 2MB allowed.');
                                return;
                            }
                            fd.append('attachment', file);
                        }
                        
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.replyMessage = '';
                            this.replyTitle = '';
                            this.replyTitleCustom = '';
                            this.replyInternal = false;
                            if (fileInput) fileInput.value = '';
                            this.openCase(this.selectedCase.id);
                            this.loadCases();
                        }
                    } catch (e) { console.error(e); }
                },

                async sendMessage() {
                    if (!this.composeForm.subject.trim() || !this.composeForm.message.trim()) {
                        alert('Please fill in subject and message.');
                        return;
                    }
                    try {
                        const fd = new FormData();
                        fd.append('action', 'send_message');
                        fd.append('target_type', this.composeForm.target_type);
                        fd.append('employee_id', this.composeForm.employee_id);
                        fd.append('department_id', this.composeForm.department_id);
                        fd.append('category_id', this.composeForm.category_id);
                        // Combine title and custom text
                        const fullTitle = this.composeForm.message_title + (this.composeForm.message_title_custom.trim() ? ': ' + this.composeForm.message_title_custom.trim() : '');
                        fd.append('message_title', fullTitle || '');
                        fd.append('subject', this.composeForm.subject);
                        fd.append('message', this.composeForm.message);
                        
                        // Handle attachment
                        const fileInput = document.getElementById('compose-attachment');
                        if (fileInput && fileInput.files[0]) {
                            const file = fileInput.files[0];
                            if (file.size > 2 * 1024 * 1024) {
                                alert('File too large. Maximum 2MB allowed.');
                                return;
                            }
                            fd.append('attachment', file);
                        }
                        
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.composeForm = { target_type: 'individual', employee_id: '', department_id: '', category_id: '', message_title: '', message_title_custom: '', subject: '', message: '' };
                            if (fileInput) fileInput.value = '';
                            this.loadCases();
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async createManualCase() {
                    if (!this.manualForm.employee_id || !this.manualForm.subject.trim() || !this.manualForm.description.trim()) {
                        alert('Please select an employee and fill in subject and description.');
                        return;
                    }
                    try {
                        const fd = new FormData();
                        fd.append('action', 'create_manual_case');
                        fd.append('employee_id', this.manualForm.employee_id);
                        fd.append('case_type', this.manualForm.case_type);
                        fd.append('subject', this.manualForm.subject);
                        fd.append('description', this.manualForm.description);
                        fd.append('priority', this.manualForm.priority);
                        
                        // Handle attachment
                        const fileInput = document.getElementById('manual-attachment');
                        if (fileInput && fileInput.files[0]) {
                            const file = fileInput.files[0];
                            if (file.size > 2 * 1024 * 1024) {
                                alert('File too large. Maximum 2MB allowed.');
                                return;
                            }
                            fd.append('attachment', file);
                        }
                        
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.manualForm = { employee_id: '', case_type: 'complaint', subject: '', description: '', priority: 'medium' };
                            if (fileInput) fileInput.value = '';
                            this.loadCases();
                            this.loadStats();
                            this.activeTab = 'cases';
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async loadArchive() {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_archived_cases');
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.archivedCases = data.cases;
                            setTimeout(() => lucide.createIcons(), 100);
                        }
                    } catch (e) { console.error(e); }
                },

                async deleteCase(caseId) {
                    if (!confirm('Are you sure you want to permanently delete this case? This action cannot be undone.')) return;
                    try {
                        const fd = new FormData();
                        fd.append('action', 'delete_case');
                        fd.append('case_id', caseId);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.loadArchive();
                            this.loadStats();
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async viewArchivedCase(caseId) {
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_case_detail');
                        fd.append('case_id', caseId);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.archiveViewCase = data.case;
                            this.archiveMessages = data.messages;
                            this.archiveModalOpen = true;
                            setTimeout(() => lucide.createIcons(), 100);
                        } else {
                            alert(data.message);
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                async deleteMultipleCases() {
                    if (!this.selectedArchive.length) return;
                    if (!confirm(`Are you sure you want to permanently delete ${this.selectedArchive.length} case(s)? This action cannot be undone.`)) return;
                    try {
                        const fd = new FormData();
                        fd.append('action', 'delete_multiple_cases');
                        fd.append('case_ids', JSON.stringify(this.selectedArchive));
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.selectedArchive = [];
                            this.loadArchive();
                            this.loadStats();
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                // Archive Export CSV (enables delete)
                exportArchiveCSV() {
                    if (this.archivedCases.length === 0) {
                        alert('No archived cases to export.');
                        return;
                    }
                    
                    const headers = ['Case #', 'Employee', 'Subject', 'Type', 'Status', 'Closed Date'];
                    const rows = this.archivedCases.map(c => [
                        c.case_number,
                        `${c.first_name} ${c.last_name}`,
                        `"${(c.subject || '').replace(/"/g, '""')}"`,
                        c.case_type,
                        c.status,
                        c.updated_at
                    ]);
                    
                    let csv = headers.join(',') + '\n';
                    rows.forEach(r => csv += r.join(',') + '\n');
                    
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `archived_cases_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                    
                    // Enable delete after export
                    this.archiveExported = true;
                },

                // Archive Print
                printArchive() {
                    if (this.archivedCases.length === 0) {
                        alert('No archived cases to print.');
                        return;
                    }
                    
                    let html = `
                        <html>
                        <head>
                            <title>Archived Cases Report</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                h1 { color: #333; font-size: 24px; margin-bottom: 5px; }
                                h3 { color: #666; font-size: 14px; margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background: #f5f5f5; font-weight: bold; }
                            </style>
                        </head>
                        <body>
                            <h1>Archived Cases</h1>
                            <h3>Generated: ${new Date().toLocaleString()}</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Case #</th>
                                        <th>Employee</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Closed Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    this.archivedCases.forEach(c => {
                        html += `
                            <tr>
                                <td>${c.case_number}</td>
                                <td>${c.first_name} ${c.last_name}</td>
                                <td>${c.subject}</td>
                                <td style="text-transform: capitalize">${c.case_type}</td>
                                <td style="text-transform: capitalize">${c.status}</td>
                                <td>${new Date(c.updated_at).toLocaleDateString()}</td>
                            </tr>
                        `;
                    });
                    
                    html += `</tbody></table></body></html>`;
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(html);
                    printWindow.document.close();
                    printWindow.print();
                },

                // Cases Export CSV
                exportCasesCSV() {
                    const visibleCases = this.cases.filter(c => !this.hiddenCases.includes(c.id));
                    if (visibleCases.length === 0) {
                        alert('No cases to export.');
                        return;
                    }
                    
                    const headers = ['Case #', 'Employee', 'Subject', 'Type', 'Priority', 'Status', 'Created'];
                    const rows = visibleCases.map(c => [
                        c.case_number,
                        `${c.first_name} ${c.last_name}`,
                        `"${(c.subject || '').replace(/"/g, '""')}"`,
                        c.case_type,
                        c.priority,
                        c.status,
                        c.created_at
                    ]);
                    
                    let csv = headers.join(',') + '\n';
                    rows.forEach(r => csv += r.join(',') + '\n');
                    
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `cases_export_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                },

                // Cases Print
                printCases() {
                    const visibleCases = this.cases.filter(c => !this.hiddenCases.includes(c.id));
                    if (visibleCases.length === 0) {
                        alert('No cases to print.');
                        return;
                    }
                    
                    let html = `
                        <html>
                        <head>
                            <title>Cases Report</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                h1 { color: #333; font-size: 24px; margin-bottom: 5px; }
                                h3 { color: #666; font-size: 14px; margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background: #f5f5f5; font-weight: bold; }
                                .priority-urgent, .priority-high { color: #dc2626; font-weight: bold; }
                                .priority-medium { color: #d97706; }
                                .priority-low { color: #6b7280; }
                            </style>
                        </head>
                        <body>
                            <h1>Employee Relations Cases</h1>
                            <h3>Generated: ${new Date().toLocaleString()}</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Case #</th>
                                        <th>Employee</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    visibleCases.forEach(c => {
                        html += `
                            <tr>
                                <td>${c.case_number}</td>
                                <td>${c.first_name} ${c.last_name}</td>
                                <td>${c.subject}</td>
                                <td style="text-transform: capitalize">${c.case_type}</td>
                                <td class="priority-${c.priority}" style="text-transform: uppercase">${c.priority}</td>
                                <td style="text-transform: capitalize">${c.status.replace('_', ' ')}</td>
                            </tr>
                        `;
                    });
                    
                    html += `</tbody></table></body></html>`;
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(html);
                    printWindow.document.close();
                    printWindow.print();
                },

                // Hide case (temporary)
                hideCase(caseId) {
                    if (!this.hiddenCases.includes(caseId)) {
                        this.hiddenCases.push(caseId);
                    }
                },

                // Delete single case with reason
                async deleteCaseWithReason(caseId) {
                    const reason = prompt('Please provide a reason for deleting this case:');
                    if (!reason || reason.trim() === '') {
                        alert('Deletion reason is required.');
                        return;
                    }
                    
                    if (!confirm('Are you sure you want to permanently delete this case?')) return;
                    
                    try {
                        const fd = new FormData();
                        fd.append('action', 'delete_case_with_reason');
                        fd.append('case_id', caseId);
                        fd.append('reason', reason);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.loadCases();
                            this.loadStats();
                        }
                    } catch (e) { alert('Error: ' + e); }
                },

                // Delete multiple cases with reason
                async deleteMultipleCasesWithReason() {
                    if (!this.selectedCases.length) return;
                    
                    const reason = prompt(`Please provide a reason for deleting ${this.selectedCases.length} case(s):`);
                    if (!reason || reason.trim() === '') {
                        alert('Deletion reason is required.');
                        return;
                    }
                    
                    if (!confirm(`Are you sure you want to permanently delete ${this.selectedCases.length} case(s)?`)) return;
                    
                    try {
                        const fd = new FormData();
                        fd.append('action', 'delete_cases_with_reason');
                        fd.append('case_ids', JSON.stringify(this.selectedCases));
                        fd.append('reason', reason);
                        const res = await fetch('../ajax/employee_relations_ajax.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if (data.status) {
                            this.selectedCases = [];
                            this.loadCases();
                            this.loadStats();
                        }
                    } catch (e) { alert('Error: ' + e); }
                }
            }
        }
    </script>

    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
