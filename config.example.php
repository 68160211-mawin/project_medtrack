<?php
// ไฟล์ตัวอย่าง: คัดลอกเป็น config.php แล้วใส่ค่าจริงของคุณ (config.php จะไม่ถูกอัปโหลดขึ้น Git)
//   cp config.example.php config.php
// ค่าอ่านจาก environment variable ก่อน ถ้าไม่มีจึงใช้ค่าหลัง ?: (ใส่ค่าจริงเฉพาะในไฟล์ config.php)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'your_database_name');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');

// ถ้าถึงเวลากินยาแล้วเกินกี่นาทียังไม่กด "กินแล้ว" ให้ถือว่าข้ามมื้อและแจ้งเตือนผู้ดูแล
const MISSED_AFTER_MIN = 30;
// ถ้ายาเหลือน้อยกว่าจำนวนวันนี้ ให้แจ้งเตือนผู้ดูแล
const LOW_STOCK_DAYS = 5;

date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
