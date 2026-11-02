<?php
// Database configuration for OPBMS
// Adjust credentials to match your XAMPP MySQL setup
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);       // Change if MySQL is configured to use another port.
define('DB_NAME', 'opbms');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP MySQL password is empty
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            exit('<!doctype html><html lang="en"><meta charset="utf-8"><title>OPBMS unavailable</title>'
                . '<body><h1>OPBMS is temporarily unavailable</h1>'
                . '<p>Could not connect to the database. Verify the MySQL service and settings in config/database.php.</p>'
                . '</body></html>');
        }
    }
    return $pdo;
}
