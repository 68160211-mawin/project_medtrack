<?php
// สมัครสมาชิก: เลือกบทบาทผู้สูงอายุ (patient) หรือลูกหลาน/ผู้ดูแล (caregiver)
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$old = ['name' => '', 'email' => '', 'role' => 'caregiver'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['role']  = ($_POST['role'] ?? '') === 'patient' ? 'patient' : 'caregiver';
    $pass = $_POST['password'] ?? '';

    if ($old['name'] === '' || mb_strlen($old['name']) > 100 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณากรอกชื่อและอีเมลให้ถูกต้อง';
    } elseif (strlen($pass) < 6) {
        $error = 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร';
    } elseif (db_val($conn, 'SELECT 1 FROM project_users WHERE email = ?', 's', [$old['email']])) {
        $error = 'อีเมลนี้ถูกใช้แล้ว';
    } else {
        try {
            db_exec($conn, 'INSERT INTO project_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                'ssss', [$old['name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT), $old['role']]);
            flash('สมัครสมาชิกสำเร็จ เข้าสู่ระบบได้เลย');
            redirect('login.php');
        } catch (mysqli_sql_exception $ex) {
            // รหัส 1062 = UNIQUE ซ้ำ (กรณีมีคนสมัครอีเมลเดียวกันพร้อมกัน)
            $error = $ex->getCode() === 1062 ? 'อีเมลนี้ถูกใช้แล้ว' : 'สมัครสมาชิกไม่สำเร็จ ลองใหม่อีกครั้ง';
        }
    }
}

$pageTitle = 'สมัครสมาชิก';
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h3 mb-3">สมัครสมาชิก</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="name">ชื่อ</label>
            <input class="form-control" type="text" id="name" name="name" maxlength="100" value="<?= e($old['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">อีเมล</label>
            <input class="form-control" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">รหัสผ่าน (อย่างน้อย 6 ตัว)</label>
            <input class="form-control" type="password" id="password" name="password" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="role">ฉันคือ</label>
            <select class="form-select" id="role" name="role">
              <option value="caregiver" <?= $old['role'] === 'caregiver' ? 'selected' : '' ?>>ลูกหลาน / ผู้ดูแล</option>
              <option value="patient" <?= $old['role'] === 'patient' ? 'selected' : '' ?>>ผู้สูงอายุ / ผู้ที่ต้องกินยา</option>
            </select>
          </div>
          <button class="btn btn-mt w-100" type="submit">สมัครสมาชิก</button>
        </form>
        <p class="text-muted mt-3 mb-0">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
