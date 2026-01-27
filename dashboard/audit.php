<?php
require_once '../includes/functions.php';
require_once '../includes/payroll_lock.php';
require_login();

$company_id = $_SESSION['company_id'];

// Filter Inputs
$search = $_GET['search'] ?? '';
$filter_user = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build Query
$where = "WHERE a.company_id = ?";
$params = [$company_id];

if (!empty($search)) {
    $where .= " AND (a.action LIKE ? OR a.details LIKE ? OR a.ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filter_user)) {
    $where .= " AND a.user_id = ?";
    $params[] = $filter_user;
}
if (!empty($date_from)) {
    $where .= " AND DATE(a.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where .= " AND DATE(a.created_at) <= ?";
    $params[] = $date_to;
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Count
$countSql = "SELECT COUNT(*) FROM audit_logs a $where";
$cStmt = $pdo->prepare($countSql);
$cStmt->execute($params);
$total_logs = $cStmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Fetch
$sql = "
    SELECT a.*, u.first_name, u.last_name, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    $where 
    ORDER BY a.created_at DESC 
    LIMIT $per_page OFFSET $offset
"; // Limit/offset are integers safe to interpolate here or bind. PDO limit limits binding sometimes. Integers are safe.

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Users for Filter
$users = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE company_id = ? ORDER BY first_name ASC");
$users->execute([$company_id]);
$all_users = $users->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - MiPayMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300">

    <?php include '../includes/dashboard_sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        <!-- Header -->
        <!-- Header -->
        <!-- Header -->
        <?php $page_title = 'Audit Trail'; include '../includes/dashboard_header.php'; ?>
        <!-- Admin Sub-Header -->
        <?php include '../includes/admin_header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            
            <!-- Filters -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-4 mb-6 shadow-sm">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Search Action / Details</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400"></i>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search..." class="w-full pl-9 rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm py-2">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">User</label>
                        <select name="user_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm py-2">
                            <option value="">All Users</option>
                            <?php foreach($all_users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Date Range</label>
                        <div class="flex gap-1">
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-1/2 rounded-lg border-slate-300 text-sm py-2 px-1">
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-1/2 rounded-lg border-slate-300 text-sm py-2 px-1">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium text-sm transition-colors">Apply Filters</button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-medium whitespace-nowrap">Timestamp</th>
                                <th class="px-6 py-3 font-medium whitespace-nowrap">User</th>
                                <th class="px-6 py-3 font-medium whitespace-nowrap">Action</th>
                                <th class="px-6 py-3 font-medium">Details</th>
                                <th class="px-6 py-3 font-medium whitespace-nowrap">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No audit logs found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-500">
                                        <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-900 dark:text-white">
                                        <?php 
                                            if ($log['first_name']) {
                                                echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); 
                                            } elseif ($log['user_id']) {
                                                // User ID exists but name is null (Deleted User)
                                                echo '<span class="text-red-400 italic">Deleted User (ID: ' . htmlspecialchars($log['user_id']) . ')</span>';
                                            } else {
                                                echo '<span class="text-slate-400">System / Guest</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs font-bold font-mono text-brand-600">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300 max-w-md truncate" title="<?php echo htmlspecialchars($log['details']); ?>">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-xs font-mono">
                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <p class="text-sm text-slate-500">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 text-sm border border-slate-200 rounded hover:bg-slate-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 text-sm border border-slate-200 rounded hover:bg-slate-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
