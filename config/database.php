<?php
// config/database.php - PDO Database Connection and Settings Cache

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'clothes_retail_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Function to fetch settings as associative array
function get_settings($pdo) {
    static $settings = null;
    if ($settings === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

// Function to get a single setting with default fallback
function get_setting($pdo, $key, $default = '') {
    $settings = get_settings($pdo);
    return $settings[$key] ?? $default;
}
