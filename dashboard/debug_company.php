<?php
require_once '../includes/functions.php';
session_start();

echo "<h1>Debug Info</h1>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not Set') . "<br>";
echo "Company ID from Session: " . ($_SESSION['company_id'] ?? 'Not Set') . "<br>";

echo "<h2>Companies in DB</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM companies");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($companies)) {
        echo "No companies found in 'companies' table.<br>";
    } else {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Email</th></tr>";
        foreach ($companies as $c) {
            echo "<tr><td>{$c['id']}</td><td>{$c['name']}</td><td>{$c['email']}</td></tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "Error querying companies: " . $e->getMessage();
}
?>
