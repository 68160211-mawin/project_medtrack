<?php
// รายละเอียดผู้ป่วย: ยาที่กิน เวลา สต็อก ประวัติ 7 วัน (ใช้ทั้งผู้สูงอายุดูของตัวเองและลูกหลานดู)
require_once __DIR__ . '/includes/functions.php';

$u  = require_login();
$id = (int)($_GET['id'] ?? 0);
if (!can_access_patient($pdo, $u['user_id'], $id)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์ดูข้อมูลนี้');
}
$canEdit = can_access_patient($pdo, $u['user_id'], $id, true);

generate_logs_for_today($pdo);
mark_missed($pdo);

$pt = $pdo->prepare('SELECT name FROM users WHERE user_id = ?');
$pt->execute([$id]);
$patientName = $pt->fetchColumn();

$rx = $pdo->prepare("SELECT p.prescription_id, p.dose, p.start_date, p.end_date, p.stock_left,
                            m.name AS med_name, m.dosage_form,
                            GROUP_CONCAT(TIME_FORMAT(s.time_of_day, '%H:%i') ORDER BY s.time_of_day SEPARATOR ', ') AS times,
                            MIN(s.days_of_week) AS days
                     FROM prescriptions p
                     JOIN medicines m ON m.medicine_id = p.medicine_id
                     LEFT JOIN schedules s ON s.prescription_id = p.prescription_id
                     WHERE p.patient_id = ?
                       AND (p.end_date IS NULL OR p.end_date >= CURDATE())
                     GROUP BY p.prescription_id
                     ORDER BY m.name");
$rx->execute([$id]);
$prescriptions = $rx->fetchAll();

$hist = $pdo->prepare("SELECT l.scheduled_at, l.taken_at, l.status, m.name AS med_name
                       FROM intake_logs l
                       JOIN schedules s ON s.schedule_id = l.schedule_id
                       JOIN prescriptions p ON p.prescription_id = s.prescription_id
                       JOIN medicines m ON m.medicine_id = p.medicine_id
                       WHERE p.patient_id = ? AND l.scheduled_at >= (NOW() - INTERVAL 7 DAY)
                       ORDER BY l.scheduled_at DESC LIMIT 100");
$hist->execute([$id]);
$history = $hist->fetchAll();

$adh = adherence_percent($pdo, $id, 7);

$pageTitle = 'ข้อมูลยา ' . $patientName;
include __DIR__ . '/includes/header.php';
?>
<h1><?= e($patientName) ?></h1>

<section class="card">
  <h2>ความสม่ำเสมอใน 7 วัน</h2>
  <?php if ($adh === null): ?>
    <p class="muted">ยังไม่มีข้อมูลเพียงพอ</p>
  <?php else: ?>
    <p><strong class="big"><?= $adh ?>%</strong> <span class="bar wide"><span style="width: <?= $adh ?>%"></span></span></p>
  <?php endif; ?>
</section>

<section class="card">
  <div class="row-between">
    <h2>ยาที่กินอยู่</h2>
    <?php if ($canEdit): ?>
      <a class="btn primary" href="add_prescription.php?patient_id=<?= $id ?>">+ เพิ่มยา</a>
    <?php endif; ?>
  </div>
  <?php if (!$prescriptions): ?>
    <p class="muted">ยังไม่มีรายการยา</p>
  <?php else: ?>
  <table>
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
            <button class="btn small danger" type="submit">ลบ</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2>ประวัติ 7 วันที่ผ่านมา</h2>
  <?php if (!$history): ?>
    <p class="muted">ยังไม่มีประวัติ</p>
  <?php else: ?>
  <table>
    <thead><tr><th>กำหนด</th><th>ยา</th><th>สถานะ</th><th>เวลาที่กินจริง</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr>
        <td><?= e(date('d/m H:i', strtotime($h['scheduled_at']))) ?></td>
        <td><?= e($h['med_name']) ?></td>
        <td><span class="badge <?= $h['status'] === 'taken' ? 'ok' : ($h['status'] === 'missed' ? 'bad' : '') ?>">
          <?= e(status_label($h['status'])) ?></span></td>
        <td><?= $h['taken_at'] ? e(date('H:i', strtotime($h['taken_at']))) : '-' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<p><a href="<?= $u['role'] === 'caregiver' ? 'caregiver.php' : 'patient.php' ?>">← กลับ</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
