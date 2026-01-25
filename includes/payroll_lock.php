<?php
// includes/payroll_lock.php

/**
 * Checks if payroll is locked/finalized
 */
function is_payroll_locked($company_id, $month, $year) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM payroll_runs WHERE company_id=? AND period_month=? AND period_year=?");
    $stmt->execute([$company_id, $month, $year]);
    $run = $stmt->fetch();
    return ($run && $run['status'] === 'locked');
}

/**
 * Enforce lock check - throws exception if locked
 */
function ensure_payroll_open($company_id, $month, $year) {
    if (is_payroll_locked($company_id, $month, $year)) {
        throw new Exception("Payroll for this period is locked and cannot be modified.");
    }
}
?>
