
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$dbname = 'taskmate';
$username = 'root';
$password = '';

$dsn = "mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);

    exit(
        '<h2>TaskMate Database Connection Failed</h2><pre>' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        '</pre>'
    );
}
