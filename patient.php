<?php
// หน้าจอสำหรับผู้สูงอายุ: ปุ่มใหญ่ อ่านง่าย กด "กินแล้ว" ได้ทันที
require_once __DIR__ . '/includes/functions.php';

$u = require_login('patient');
generate_logs_for_today($pdo);
mark_missed($pdo);

$q = $pdo->prepare("SELECT l.log_id, l.scheduled_at, l.taken_at, l.status,
                           m.name AS med_name, m.dosage_form, p.dose
                    FROM intake_logs l
                    JOIN schedules s ON s.schedule_id = l.schedule_id
                    JOIN prescriptions p ON p.prescription_id = s.prescription_id
                    JOIN medicines m ON m.medicine_id = p.medicine_id
                    WHERE p.patient_id = ? AND DATE(l.scheduled_at) = CURDATE()
                    ORDER BY l.scheduled_at");
$q->execute([$u['user_id']]);
$logs = $q->fetchAll();

$pageTitle = 'ยาวันนี้';
$bodyClass = 'senior';
include __DIR__ . '/includes/header.php';
?>
<h1>สวัสดี <?= e($u['name']) ?></h1>
<p class="big muted">ยาของวันนี้ <?= date('d/m/Y') ?></p>

<?php if (!$logs): ?>
  <div class="card">วันนี้ไม่มีรายการยา
    <a href="patient_detail.php?id=<?= (int)$u['user_id'] ?>">เพิ่มยาของฉัน</a>
  </div>
<?php endif; ?>

<?php foreach ($logs as $l): ?>
  <section class="card dose <?= e($l['status']) ?>">
    <div class="dose-time"><?= date('H:i', strtotime($l['scheduled_at'])) ?> น.</div>
    <div class="dose-info">
      <strong><?= e($l['med_name']) ?></strong>
      <span><?= e($l['dose']) ?> (<?= e($l['dosage_form']) ?>)</span>
    </div>
    <?php if ($l['status'] === 'taken'): ?>
      <div class="badge ok">✔ กินแล้ว <?= date('H:i', strtotime($l['taken_at'])) ?> น.</div>
    <?php else: ?>
      <?php if ($l['status'] === 'missed'): ?><div class="badge bad">เลยเวลาแล้ว</div><?php endif; ?>
      <form method="post" action="take.php">
        <?= csrf_field() ?>
        <input type="hidden" name="log_id" value="<?= (int)$l['log_id'] ?>">
        <input type="hidden" name="back" value="patient.php">
        <button class="btn primary huge" type="submit">✔ กินแล้ว</button>
      </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
