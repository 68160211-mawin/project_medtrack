<?php
// ลบยา: ลบเฉพาะยาของผู้ป่วยคนนั้น (project_schedules / project_intake_logs ลบตามด้วย ON DELETE CASCADE)
require_once __DIR__ . '/includes/functions.php';

$u = require_login($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$rxId      = (int)($_POST['prescription_id'] ?? 0);
$patientId = (int)($_POST['patient_id'] ?? 0);

if (!can_access_patient($conn, $u['user_id'], $patientId, true)) {
    forbidden();
}

$n = db_exec($conn, 'DELETE FROM project_prescriptions WHERE prescription_id = ? AND patient_id = ?', 'ii', [$rxId, $patientId]);
flash($n ? 'ลบยาแล้ว' : 'ไม่พบรายการยานี้');
redirect('patient_detail.php?id=' . $patientId);
