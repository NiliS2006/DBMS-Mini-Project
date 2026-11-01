<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    redirect(getDashboardUrl());
} else {
    redirect(BASE_URL . '/auth/login.php');
}
