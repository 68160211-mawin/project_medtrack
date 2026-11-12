<?php
// หน้าแรก: ถ้ายังไม่ล็อกอินไปหน้า login ถ้าล็อกอินแล้วไปหน้าตามบทบาท
require_once __DIR__ . '/includes/functions.php';

$u = current_user();
if (!$u) {
    redirect('login.php');
}
redirect($u['role'] === 'caregiver' ? 'caregiver.php' : 'patient.php');
