<?php
// ลูกหลานเชื่อมกับบัญชีผู้สูงอายุ โดยกรอกอีเมลที่ผู้สูงอายุสมัครไว้ (บันทึกลง project_care_links)
require_once __DIR__ . '/includes/functions.php';

$u = require_login($conn, 'caregiver');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $relation = trim($_POST['relation'] ?? '') ?: 'ลูกหลาน';

    $patient = db_row($conn, "SELECT user_id, name FROM project_users WHERE email = ? AND role = 'patient'", 's', [$email]);

    if (!$patient) {
        $error = 'ไม่พบบัญชีผู้สูงอายุด้วยอีเมลนี้ (ให้ผู้สูงอายุสมัครแบบ "ผู้สูงอายุ" ก่อน)';
    } else {
        // INSERT IGNORE + UNIQUE(patient_id, caregiver_id) กันเชื่อมซ้ำ
        $n = db_exec($conn, 'INSERT IGNORE INTO project_care_links (patient_id, caregiver_id, relation) VALUES (?, ?, ?)',
            'iis', [$patient['user_id'], $u['user_id'], mb_substr($relation, 0, 50)]);
        flash($n ? 'เชื่อมกับ ' . $patient['name'] . ' แล้ว' : 'เชื่อมกันไว้อยู่แล้ว');
        redirect('caregiver.php');
    }
}

$pageTitle = 'เพิ่มผู้สูงอายุ';
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h3 mb-3">เพิ่มผู้สูงอายุที่ดูแล</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="email">อีเมลของผู้สูงอายุ</label>
            <input class="form-control" type="email" id="email" name="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="relation">ความสัมพันธ์ (เช่น ลูก หลาน พยาบาล)</label>
            <input class="form-control" type="text" id="relation" name="relation" maxlength="50" placeholder="ลูก">
          </div>
          <button class="btn btn-mt" type="submit">เชื่อมบัญชี</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
