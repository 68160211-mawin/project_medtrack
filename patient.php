<?php
// หน้าสำหรับผู้สูงอายุ: ยาของวันนี้เรียงตามเวลา ปุ่มใหญ่ "กินแล้ว"
require_once __DIR__ . '/includes/functions.php';

$u = require_login($conn, 'patient');
generate_logs_for_today($conn);
mark_missed($conn);

$logs = db_all($conn, "SELECT l.log_id, l.scheduled_at, l.taken_at, l.status,
                              m.name AS med_name, m.dosage_form, p.dose
                       FROM project_intake_logs l
                       JOIN project_schedules s ON s.schedule_id = l.schedule_id
                       JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
                       JOIN project_medicines m ON m.medicine_id = p.medicine_id
                       WHERE p.patient_id = ? AND DATE(l.scheduled_at) = CURDATE()
                       ORDER BY l.scheduled_at", 'i', [$u['user_id']]);

$pageTitle = 'ยาวันนี้';
$bodyClass = 'senior';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h2">สวัสดี <?= e($u['name']) ?></h1>
<p class="fs-5 text-muted">ยาของวันนี้ <?= date('d/m/Y') ?></p>

<?php if (!$logs): ?>
  <div class="card card-body">
    วันนี้ไม่มีรายการยา
    <a href="patient_detail.php?id=<?= (int)$u['user_id'] ?>">เพิ่มยาของฉัน</a>
  </div>
<?php endif; ?>

<?php foreach ($logs as $l): ?>
  <div class="card dose <?= e($l['status']) ?> mb-3 shadow-sm">
    <div class="card-body">
      <div class="row align-items-center g-3">
        <div class="col-sm-3 dose-time"><?= date('H:i', strtotime($l['scheduled_at'])) ?> น.</div>
        <div class="col-sm-9">
          <div class="dose-med"><?= e($l['med_name']) ?></div>
          <div class="text-muted"><?= e($l['dose']) ?> (<?= e($l['dosage_form']) ?>)</div>
        </div>
        <div class="col-12">
          <?php if ($l['status'] === 'taken'): ?>
            <span class="badge text-bg-success fs-6">✔ กินแล้ว <?= date('H:i', strtotime($l['taken_at'])) ?> น.</span>
          <?php else: ?>
            <?php if ($l['status'] === 'missed'): ?><span class="badge text-bg-danger fs-6 mb-2">เลยเวลาแล้ว</span><?php endif; ?>
            <form method="post" action="take.php">
              <?= csrf_field() ?>
              <input type="hidden" name="log_id" value="<?= (int)$l['log_id'] ?>">
              <input type="hidden" name="back" value="patient.php">
              <button class="btn btn-mt btn-huge" type="submit">✔ กินแล้ว</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
