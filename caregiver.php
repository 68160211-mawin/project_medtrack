<?php
// แดชบอร์ดของลูกหลาน: ดูสถานะผู้สูงอายุที่ดูแลทั้งหมด + การแจ้งเตือน
require_once __DIR__ . '/includes/functions.php';

$u = require_login('caregiver');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['read_all'])) {
        $pdo->prepare('UPDATE alerts SET is_read = 1 WHERE caregiver_id = ?')->execute([$u['user_id']]);
    } elseif (isset($_POST['alert_id'])) {
        $pdo->prepare('UPDATE alerts SET is_read = 1 WHERE alert_id = ? AND caregiver_id = ?')
            ->execute([(int)$_POST['alert_id'], $u['user_id']]);
    }
    redirect('caregiver.php');
}

generate_logs_for_today($pdo);
mark_missed($pdo);

$q = $pdo->prepare("SELECT u.user_id, u.name, c.relation
                    FROM care_links c JOIN users u ON u.user_id = c.patient_id
                    WHERE c.caregiver_id = ? ORDER BY u.name");
$q->execute([$u['user_id']]);
$patients = $q->fetchAll();

$today = $pdo->prepare("SELECT SUM(l.status = 'taken') AS taken, SUM(l.status = 'missed') AS missed, COUNT(*) AS total
                        FROM intake_logs l
                        JOIN schedules s ON s.schedule_id = l.schedule_id
                        JOIN prescriptions p ON p.prescription_id = s.prescription_id
                        WHERE p.patient_id = ? AND DATE(l.scheduled_at) = CURDATE()");

$al = $pdo->prepare("SELECT a.alert_id, a.type, a.message, a.sent_at
                     FROM alerts a WHERE a.caregiver_id = ? AND a.is_read = 0
                     ORDER BY a.sent_at DESC LIMIT 20");
$al->execute([$u['user_id']]);
$alerts = $al->fetchAll();

$pageTitle = 'ผู้ที่ดูแล';
include __DIR__ . '/includes/header.php';
?>
<h1>ผู้สูงอายุที่ดูแล</h1>

<?php if ($alerts): ?>
<section class="card alerts">
  <h2>🔔 การแจ้งเตือน (<?= count($alerts) ?>)</h2>
  <?php foreach ($alerts as $a): ?>
    <div class="alert-row <?= e($a['type']) ?>">
      <span><?= $a['type'] === 'missed' ? '⚠️' : '📦' ?> <?= e($a['message']) ?>
        <small class="muted"><?= e(date('d/m H:i', strtotime($a['sent_at']))) ?></small></span>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="alert_id" value="<?= (int)$a['alert_id'] ?>">
        <button class="btn small" type="submit">รับทราบ</button>
      </form>
    </div>
  <?php endforeach; ?>
  <form method="post"><?= csrf_field() ?><button class="btn small" name="read_all" value="1">รับทราบทั้งหมด</button></form>
</section>
<?php endif; ?>

<?php if (!$patients): ?>
  <div class="card">ยังไม่ได้เชื่อมกับผู้สูงอายุคนใด <a href="link_patient.php">เพิ่มผู้สูงอายุ</a></div>
<?php endif; ?>

<div class="grid">
<?php foreach ($patients as $p):
    $today->execute([$p['user_id']]);
    $t = $today->fetch();
    $total = (int)$t['total'];
    $taken = (int)$t['taken'];
    $adh = adherence_percent($pdo, (int)$p['user_id'], 7);
?>
  <a class="card patient-card" href="patient_detail.php?id=<?= (int)$p['user_id'] ?>">
    <h2><?= e($p['name']) ?> <small class="muted">(<?= e($p['relation']) ?>)</small></h2>
    <p>วันนี้กินแล้ว <strong><?= $taken ?>/<?= $total ?></strong> มื้อ
       <?php if ((int)$t['missed'] > 0): ?><span class="badge bad">ข้าม <?= (int)$t['missed'] ?></span><?php endif; ?></p>
    <p>ความสม่ำเสมอ 7 วัน:
      <?php if ($adh === null): ?><span class="muted">ยังไม่มีข้อมูล</span>
      <?php else: ?><strong><?= $adh ?>%</strong>
        <span class="bar"><span style="width: <?= $adh ?>%"></span></span><?php endif; ?></p>
  </a>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
