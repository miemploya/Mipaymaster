<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
    $stmt = $pdo->query('SELECT id, name, email FROM companies');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch(Exception $e) {
    echo $e->getMessage();
}
