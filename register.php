<?php
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

    if ($old['name'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณากรอกชื่อและอีเมลให้ถูกต้อง';
    } elseif (strlen($pass) < 6) {
        $error = 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $chk->execute([$old['email']]);
        if ($chk->fetch()) {
            $error = 'อีเมลนี้ถูกใช้แล้ว';
        } else {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
                ->execute([$old['name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT), $old['role']]);
            flash('สมัครสมาชิกสำเร็จ เข้าสู่ระบบได้เลย');
            redirect('login.php');
        }
    }
}

$pageTitle = 'สมัครสมาชิก';
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h1>สมัครสมาชิก</h1>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>ชื่อ <input type="text" name="name" value="<?= e($old['name']) ?>" required></label>
    <label>อีเมล <input type="email" name="email" value="<?= e($old['email']) ?>" required></label>
    <label>รหัสผ่าน (อย่างน้อย 6 ตัว) <input type="password" name="password" required></label>
    <label>ฉันคือ
      <select name="role">
        <option value="caregiver" <?= $old['role'] === 'caregiver' ? 'selected' : '' ?>>ลูกหลาน / ผู้ดูแล</option>
        <option value="patient" <?= $old['role'] === 'patient' ? 'selected' : '' ?>>ผู้สูงอายุ / ผู้ที่ต้องกินยา</option>
      </select>
    </label>
    <button class="btn primary" type="submit">สมัครสมาชิก</button>
  </form>
  <p class="muted">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
