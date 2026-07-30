<?php
$hostName = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
$isLocal = in_array($hostName, ['localhost', '127.0.0.1', '::1']) || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');

if ($isLocal) {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'kiemdinh_cntt');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'sql311.infinityfree.com');
    define('DB_NAME', 'if0_42436652_kiemdinh_cntt');
    define('DB_USER', 'if0_42436652');
    define('DB_PASS', 'zFmXAWtsSLk');
}

define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
    }

    return $pdo;
}
?>
