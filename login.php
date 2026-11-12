<?php
// เข้าสู่ระบบ: ตรวจอีเมล + รหัสผ่าน (password_verify) แล้วเก็บข้อมูลผู้ใช้ใน Session
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $user = db_row($conn, 'SELECT user_id, name, password_hash, role FROM project_users WHERE email = ? LIMIT 1', 's', [$email]);

    if ($user && password_verify($pass, $user['password_hash'])) {
        session_regenerate_id(true); // ป้องกัน Session Fixation
        $_SESSION['user'] = [
            'user_id' => (int)$user['user_id'],
            'name'    => $user['name'],
            'role'    => $user['role'],
        ];
        redirect('index.php');
    }
    // ไม่บอกว่าผิดที่อีเมลหรือรหัสผ่าน
    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}

$pageTitle = 'เข้าสู่ระบบ';
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h3 mb-3">เข้าสู่ระบบ</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="email">อีเมล</label>
            <input class="form-control" type="email" id="email" name="email" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">รหัสผ่าน</label>
            <input class="form-control" type="password" id="password" name="password" required>
          </div>
          <button class="btn btn-mt w-100" type="submit">เข้าสู่ระบบ</button>
        </form>
        <p class="text-muted mt-3 mb-0">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
