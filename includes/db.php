<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $ex) {
    http_response_code(500);
    exit('เชื่อมต่อฐานข้อมูลไม่ได้ ตรวจสอบ config.php และนำเข้า database.sql แล้วหรือยัง');
}
