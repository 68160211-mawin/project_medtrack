<?php
// สร้างข้อมูลตัวอย่างสำหรับตอนนำเสนอ รันครั้งเดียว:  php seed_demo.php
//   ผู้สูงอายุ: demo.patient@example.com / demo1234
//   ลูกหลาน:    demo.child@example.com   / demo1234
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/includes/functions.php';

$exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
$exists->execute(['demo.patient@example.com']);
if ($exists->fetch()) {
    exit("มีข้อมูลตัวอย่างอยู่แล้ว\n");
}

$hash = password_hash('demo1234', PASSWORD_DEFAULT);
$addUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
$addUser->execute(['คุณยายสมศรี', 'demo.patient@example.com', $hash, 'patient']);
$patientId = (int)$pdo->lastInsertId();
$addUser->execute(['น้องมายด์ (หลานสาว)', 'demo.child@example.com', $hash, 'caregiver']);
$childId = (int)$pdo->lastInsertId();

$pdo->prepare('INSERT INTO care_links (patient_id, caregiver_id, relation) VALUES (?, ?, ?)')
    ->execute([$patientId, $childId, 'หลานสาว']);

$medId = function (string $name) use ($pdo): int {
    $q = $pdo->prepare('SELECT medicine_id FROM medicines WHERE name = ?');
    $q->execute([$name]);
    return (int)$q->fetchColumn();
};

// [ชื่อยา, ขนาด, เวลา[], สต็อก]  สต็อกของตัวแรกตั้งให้ต่ำเพื่อโชว์การเตือนยาใกล้หมด
$plan = [
    ['Metformin', '1 เม็ด', ['08:00', '18:00'], 8],
    ['Amlodipine', '1 เม็ด', ['08:00'], 30],
    ['Vitamin B complex', '1 เม็ด', ['12:00'], 45],
];

$addRx  = $pdo->prepare('INSERT INTO prescriptions (patient_id, medicine_id, dose, start_date, stock_left, created_by)
                         VALUES (?, ?, ?, ?, ?, ?)');
$addSch = $pdo->prepare('INSERT INTO schedules (prescription_id, time_of_day) VALUES (?, ?)');
$addLog = $pdo->prepare('INSERT IGNORE INTO intake_logs (schedule_id, scheduled_at, taken_at, status) VALUES (?, ?, ?, ?)');

$start = date('Y-m-d', strtotime('-7 days'));
$i = 0;
foreach ($plan as [$name, $dose, $times, $stock]) {
    $addRx->execute([$patientId, $medId($name), $dose, $start, $stock, $childId]);
    $rxId = (int)$pdo->lastInsertId();
    foreach ($times as $t) {
        $addSch->execute([$rxId, $t . ':00']);
        $schId = (int)$pdo->lastInsertId();
        // ประวัติย้อนหลัง 1-6 วัน: กินเกือบครบ มีข้ามบ้างเพื่อให้กราฟดูสมจริง
        for ($d = 6; $d >= 1; $d--) {
            $sched = date('Y-m-d', strtotime("-$d days")) . " $t:00";
            $i++;
            if ($i % 7 === 0) {
                $addLog->execute([$schId, $sched, null, 'missed']);
            } else {
                $addLog->execute([$schId, $sched, date('Y-m-d H:i:s', strtotime($sched) + 600), 'taken']);
            }
        }
    }
}

echo "สร้างข้อมูลตัวอย่างแล้ว\n";
