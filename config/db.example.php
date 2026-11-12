<?php
// ตัวอย่างไฟล์เชื่อมต่อฐานข้อมูล (MySQLi)
// วิธีใช้: คัดลอกไฟล์นี้เป็น config/db.php แล้วใส่ค่าจริงของคุณ
// config/db.php อยู่ใน .gitignore จึงไม่ถูกอัปโหลดขึ้น GitHub

// ให้ mysqli โยน exception เมื่อ SQL ผิดพลาด (ใช้ try/catch + rollback ใน Transaction ได้)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'your_db_user';
$pass   = getenv('DB_PASS') ?: 'your_db_password';
$dbname = getenv('DB_NAME') ?: 'your_db_name';

try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('เชื่อมต่อฐานข้อมูลไม่ได้ ตรวจสอบ config/db.php และนำเข้า database/setup_server.sql แล้วหรือยัง');
}
