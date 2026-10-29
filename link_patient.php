<?php
// ลูกหลานเชื่อมกับบัญชีผู้สูงอายุ โดยกรอกอีเมลที่ผู้สูงอายุสมัครไว้
require_once __DIR__ . '/includes/functions.php';

$u = require_login('caregiver');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $relation = trim($_POST['relation'] ?? '') ?: 'ลูกหลาน';

    $q = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? AND role = 'patient'");
    $q->execute([$email]);
    $patient = $q->fetch();

    if (!$patient) {
        $error = 'ไม่พบบัญชีผู้สูงอายุด้วยอีเมลนี้ (ให้ผู้สูงอายุสมัครแบบ "ผู้สูงอายุ" ก่อน)';
    } else {
        $ins = $pdo->prepare('INSERT IGNORE INTO care_links (patient_id, caregiver_id, relation) VALUES (?, ?, ?)');
        $ins->execute([$patient['user_id'], $u['user_id'], mb_substr($relation, 0, 50)]);
        flash($ins->rowCount() ? 'เชื่อมกับ ' . $patient['name'] . ' แล้ว' : 'เชื่อมกันไว้อยู่แล้ว');
        redirect('caregiver.php');
    }
}

$pageTitle = 'เพิ่มผู้สูงอายุ';
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h1>เพิ่มผู้สูงอายุที่ดูแล</h1>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>อีเมลของผู้สูงอายุ <input type="email" name="email" required></label>
    <label>ความสัมพันธ์ (เช่น ลูก หลาน พยาบาล)
      <input type="text" name="relation" maxlength="50" placeholder="ลูก"></label>
    <button class="btn primary" type="submit">เชื่อมบัญชี</button>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
