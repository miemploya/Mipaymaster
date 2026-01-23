<?php
// Mock Session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['role'] = 'admin';
$_SESSION['company_name'] = 'Test Co';
$_SESSION['user_photo'] = 'default.jpg';
$_SERVER['PHP_SELF'] = '/dashboard/attendance.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Mock required functions if they cause issues, but likely functions.php handles session checks.
// We'll rely on the actual files.

ob_start();
try {
    include 'attendance.php';
} catch (Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine();
}
$output = ob_get_clean();

if (empty($output)) {
    echo "Output is EMPTY.";
} else {
    echo "Rendered successfully. Length: " . strlen($output) . "\n";
    // Check for common error strings in output that wouldn't throw exception but print error
    if (strpos($output, 'Fatal error') !== false || strpos($output, 'Parse error') !== false) {
        echo "Found PHP Error in output!\n";
        echo substr($output, 0, 500); // Show start of output
    } else {
        echo "Head of output:\n" . substr($output, 0, 200) . "...\n";
    }
}
?>
