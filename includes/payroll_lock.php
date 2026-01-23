<?php
// includes/payroll_lock.php

class PayrollLock {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Lock a payroll run
     * Transforming it to immutable state.
     */
    public function lock_payroll($run_id, $user_id) {
        try {
            // Check current status
            $stmt = $this->pdo->prepare("SELECT status FROM payroll_runs WHERE id = ?");
            $stmt->execute([$run_id]);
            $run = $stmt->fetch();

            if (!$run) return ['status' => false, 'message' => 'Payroll run not found'];
            if ($run['status'] === 'locked') return ['status' => false, 'message' => 'Payroll is already locked'];
            if ($run['status'] === 'reversed') return ['status' => false, 'message' => 'Cannot lock a reversed payroll'];

            // Lock it
            $update = $this->pdo->prepare("UPDATE payroll_runs 
                SET status = 'locked', locked_by = ?, locked_at = NOW() 
                WHERE id = ?");
            $update->execute([$user_id, $run_id]);

            return ['status' => true, 'message' => 'Payroll run locked successfully'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reverse a locked payroll
     * Does NOT delete, but marks as reversed and logs the action.
     */
    public function reverse_payroll($run_id, $user_id, $reason) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT status FROM payroll_runs WHERE id = ?");
            $stmt->execute([$run_id]);
            $run = $stmt->fetch();

            if (!$run) throw new Exception('Payroll run not found');
            if ($run['status'] !== 'locked') throw new Exception('Only locked payrolls can be reversed');

            // Update status
            $update = $this->pdo->prepare("UPDATE payroll_runs SET status = 'reversed' WHERE id = ?");
            $update->execute([$run_id]);

            // Log reversal
            $log = $this->pdo->prepare("INSERT INTO payroll_reversals (payroll_run_id, reason, reversed_by, reversed_at) VALUES (?, ?, ?, NOW())");
            $log->execute([$run_id, $reason, $user_id]);

            $this->pdo->commit();
            return ['status' => true, 'message' => 'Payroll run reversed successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function is_locked($run_id) {
        $stmt = $this->pdo->prepare("SELECT status FROM payroll_runs WHERE id = ?");
        $stmt->execute([$run_id]);
        $run = $stmt->fetch();
        return ($run && $run['status'] === 'locked');
    }
}
?>
