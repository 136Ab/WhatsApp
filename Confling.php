<?php
// Start session with error handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$dbname = 'dbgxklgcifguey';
$username = 'uaozeqcbxyhyg';
$password = 'f4kld3wzz1v3';

// Error logging configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Establish database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Verify database exists
    $pdo->query("SELECT 1 FROM users LIMIT 1");
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Database error: Unable to connect or initialize. Please check server logs.");
}
?>
