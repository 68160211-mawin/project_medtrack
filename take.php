<?php
require_once __DIR__ . '/includes/functions.php';

$u = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$ok = take_dose($pdo, (int)($_POST['log_id'] ?? 0), $u['user_id']);
flash($ok ? 'บันทึกแล้วว่ากินยา ✔' : 'ไม่สามารถบันทึกได้');

$back = $_POST['back'] ?? '';
// อนุญาตเฉพาะกลับไปหน้าภายในระบบ
if (!preg_match('/^[a-z_]+\.php(\?id=\d+)?$/', $back)) {
    $back = 'index.php';
}
redirect($back);
