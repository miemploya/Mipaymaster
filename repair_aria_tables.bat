@echo off
echo ========================================
echo MariaDB Aria Table Repair Script
echo ========================================
echo.
echo This will repair corrupted Aria tables
echo.

cd C:\xampp\mysql\bin

echo Step 1: Checking and repairing aria_log_control...
aria_chk.exe -r "C:\xampp\mysql\data\aria_log_control"

echo.
echo Step 2: Repairing mysql database tables...
aria_chk.exe -r "C:\xampp\mysql\data\mysql\*.MAI"

echo.
echo Step 3: Done! Now start MySQL in XAMPP Control Panel
echo.
pause
