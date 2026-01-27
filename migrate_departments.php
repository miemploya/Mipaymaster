<?php
// migrate_departments.php
// Purpose: One-time migration to link employees with text-based 'department' column to 'department_id' foreign key.

require_once 'includes/functions.php';

// Disable time limit for migration
set_time_limit(0);

echo "Starting Department Migration...\n";
echo "--------------------------------\n";

// 1. Get all companies
$stmt = $pdo->query("SELECT id, name FROM companies");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($companies as $company) {
    $cid = $company['id'];
    echo "Processing Company: {$company['name']} (ID: $cid)\n";

    // 2. Fetch employees with missing department_id but having department text
    $stmt_emp = $pdo->prepare("SELECT id, first_name, last_name, department FROM employees WHERE company_id = ? AND (department_id IS NULL OR department_id = 0) AND department IS NOT NULL AND department != ''");
    $stmt_emp->execute([$cid]);
    $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

    if (count($employees) === 0) {
        echo "  - No employees need migration.\n";
        continue;
    }

    echo "  - Found " . count($employees) . " employees to check.\n";

    // 3. Pre-fetch dictionary of existing departments for this company
    // Using lowercase for case-insensitive matching
    $stmt_dept = $pdo->prepare("SELECT id, name FROM departments WHERE company_id = ?");
    $stmt_dept->execute([$cid]);
    $dept_rows = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);
    
    $dept_map = []; // name_lower => id
    foreach ($dept_rows as $dr) {
        $dept_map[strtolower(trim($dr['name']))] = $dr['id'];
    }

    $updated_count = 0;
    $skipped_count = 0;

    // 4. Migrate
    foreach ($employees as $emp) {
        $emp_dept_clean = strtolower(trim($emp['department']));
        
        if (isset($dept_map[$emp_dept_clean])) {
            $dept_id = $dept_map[$emp_dept_clean];
            
            // Update
            $update = $pdo->prepare("UPDATE employees SET department_id = ? WHERE id = ?");
            $update->execute([$dept_id, $emp['id']]);
            $updated_count++;
            // echo "    > Linked {$emp['first_name']} to Dept ID $dept_id\n";
        } else {
            // No match found
            echo "    ! No match for '{$emp['department']}' (Emp ID: {$emp['id']})\n";
            $skipped_count++;
            
            // OPTIONAL: Auto-create department? 
            // For now, we skip to avoid creating duplicates from typos. 
            // User can manually correct later or we can add logic if requested.
        }
    }

    echo "  - Migration Complete: Linked $updated_count, Skipped $skipped_count.\n\n";
}

echo "Done.\n";
?>
