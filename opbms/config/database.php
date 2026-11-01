<?php
// Database configuration for OPBMS
// Adjust credentials to match your XAMPP MySQL setup
define('DB_HOST', 'localhost');
define('DB_NAME', 'opbms');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP MySQL password is empty
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error without exposing credentials
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed. Check config/database.php settings.']));
        }
    }
    return $pdo;
}
