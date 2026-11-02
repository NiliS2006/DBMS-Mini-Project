<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../includes/auth.php';
logoutUser();
header('Location: ' . BASE_URL . '/');
exit;
