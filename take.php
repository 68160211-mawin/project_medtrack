<?php
// รับคำสั่ง POST "กินแล้ว" จากปุ่มในหน้ายาวันนี้ แล้ว redirect กลับ (Post/Redirect/Get)
require_once __DIR__ . '/includes/functions.php';

$u = require_login($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$ok = take_dose($conn, (int)($_POST['log_id'] ?? 0), $u['user_id']);
flash($ok ? 'บันทึกแล้วว่ากินยา ✔' : 'ไม่สามารถบันทึกได้');

// อนุญาตให้กลับเฉพาะหน้าภายในระบบ
$back = $_POST['back'] ?? '';
if (!preg_match('/^[a-z_]+\.php(\?id=\d+)?$/', $back)) {
    $back = 'index.php';
}
redirect($back);
