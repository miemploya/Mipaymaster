# DATABASE RESTORATION GUIDE
## Fix Company Setup & Account Switching Issues

### 🎯 What This Will Do:
- Create a **clean, fresh database** with proper structure
- Set up **ONE company** with **ONE admin user**
- Fix the account switching problem permanently
- Give you a working login system

### ⚠️ IMPORTANT WARNINGS:
- **This will DELETE all existing data in the database**
- **All employees, payroll records, and company data will be lost**
- **Make sure you have a backup if you need to preserve anything**

### ✅ What You'll Get:
- Clean database structure
- Single company setup
- Admin login credentials
- No more account switching issues

---

## STEP-BY-STEP INSTRUCTIONS

### OPTION 1: Using MySQL Command Line (Recommended)

#### Step 1: Open Command Prompt
Press `Win + R`, type `cmd`, press Enter

#### Step 2: Navigate to your project folder
```cmd
cd C:\xampp\htdocs\Mipaymaster
```

#### Step 3: Run the restoration script
```cmd
C:\xampp\mysql\bin\mysql.exe --user=root < FRESH_DATABASE_SETUP.sql
```

#### Step 4: Verify the setup
```cmd
C:\xampp\php\php.exe verify_setup.php
```

You should see:
```
[✓✓✓] ALL CHECKS PASSED!

Your database is correctly set up.
You can now login at: http://localhost/Mipaymaster/auth/login.php

Login Credentials:
  Email: admin@mycompany.com
  Password: password
```

#### Step 5: Test Login
1. Open browser
2. Go to: `http://localhost/Mipaymaster/auth/login.php`
3. Enter:
   - Email: `admin@mycompany.com`
   - Password: `password`
4. You should be logged in successfully!

#### Step 6: Change Your Password (IMPORTANT!)
1. After logging in, go to settings/profile
2. Change the default password to something secure

---

### OPTION 2: Using phpMyAdmin (Alternative)

#### Step 1: Open phpMyAdmin
Go to: `http://localhost/phpmyadmin`

#### Step 2: Drop old database
1. Click on `mipaymaster` database in the left panel
2. Click on "Operations" tab
3. Scroll to bottom, click "Drop the database"
4. Confirm deletion

#### Step 3: Create new database
1. Click "New" in the left panel
2. Enter database name: `mipaymaster`  
3. Collation: `utf8mb4_general_ci`
4. Click "Create"

#### Step 4: Import setup script
1. Click on `mipaymaster` database
2. Click "Import" tab
3. Click "Choose File"
4. Select: `C:\xampp\htdocs\Mipaymaster\FRESH_DATABASE_SETUP.sql`
5. Click "Go" at bottom
6. Wait for "Import has been successfully finished"

#### Step 5: Verify the setup
Open Command Prompt and run:
```cmd
cd C:\xampp\htdocs\Mipaymaster
C:\xampp\php\php.exe verify_setup.php
```

#### Step 6: Test Login
Same as Option 1, Step 5 above

---

## 🔍 VERIFICATION CHECKLIST

After running the restoration, verify the following:

- [ ] Database `mipaymaster` exists
- [ ] Only **1 company** record exists
- [ ] Only **1 user** record exists (admin)
- [ ] 3 salary categories exist (Junior, Senior, Management)
- [ ] Statutory settings exist
- [ ] 3 departments exist (Administration, Operations, Finance)
- [ ] Login page loads without errors
- [ ] You can login with admin@mycompany.com / password
- [ ] After login, you see the dashboard
- [ ] No account switching prompts appear

---

## 🚨 TROUBLESHOOTING

### Problem: "ERROR 2002: Can't connect to MySQL server"
**Solution**: Start MySQL from XAMPP Control Panel

### Problem: "ERROR 1045: Access denied for user 'root'"
**Solution**: If your MySQL root has a password, add it:
```cmd
C:\xampp\mysql\bin\mysql.exe --user=root --password=YOUR_PASSWORD < FRESH_DATABASE_SETUP.sql
```

### Problem: "ERROR 1049: Unknown database 'mipaymaster'"
**Solution**: This is normal - the script creates it. Just run the command again.

### Problem: "Cannot find file FRESH_DATABASE_SETUP.sql"
**Solution**: Make sure you're in the correct directory:
```cmd
cd C:\xampp\htdocs\Mipaymaster
dir FRESH_DATABASE_SETUP.sql
```

### Problem: Verification shows warnings
**Solution**: Re-run the setup script:
```cmd
C:\xampp\mysql\bin\mysql.exe --user=root < FRESH_DATABASE_SETUP.sql
```

---

## 📝 DEFAULT LOGIN CREDENTIALS

**Email**: `admin@mycompany.com`  
**Password**: `password`

### ⚠️ SECURITY WARNING:
**You MUST change this password immediately after first login!**

The default password is for initial setup only and is NOT secure.

---

## 🔄 WHAT IF I NEED MY OLD DATA?

If you realize you need data from the old database:

### Option A: Restore from backup files
The following backup files exist:
1. `full_backup_recovery.sql`
2. `mipaymaster_backup.sql`
3. `rescue_dump.sql`

To restore from backup:
```cmd
C:\xampp\mysql\bin\mysql.exe --user=root mipaymaster < full_backup_recovery.sql
```

### Option B: Extract specific data
If you only need certain records (like employees), you can:
1. Restore the fresh database first
2. Then manually import specific tables from backup using phpMyAdmin

---

## ✅ POST-SETUP CONFIGURATION

After successful login, complete these steps:

### 1. Update Company Profile
- Go to: Company → Profile
- Update:
  - Company name
  - Email
  - Phone
  - Address
  - Upload logo

### 2. Configure Salary Components
- Go to: Company → Payroll Items
- Review and adjust default allowances/deductions

### 3. Configure Statutory Settings
- Go to: Company → Statutory
- Verify pension percentages
- Enable/disable NHIS, NHF as needed

### 4. Add Departments
- Go to: Company → Departments
- Add your actual departments

### 5. Create Employees
- Go to: Employees → Add New
- Add your staff members

---

## 📞 NEED HELP?

If you encounter issues:
1. Check the `db_debug.log` file for connection errors
2. Check the `login_debug.log` file for login issues
3. Run `verify_setup.php` to see the status
4. Share the error messages for further assistance

---

**Last Updated**: 2026-01-23
**Version**: 1.0
