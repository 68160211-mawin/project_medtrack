<?php
// รันจากบรรทัดคำสั่งทุก 5 นาที เพื่อสร้างมื้อยาของวันและตรวจมื้อที่ข้าม
//   Linux/macOS (crontab):  */5 * * * * php /path/to/medtrack/cron.php
//   Windows: ตั้งใน Task Scheduler ให้รัน  php.exe C:\path\to\medtrack\cron.php  ทุก 5 นาที
// (ถ้าไม่ตั้ง ระบบก็ทำงานเมื่อมีคนเปิดหน้าเว็บ แต่การแจ้งเตือนจะช้ากว่า)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/includes/functions.php';

generate_logs_for_today($conn);
$n = mark_missed($conn);
echo date('Y-m-d H:i:s'), " done, missed doses processed: $n\n";
