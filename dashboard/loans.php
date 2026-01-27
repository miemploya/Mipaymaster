<?php
require_once '../includes/functions.php';
require_once '../includes/payroll_lock.php';
require_login();

$company_id = $_SESSION['company_id'];

// Initial Fetch of Employees for Dropdown
$emps = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE company_id = ? AND employment_status='active' ORDER BY first_name ASC");
$emps->execute([$company_id]);
$all_employees = $emps->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans & Advances - MiPayMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Alpine Plugins -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <!-- Alpine Core -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' } }
                }
            }
        }
    </script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'); body { font-family: 'Inter', sans-serif; } [x-cloak] { display: none !important; }</style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" 
      x-data="loansApp()">

    <?php include '../includes/dashboard_sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        <!-- Header -->
        <?php $page_title = 'Loans & Advances'; include '../includes/dashboard_header.php'; ?>
        <!-- Payroll Sub-Header -->
        <?php include '../includes/payroll_header.php'; ?>
        
        <!-- Collapsed Toolbar (Expand Button) -->
        <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
            <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
            </button>
        </div>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900" x-data="loanModalData()">
            <!-- Tabs -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 dark:border-slate-800 mb-6 pb-2">
                <div class="flex gap-4">
                    <button @click="tab = 'pending'; fetchLoans()" :class="tab === 'pending' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">Pending Requests</button>
                    <button @click="tab = 'active'; fetchLoans()" :class="tab === 'active' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">Active Loans</button>
                    <button @click="tab = 'completed'; fetchLoans()" :class="tab === 'completed' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">History</button>
                </div>
                <!-- Action Button -->
                <button @click="openModal()" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-bold text-sm transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i> New Request
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                            <tr>
                                <th class="px-6 py-4 font-bold">Employee</th>
                                <th class="px-6 py-4 font-bold">Type</th>
                                <th class="px-6 py-4 font-bold text-right">Principal</th>
                                <th class="px-6 py-4 font-bold text-right">Balance</th>
                                <th class="px-6 py-4 font-bold">Start Period</th>
                                <th class="px-6 py-4 font-bold text-right">Repayment</th>
                                <th class="px-6 py-4 font-bold text-center">Status</th>
                                <th class="px-6 py-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="loan in loans" :key="loan.id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white" x-text="loan.first_name + ' ' + loan.last_name"></div>
                                        <div class="text-xs text-slate-400" x-text="'ID: ' + loan.employee_id"></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="capitalize text-slate-700 dark:text-slate-300" x-text="loan.loan_type === 'other' ? loan.custom_type : loan.loan_type.replace('_', ' ')"></div>
                                        <template x-if="loan.document_path">
                                            <a :href="'../uploads/loans/' + loan.document_path" target="_blank" class="text-xs text-brand-600 hover:underline flex items-center gap-1 mt-1"><i data-lucide="file-text" class="w-3 h-3"></i> View Doc</a>
                                        </template>
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400" x-text="formatCurrency(loan.principal_amount)"></td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-slate-900 dark:text-white" x-text="formatCurrency(loan.balance)"></td>
                                    <td class="px-6 py-4 text-slate-500" x-text="loan.start_month + '/' + loan.start_year"></td>
                                    <td class="px-6 py-4 text-right font-mono text-xs text-slate-500" x-text="formatCurrency(loan.repayment_amount) + '/mo'"></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 rounded text-xs font-bold capitalize"
                                              :class="{
                                                  'bg-yellow-50 text-yellow-700 border border-yellow-200': loan.status === 'pending',
                                                  'bg-green-50 text-green-700 border border-green-200': loan.status === 'approved',
                                                  'bg-red-50 text-red-700 border border-red-200': loan.status === 'rejected',
                                                  'bg-slate-100 text-slate-600 border border-slate-200': loan.status === 'completed'
                                              }" x-text="loan.status"></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2" x-show="loan.status === 'pending'">
                                            <button @click="approveLoan(loan.id)" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Approve"><i data-lucide="check" class="w-4 h-4"></i></button>
                                            <button @click="rejectLoan(loan.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Reject"><i data-lucide="x" class="w-4 h-4"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="loans.length === 0">
                                <td colspan="8" class="px-6 py-8 text-center text-slate-500">No loans found in this category.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Loan Modal -->
    <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div @click.outside="isModalOpen = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">New Loan Request</h3>
                <button @click="isModalOpen = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form @submit.prevent="submitLoan" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Employee</label>
                    <select x-model="form.employee_id" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                        <option value="">Select Employee</option>
                        <?php foreach($all_employees as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Loan Type</label>
                        <select x-model="form.loan_type" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            <option value="salary_advance">Salary Advance</option>
                            <option value="housing">Housing Loan</option>
                            <option value="car">Car Loan</option>
                            <option value="personal">Personal Loan</option>
                            <option value="medical">Medical Loan</option>
                            <option value="education">Education Loan</option>
                            <option value="emergency">Emergency Loan</option>
                            <option value="festival">Festival Advance</option>
                            <option value="wedding">Wedding Loan</option>
                            <option value="compassionate">Compassionate Loan</option>
                            <option value="furniture">Furniture/Appliance</option>
                            <option value="training">Training/Development</option>
                            <option value="travel">Travel Loan</option>
                            <option value="relocation">Relocation Loan</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div x-show="form.loan_type === 'other'">
                        <label class="block text-xs font-bold text-slate-500 mb-1">Specify Type</label>
                        <input type="text" x-model="form.custom_type" @input="form.custom_type = form.custom_type.charAt(0).toUpperCase() + form.custom_type.slice(1)" placeholder="e.g. Medical Emergency" :required="form.loan_type === 'other'" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Principal Amount</label>
                        <input type="number" x-model="form.principal_amount" min="1" step="0.01" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Monthly Repayment</label>
                        <input type="number" x-model="form.repayment_amount" min="1" step="0.01" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Interest Rate (%)</label>
                        <input type="number" x-model="form.interest_rate" min="0" step="0.01" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="0.00">
                    </div>
                    <div class="flex flex-col justify-end pb-2">
                        <div class="text-xs text-slate-500">Interest Amount: <span class="font-bold text-slate-700 dark:text-slate-300" x-text="formatCurrency(interestAmount)"></span></div>
                        <div class="text-xs text-slate-500">Total Payable: <span class="font-bold text-brand-600" x-text="formatCurrency(totalPayable)"></span></div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Start Month</label>
                        <select x-model="form.start_month" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Start Year</label>
                        <div x-data="{ open: false, years: Array.from({length: 101}, (_, i) => 2026 + i) }" class="relative">
                            <button type="button" @click="open = !open" @click.outside="open = false" class="w-full flex items-center justify-between rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm text-left">
                                <span x-text="form.start_year"></span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-500"></i>
                            </button>
                            <ul x-show="open" 
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute z-10 mt-1 w-full max-h-48 overflow-auto rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-lg text-sm">
                                <template x-for="year in years" :key="year">
                                    <li @click="form.start_year = year; open = false" 
                                        :class="form.start_year === year ? 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-400' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'"
                                        class="cursor-pointer px-4 py-2" x-text="year"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
                <div>
                   <label class="block text-xs font-bold text-slate-500 mb-1">Supporting Document (Optional)</label>
                   <input type="file" @change="handleFile" accept=".pdf,.jpg,.png,.jpeg" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                   <p class="text-xs text-slate-400 mt-1">PDF or Images, Max 5MB.</p>
                </div>

                <div class="pt-4 flex justify-end gap-2">
                    <button type="button" @click="isModalOpen = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-brand-600 text-white rounded-lg text-sm font-bold hover:bg-brand-700 shadow-md">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function loansApp() {
            return {
                tab: 'pending',
                loans: [],
                isModalOpen: false,
                form: {
                    employee_id: '',
                    loan_type: 'salary_advance',
                    custom_type: '',
                    principal_amount: '',
                    repayment_amount: '',
                    interest_rate: 0,
                    start_month: new Date().getMonth() + 1,
                    start_year: new Date().getFullYear(),
                    file: null
                },

                get interestAmount() {
                    const p = parseFloat(this.form.principal_amount) || 0;
                    const r = parseFloat(this.form.interest_rate) || 0;
                    return p * (r / 100);
                },

                get totalPayable() {
                    return (parseFloat(this.form.principal_amount) || 0) + this.interestAmount;
                },

                init() {
                    this.fetchLoans();
                    lucide.createIcons();
                },

                formatCurrency(val) {
                    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val);
                },

                handleFile(e) {
                    this.form.file = e.target.files[0];
                },

                openModal() {
                    this.isModalOpen = true;
                    // Reset form...
                },

                async fetchLoans() {
                    const fd = new FormData();
                    fd.append('action', 'fetch_loans');
                    fd.append('filter', this.tab);
                    
                    const res = await fetch('../ajax/loan_operations.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.status) {
                        this.loans = data.loans;
                        this.$nextTick(() => lucide.createIcons());
                    }
                },

                async submitLoan() {
                    // Frontend validation
                    if (!this.form.employee_id) {
                        alert('Please select an employee');
                        return;
                    }
                    
                    if (!this.form.principal_amount || parseFloat(this.form.principal_amount) <= 0) {
                        alert('Please enter a valid principal amount');
                        return;
                    }
                    
                    if (!this.form.repayment_amount || parseFloat(this.form.repayment_amount) <= 0) {
                        alert('Please enter a valid repayment amount');
                        return;
                    }
                    
                    const fd = new FormData();
                    fd.append('action', 'create_loan');
                    fd.append('employee_id', this.form.employee_id);
                    fd.append('loan_type', this.form.loan_type);
                    if (this.form.loan_type === 'other') fd.append('custom_type', this.form.custom_type);
                    fd.append('principal_amount', this.form.principal_amount);
                    fd.append('repayment_amount', this.form.repayment_amount);
                    fd.append('interest_rate', this.form.interest_rate);
                    fd.append('start_month', this.form.start_month);
                    fd.append('start_year', this.form.start_year);
                    if (this.form.file) fd.append('loan_doc', this.form.file);

                    console.log('Submitting Loan with data:', {
                        employee_id: this.form.employee_id,
                        loan_type: this.form.loan_type,
                        principal: this.form.principal_amount,
                        repayment: this.form.repayment_amount,
                        interest_rate: this.form.interest_rate,
                        start: `${this.form.start_month}/${this.form.start_year}`,
                        hasFile: !!this.form.file
                    });

                    try {
                        const res = await fetch('../ajax/loan_operations.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        
                        console.log('Server response:', data);
                        
                        if (data.status) {
                            alert('Loan Request Created Successfully! (ID: ' + data.loan_id + ')');
                            this.isModalOpen = false;
                            this.fetchLoans();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Submission error:', error);
                        alert('Network error. Please check loans_debug.log for details.');
                    }
                },

                async approveLoan(id) {
                    if (!confirm('Approve this loan?')) return;
                    const fd = new FormData();
                    fd.append('action', 'approve_loan');
                    fd.append('loan_id', id);
                    
                    const res = await fetch('../ajax/loan_operations.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.status) this.fetchLoans();
                    else alert(data.message);
                },

                async rejectLoan(id) {
                    if (!confirm('Reject this loan?')) return;
                    const fd = new FormData();
                    fd.append('action', 'reject_loan');
                    fd.append('loan_id', id);
                    
                    const res = await fetch('../ajax/loan_operations.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.status) this.fetchLoans();
                    else alert(data.message);
                }
            }
        }

    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
