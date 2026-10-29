<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $q = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $q->execute([$email]);
    $user = $q->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'user_id' => (int)$user['user_id'],
            'name'    => $user['name'],
            'role'    => $user['role'],
        ];
        redirect('index.php');
    }
    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}

$pageTitle = 'เข้าสู่ระบบ';
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h1>เข้าสู่ระบบ</h1>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>อีเมล <input type="email" name="email" required autofocus></label>
    <label>รหัสผ่าน <input type="password" name="password" required></label>
    <button class="btn primary" type="submit">เข้าสู่ระบบ</button>
  </form>
  <p class="muted">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
