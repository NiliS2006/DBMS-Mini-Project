<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit('Receipt not found.');
}

$scope = getExpenseScopeSql($_SESSION['user_role'], (int)$_SESSION['user_id'], 'e');
$stmt = getDB()->prepare("SELECT e.receipt_path FROM expenses e WHERE e.id=? AND ($scope[0])");
$stmt->execute(array_merge([$id], $scope[1]));
$receipt = $stmt->fetchColumn();
if (!$receipt || !preg_match('/^assets\/uploads\/(rcpt_(?:[a-f0-9]{32}|\d+_[a-f0-9]{8})\.(?:jpg|jpeg|png|pdf|webp))$/D', $receipt, $matches)) {
    http_response_code(404);
    exit('Receipt not found.');
}

$uploadDir = realpath(__DIR__ . '/../assets/uploads');
$filePath = $uploadDir ? realpath($uploadDir . DIRECTORY_SEPARATOR . $matches[1]) : false;
if (!$filePath || dirname($filePath) !== $uploadDir || !is_file($filePath)) {
    http_response_code(404);
    exit('Receipt not found.');
}

$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    'webp' => 'image/webp',
];
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Disposition: inline; filename="receipt.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
