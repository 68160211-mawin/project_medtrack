<?php
// ค่าตั้งต้นของระบบ MedTrack

// ถ้าถึงเวลากินยาแล้วเกินกี่นาทียังไม่กด "กินแล้ว" ให้ถือว่าข้ามมื้อและแจ้งเตือนผู้ดูแล
const MISSED_AFTER_MIN = 30;
// ถ้ายาเหลือไม่เกินจำนวนวันนี้ ให้แจ้งเตือนผู้ดูแล
const LOW_STOCK_DAYS = 5;

// เขตเวลาของระบบ และเริ่ม Session (ใช้เก็บข้อมูลผู้ใช้ที่ล็อกอิน)
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
