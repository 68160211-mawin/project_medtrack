<?php
// สร้างข้อมูลตัวอย่างสำหรับตอนนำเสนอ รันครั้งเดียวจากบรรทัดคำสั่ง:  php seed_demo.php
//   ผู้สูงอายุ: demo.patient@example.com / demo1234
//   ลูกหลาน:    demo.child@example.com   / demo1234
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/includes/functions.php';

if (db_val($conn, 'SELECT 1 FROM project_users WHERE email = ?', 's', ['demo.patient@example.com'])) {
    exit("มีข้อมูลตัวอย่างอยู่แล้ว\n");
}

$hash = password_hash('demo1234', PASSWORD_DEFAULT);
$addUser = 'INSERT INTO project_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)';

db_exec($conn, $addUser, 'ssss', ['คุณยายสมศรี', 'demo.patient@example.com', $hash, 'patient']);
$patientId = $conn->insert_id;
db_exec($conn, $addUser, 'ssss', ['น้องมายด์ (หลานสาว)', 'demo.child@example.com', $hash, 'caregiver']);
$childId = $conn->insert_id;

db_exec($conn, 'INSERT INTO project_care_links (patient_id, caregiver_id, relation) VALUES (?, ?, ?)', 'iis', [$patientId, $childId, 'หลานสาว']);

// [ชื่อยา, ขนาด, เวลา[], สต็อก]  สต็อกของตัวแรกตั้งให้ต่ำเพื่อโชว์การเตือนยาใกล้หมด
$plan = [
    ['Metformin', '1 เม็ด', ['08:00', '18:00'], 8],
    ['Amlodipine', '1 เม็ด', ['08:00'], 30],
    ['Vitamin B complex', '1 เม็ด', ['12:00'], 45],
];

$start = date('Y-m-d', strtotime('-7 days'));
$i = 0;
foreach ($plan as [$name, $dose, $times, $stock]) {
    $medId = (int)db_val($conn, 'SELECT medicine_id FROM project_medicines WHERE name = ?', 's', [$name]);
    db_exec($conn, 'INSERT INTO project_prescriptions (patient_id, medicine_id, dose, start_date, stock_left, created_by) VALUES (?, ?, ?, ?, ?, ?)',
        'iissii', [$patientId, $medId, $dose, $start, $stock, $childId]);
    $rxId = $conn->insert_id;
    foreach ($times as $t) {
        db_exec($conn, 'INSERT INTO project_schedules (prescription_id, time_of_day) VALUES (?, ?)', 'is', [$rxId, $t . ':00']);
        $schId = $conn->insert_id;
        // ประวัติย้อนหลัง 1-6 วัน: กินเกือบครบ มีข้ามบ้างเพื่อให้กราฟดูสมจริง
        for ($d = 6; $d >= 1; $d--) {
            $sched = date('Y-m-d', strtotime("-$d days")) . " $t:00";
            $i++;
            if ($i % 7 === 0) {
                db_exec($conn, "INSERT IGNORE INTO project_intake_logs (schedule_id, scheduled_at, status) VALUES (?, ?, 'missed')", 'is', [$schId, $sched]);
            } else {
                db_exec($conn, "INSERT IGNORE INTO project_intake_logs (schedule_id, scheduled_at, taken_at, status) VALUES (?, ?, ?, 'taken')",
                    'iss', [$schId, $sched, date('Y-m-d H:i:s', strtotime($sched) + 600)]);
            }
        }
    }
}

echo "สร้างข้อมูลตัวอย่างแล้ว\n";
