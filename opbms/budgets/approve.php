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
$stmt = $pdo->prepare("UPDATE budgets SET status='approved', approved_by=?, approved_at=NOW() WHERE id=? AND status!='approved'");
$stmt->execute([$_SESSION['user_id'], $id]);
setFlash('Budget approved successfully.', 'success');
redirect(BASE_URL.'/budgets/index.php');
