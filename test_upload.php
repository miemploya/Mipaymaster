<?php
// test_upload.php
// Simulates a file upload to company.php logic
// Since we can't easily fake $_FILES in CLI, we'll replicate the logic block.

require_once 'includes/functions.php';

// Mock Session
$_SESSION['company_id'] = 1;

$company_id = 1;
$targetDir = "uploads/logos/";

// Mock file
$mock_tmp = 'temp_logo.png';
$mock_name = 'test_logo.png';
file_put_contents($mock_tmp, 'FAKE IMAGE CONTENT');

// Simulate Logic from company.php
$allowTypes = ['jpg', 'png', 'jpeg', 'gif'];
$fileName = basename($mock_name);
$fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

echo "File Type: $fileType\n";

if (in_array($fileType, $allowTypes)) {
    $newFileName = 'logo_' . $company_id . '_' . time() . '.' . $fileType;
    $targetFilePath = $targetDir . $newFileName;
    
    // Use copy instead of move_uploaded_file for CLI test
    if (copy($mock_tmp, $targetFilePath)) {
        echo "Success: File copied to $targetFilePath\n";
        
        // Update DB
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("UPDATE companies SET logo_url=? WHERE id=?");
            $stmt->execute([$newFileName, $company_id]);
            echo "Success: DB Updated with $newFileName\n";
            
        } catch (PDOException $e) {
            echo "DB Error: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "Error: Failed to copy file.\n";
    }
} else {
    echo "Error: Invalid file type.\n";
}

// Clean up
@unlink($mock_tmp);
?>
