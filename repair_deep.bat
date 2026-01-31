@echo off
echo ========================================
echo MariaDB Deep Repair Script v2
echo ========================================
echo.

cd C:\xampp\mysql\bin

echo Deleting corrupted aria log control file...
del "C:\xampp\mysql\data\aria_log_control" 2>nul
del "C:\xampp\mysql\data\aria_log.00000001" 2>nul

echo.
echo Repairing user privilege tables with safe-recover...

aria_chk.exe -o -f --sort_buffer_size=268435456 "C:\xampp\mysql\data\mysql\global_priv.MAI"
aria_chk.exe -o -f --sort_buffer_size=268435456 "C:\xampp\mysql\data\mysql\columns_priv.MAI"
aria_chk.exe -o -f --sort_buffer_size=268435456 "C:\xampp\mysql\data\mysql\proxies_priv.MAI"
aria_chk.exe -o -f --sort_buffer_size=268435456 "C:\xampp\mysql\data\mysql\db.MAI"
aria_chk.exe -o -f --sort_buffer_size=268435456 "C:\xampp\mysql\data\mysql\tables_priv.MAI"
aria_chk.exe -o -f --sort_buffer_size=268435456 "C:\xampp\mysql\data\mysql\help_topic.MAI"

echo.
echo ========================================
echo Repair Complete! 
echo Now try starting MySQL in XAMPP
echo ========================================
pause
