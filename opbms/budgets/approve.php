<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(BASE_URL.'/budgets/index.php'); }
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlash('Invalid CSRF token.','danger'); redirect(BASE_URL.'/budgets/index.php'); }
$id = sanitizeInt($_POST['id'] ?? 0);
if (!$id) { setFlash('Invalid budget.','danger'); redirect(BASE_URL.'/budgets/index.php'); }

try {
    $pdo->beginTransaction();
    $budgetStmt = $pdo->prepare("SELECT * FROM budgets WHERE id=? AND status IN ('proposed','revised') FOR UPDATE");
    $budgetStmt->execute([$id]);
    $budget = $budgetStmt->fetch();
    if (!$budget) {
        $pdo->rollBack();
        setFlash('Budget was not found or has already been approved.', 'warning');
        redirect(BASE_URL.'/budgets/index.php');
    }

    $duplicateStmt = $pdo->prepare("SELECT COUNT(*) FROM budgets
        WHERE entity_type=? AND entity_id=? AND fiscal_year=? AND period_start=? AND period_end=?
          AND status='approved' AND id<>?");
    $duplicateStmt->execute([$budget['entity_type'], $budget['entity_id'], $budget['fiscal_year'], $budget['period_start'], $budget['period_end'], $id]);
    if ($duplicateStmt->fetchColumn()) {
        $pdo->rollBack();
        setFlash('An approved budget already exists for this entity and period.', 'danger');
        redirect(BASE_URL.'/budgets/index.php');
    }

    $parentType = null;
    $parentId = null;
    if ($budget['entity_type'] === 'department') {
        $parentType = 'organization';
        $parentStmt = $pdo->prepare("SELECT organization_id FROM departments WHERE id=?");
        $parentStmt->execute([$budget['entity_id']]);
        $parentId = $parentStmt->fetchColumn();
    } elseif ($budget['entity_type'] === 'project') {
        $parentType = 'department';
        $parentStmt = $pdo->prepare("SELECT department_id FROM projects WHERE id=?");
        $parentStmt->execute([$budget['entity_id']]);
        $parentId = $parentStmt->fetchColumn();
    }

    if ($parentType !== null) {
        $parentBudget = $pdo->prepare("SELECT amount FROM budgets
            WHERE entity_type=? AND entity_id=? AND fiscal_year=? AND period_start<=? AND period_end>=? AND status='approved'
            ORDER BY amount DESC LIMIT 1 FOR UPDATE");
        $parentBudget->execute([$parentType, $parentId, $budget['fiscal_year'], $budget['period_start'], $budget['period_end']]);
        $parentAmount = $parentBudget->fetchColumn();
        if ($parentAmount === false) {
            $pdo->rollBack();
            setFlash('An approved parent budget covering this period is required first.', 'danger');
            redirect(BASE_URL.'/budgets/index.php');
        }

        if ($budget['entity_type'] === 'department') {
            $allocated = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM budgets
                WHERE entity_type='department' AND entity_id IN (SELECT id FROM departments WHERE organization_id=?)
                  AND fiscal_year=? AND period_start=? AND period_end=? AND status IN ('approved','revised','proposed') AND id<>?");
        } else {
            $allocated = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM budgets
                WHERE entity_type='project' AND entity_id IN (SELECT id FROM projects WHERE department_id=?)
                  AND fiscal_year=? AND period_start=? AND period_end=? AND status IN ('approved','revised','proposed') AND id<>?");
        }
        $allocated->execute([$parentId, $budget['fiscal_year'], $budget['period_start'], $budget['period_end'], $id]);
        if ((float)$allocated->fetchColumn() + (float)$budget['amount'] > (float)$parentAmount) {
            $pdo->rollBack();
            setFlash('Approving this allocation would exceed the available parent budget.', 'danger');
            redirect(BASE_URL.'/budgets/index.php');
        }
    }

    $update = $pdo->prepare("UPDATE budgets SET status='approved', approved_by=?, approved_at=NOW() WHERE id=? AND status IN ('proposed','revised')");
    $update->execute([$_SESSION['user_id'], $id]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        setFlash('Budget was already processed. Refresh the budget list.', 'warning');
        redirect(BASE_URL.'/budgets/index.php');
    }
    $pdo->commit();
    setFlash('Budget approved successfully.', 'success');
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Budget approval failed: ' . $ex->getMessage());
    setFlash('Failed to approve the budget. Please try again.', 'danger');
}
redirect(BASE_URL.'/budgets/index.php');
