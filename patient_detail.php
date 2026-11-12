<?php
// รายละเอียดผู้ป่วย: ยาที่กิน เวลา สต็อก ประวัติ 7 วัน (ผู้สูงอายุดูของตัวเอง / ลูกหลานดูของคนที่เชื่อมไว้)
require_once __DIR__ . '/includes/functions.php';

$u  = require_login($conn);
$id = (int)($_GET['id'] ?? 0);
if (!can_access_patient($conn, $u['user_id'], $id)) {
    forbidden('ไม่มีสิทธิ์ดูข้อมูลนี้');
}
$canEdit = can_access_patient($conn, $u['user_id'], $id, true);

generate_logs_for_today($conn);
mark_missed($conn);

$patientName = db_val($conn, 'SELECT name FROM project_users WHERE user_id = ?', 'i', [$id]);

$prescriptions = db_all($conn, "SELECT p.prescription_id, p.dose, p.start_date, p.end_date, p.stock_left,
                                       m.name AS med_name, m.dosage_form,
                                       GROUP_CONCAT(TIME_FORMAT(s.time_of_day, '%H:%i') ORDER BY s.time_of_day SEPARATOR ', ') AS times,
                                       MIN(s.days_of_week) AS days
                                FROM project_prescriptions p
                                JOIN project_medicines m ON m.medicine_id = p.medicine_id
                                LEFT JOIN project_schedules s ON s.prescription_id = p.prescription_id
                                WHERE p.patient_id = ? AND (p.end_date IS NULL OR p.end_date >= CURDATE())
                                GROUP BY p.prescription_id, p.dose, p.start_date, p.end_date, p.stock_left, m.name, m.dosage_form
                                ORDER BY m.name", 'i', [$id]);

$history = db_all($conn, "SELECT l.scheduled_at, l.taken_at, l.status, m.name AS med_name
                          FROM project_intake_logs l
                          JOIN project_schedules s ON s.schedule_id = l.schedule_id
                          JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
                          JOIN project_medicines m ON m.medicine_id = p.medicine_id
                          WHERE p.patient_id = ? AND l.scheduled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                          ORDER BY l.scheduled_at DESC LIMIT 100", 'i', [$id]);

$adh = adherence_percent($conn, $id, 7);

$pageTitle = 'ข้อมูลยา ' . $patientName;
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($patientName) ?></h1>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h2 class="h5">ความสม่ำเสมอใน 7 วัน</h2>
    <?php if ($adh === null): ?>
      <p class="text-muted mb-0">ยังไม่มีข้อมูลเพียงพอ</p>
    <?php else: ?>
      <p class="display-6 mb-2"><?= $adh ?>%</p>
      <div class="progress" role="progressbar" aria-valuenow="<?= $adh ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: <?= $adh ?>%"></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h5 mb-0">ยาที่กินอยู่</h2>
      <?php if ($canEdit): ?><a class="btn btn-mt btn-sm" href="add_prescription.php?patient_id=<?= $id ?>">+ เพิ่มยา</a><?php endif; ?>
    </div>
    <?php if (!$prescriptions): ?>
      <p class="text-muted mb-0">ยังไม่มีรายการยา</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>ยา</th><th>ขนาด</th><th>เวลา</th><th>วัน</th><th>คงเหลือ</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($prescriptions as $r): ?>
          <tr>
            <td><?= e($r['med_name']) ?></td>
            <td><?= e($r['dose']) ?> (<?= e($r['dosage_form']) ?>)</td>
            <td><?= e($r['times'] ?? '-') ?></td>
            <td><?= e(day_names($r['days'] ?? '')) ?></td>
            <td><?= (int)$r['stock_left'] ?></td>
            <?php if ($canEdit): ?>
            <td>
              <form method="post" action="delete_prescription.php" onsubmit="return confirm('ลบยานี้ใช่ไหม?')">
                <?= csrf_field() ?>
                <input type="hidden" name="prescription_id" value="<?= (int)$r['prescription_id'] ?>">
                <input type="hidden" name="patient_id" value="<?= $id ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">ลบ</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h2 class="h5">ประวัติ 7 วันที่ผ่านมา</h2>
    <?php if (!$history): ?>
      <p class="text-muted mb-0">ยังไม่มีประวัติ</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>กำหนด</th><th>ยา</th><th>สถานะ</th><th>เวลาที่กินจริง</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><?= e(date('d/m H:i', strtotime($h['scheduled_at']))) ?></td>
            <td><?= e($h['med_name']) ?></td>
            <td><span class="badge <?= $h['status'] === 'taken' ? 'text-bg-success' : ($h['status'] === 'missed' ? 'text-bg-danger' : 'text-bg-secondary') ?>">
              <?= e(status_label($h['status'])) ?></span></td>
            <td><?= $h['taken_at'] ? e(date('H:i', strtotime($h['taken_at']))) : '-' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<a href="<?= $u['role'] === 'caregiver' ? 'caregiver.php' : 'patient.php' ?>">← กลับ</a>
<?php include __DIR__ . '/includes/footer.php'; ?>
