$ErrorActionPreference = "SilentlyContinue"
Write-Host "Waiting for MySQL to come online..."
$max_retries = 30
$count = 0

while ($count -lt $max_retries) {
    # Try to dump
    & c:\xampp\mysql\bin\mysqldump.exe -u root --all-databases --result-file=c:\xampp\htdocs\Mipaymaster\rescue_dump.sql 2>&1 | Out-Null
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "SUCCESS! Database dumped to c:\xampp\htdocs\Mipaymaster\rescue_dump.sql"
        exit 0
    }
    
    Write-Host "." -NoNewline
    Start-Sleep -Seconds 1
    $count++
}

Write-Host "Timed out waiting for MySQL."
