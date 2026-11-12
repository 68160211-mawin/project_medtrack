<?php
// แดชบอร์ดของลูกหลาน: สถานะผู้สูงอายุที่ดูแลทั้งหมด + การแจ้งเตือนที่ยังไม่อ่าน
require_once __DIR__ . '/includes/functions.php';

$u = require_login($conn, 'caregiver');

// รับทราบการแจ้งเตือน (ทีละรายการ หรือทั้งหมด) แล้ว redirect กลับ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['read_all'])) {
        db_exec($conn, 'UPDATE project_alerts SET is_read = 1 WHERE caregiver_id = ?', 'i', [$u['user_id']]);
    } elseif (isset($_POST['alert_id'])) {
        db_exec($conn, 'UPDATE project_alerts SET is_read = 1 WHERE alert_id = ? AND caregiver_id = ?', 'ii', [(int)$_POST['alert_id'], $u['user_id']]);
    }
    redirect('caregiver.php');
}

generate_logs_for_today($conn);
mark_missed($conn);

$patients = db_all($conn, "SELECT u.user_id, u.name, c.relation
                           FROM project_care_links c JOIN project_users u ON u.user_id = c.patient_id
                           WHERE c.caregiver_id = ? ORDER BY u.name", 'i', [$u['user_id']]);

$alerts = db_all($conn, "SELECT alert_id, type, message, sent_at FROM project_alerts
                         WHERE caregiver_id = ? AND is_read = 0 ORDER BY sent_at DESC LIMIT 20", 'i', [$u['user_id']]);

$pageTitle = 'ผู้ที่ดูแล';
include __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-3">ผู้สูงอายุที่ดูแล</h1>

<?php if ($alerts): ?>
<div class="card border-warning bg-warning-subtle mb-4">
  <div class="card-body">
    <h2 class="h5">🔔 การแจ้งเตือน (<?= count($alerts) ?>)</h2>
    <?php foreach ($alerts as $a): ?>
      <div class="alert-row <?= e($a['type']) ?>">
        <span><?= $a['type'] === 'missed' ? '⚠️' : '📦' ?> <?= e($a['message']) ?>
          <small class="text-muted"><?= e(date('d/m H:i', strtotime($a['sent_at']))) ?></small></span>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="alert_id" value="<?= (int)$a['alert_id'] ?>">
          <button class="btn btn-sm btn-outline-secondary" type="submit">รับทราบ</button>
        </form>
      </div>
    <?php endforeach; ?>
    <form method="post" class="mt-3">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-outline-secondary" name="read_all" value="1">รับทราบทั้งหมด</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!$patients): ?>
  <div class="card card-body">ยังไม่ได้เชื่อมกับผู้สูงอายุคนใด <a href="link_patient.php">เพิ่มผู้สูงอายุ</a></div>
<?php endif; ?>

<div class="row g-3">
<?php foreach ($patients as $p):
    $t = db_row($conn, "SELECT COALESCE(SUM(l.status = 'taken'), 0) AS taken, COALESCE(SUM(l.status = 'missed'), 0) AS missed, COUNT(*) AS total
                        FROM project_intake_logs l
                        JOIN project_schedules s ON s.schedule_id = l.schedule_id
                        JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
                        WHERE p.patient_id = ? AND DATE(l.scheduled_at) = CURDATE()", 'i', [$p['user_id']]);
    $adh = adherence_percent($conn, (int)$p['user_id'], 7);
?>
  <div class="col-md-6 col-lg-4">
    <a class="card patient-card h-100 shadow-sm" href="patient_detail.php?id=<?= (int)$p['user_id'] ?>">
      <div class="card-body">
        <h2 class="h5"><?= e($p['name']) ?> <small class="text-muted">(<?= e($p['relation']) ?>)</small></h2>
        <p class="mb-2">วันนี้กินแล้ว <strong><?= (int)$t['taken'] ?>/<?= (int)$t['total'] ?></strong> มื้อ
          <?php if ((int)$t['missed'] > 0): ?><span class="badge text-bg-danger">ข้าม <?= (int)$t['missed'] ?></span><?php endif; ?></p>
        <p class="mb-1">ความสม่ำเสมอ 7 วัน:
          <?php if ($adh === null): ?><span class="text-muted">ยังไม่มีข้อมูล</span><?php else: ?><strong><?= $adh ?>%</strong><?php endif; ?></p>
        <?php if ($adh !== null): ?>
          <div class="progress" role="progressbar" aria-valuenow="<?= $adh ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= $adh ?>%"></div>
          </div>
        <?php endif; ?>
      </div>
    </a>
  </div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
