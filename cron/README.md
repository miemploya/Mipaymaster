# MiPayMaster Cron Jobs

This directory contains scheduled task scripts for automated attendance management.

## Available Scripts

### 1. `cron_auto_absent.php`
Automatically marks employees as **absent** if they haven't clocked in by the cutoff time.

**Trigger Conditions:**
- Company has `auto_mark_absent = 1` in attendance_policies
- Current time is past `check_in_time + absent_cutoff_minutes`
- Employee has no attendance record for today
- Today is a working day for the employee (respects shift/daily mode)

**What it does:**
- Creates attendance record with `status = 'absent'`
- Applies absent deduction amount from policy
- Marks record with `is_auto_marked = 1`
- Logs audit entry

### 2. `cron_auto_checkout.php`
Automatically closes open attendance sessions (employees who forgot to clock out).

**Trigger Conditions:**
- Company has `auto_checkout_enabled = 1` in attendance_policies
- Current time is past the expected checkout time
- Employee has clocked in but not out

**What it does:**
- Sets `check_out_time` to expected checkout time
- Marks record with `requires_review = 1` for HR review
- Also cleans up stale sessions from previous days
- Logs audit entry

## Windows Task Scheduler Setup

### Auto-Absent Task
```
Task Name: MiPayMaster Auto-Absent
Trigger: Daily at 10:30 AM (30 mins after typical check-in)
Action: php C:\xampp\htdocs\Mipaymaster\cron\cron_auto_absent.php
```

### Auto-Checkout Task
```
Task Name: MiPayMaster Auto-Checkout
Trigger: Daily at 8:00 PM
Action: php C:\xampp\htdocs\Mipaymaster\cron\cron_auto_checkout.php
```

## Linux/Unix Cron Setup

Add to crontab (`crontab -e`):

```bash
# Auto-absent at 10:30 AM Mon-Fri
30 10 * * 1-5 /usr/bin/php /var/www/Mipaymaster/cron/cron_auto_absent.php >> /var/log/mipay_absent.log 2>&1

# Auto-checkout at 8:00 PM Mon-Fri
0 20 * * 1-5 /usr/bin/php /var/www/Mipaymaster/cron/cron_auto_checkout.php >> /var/log/mipay_checkout.log 2>&1
```

## Manual Testing

You can run the scripts manually from the command line:

```bash
# Windows
php C:\xampp\htdocs\Mipaymaster\cron\cron_auto_absent.php
php C:\xampp\htdocs\Mipaymaster\cron\cron_auto_checkout.php

# Linux/Mac
php /path/to/Mipaymaster/cron/cron_auto_absent.php
php /path/to/Mipaymaster/cron/cron_auto_checkout.php
```

## Configuration

Enable/disable these features in **Company Setup → Attendance**:
- **Auto-mark Absent**: Toggle + set cutoff minutes
- **Auto-checkout**: Toggle

## Security

These scripts:
- Only run from CLI (blocks web access)
- Require database connection via `includes/functions.php`
- Log all actions to audit trail
