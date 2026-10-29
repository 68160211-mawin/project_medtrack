<?php
require_once __DIR__ . '/includes/functions.php';

$u = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$rxId      = (int)($_POST['prescription_id'] ?? 0);
$patientId = (int)($_POST['patient_id'] ?? 0);

if (!can_access_patient($pdo, $u['user_id'], $patientId, true)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์');
}

// ลบเฉพาะยาของผู้ป่วยคนนี้ (ตาราง schedules/intake_logs ลบตามด้วย ON DELETE CASCADE)
$pdo->prepare('DELETE FROM prescriptions WHERE prescription_id = ? AND patient_id = ?')->execute([$rxId, $patientId]);
flash('ลบยาแล้ว');
redirect('patient_detail.php?id=' . $patientId);
