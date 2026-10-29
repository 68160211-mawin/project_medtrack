<?php
require_once __DIR__ . '/functions.php';
$u = current_user();
$pageTitle = $pageTitle ?? 'MedTrack';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | MedTrack</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="topbar">
  <a class="brand" href="index.php">💊 MedTrack</a>
  <?php if ($u): ?>
    <nav>
      <?php if ($u['role'] === 'caregiver'): ?>
        <a href="caregiver.php">ผู้ที่ดูแล</a>
        <a href="link_patient.php">เพิ่มผู้สูงอายุ</a>
      <?php else: ?>
        <a href="patient.php">ยาวันนี้</a>
        <a href="patient_detail.php?id=<?= (int)$u['user_id'] ?>">ยาของฉัน</a>
      <?php endif; ?>
      <span class="who"><?= e($u['name']) ?></span>
      <a href="logout.php">ออกจากระบบ</a>
    </nav>
  <?php endif; ?>
</header>
<main class="container">
<?php if ($m = flash()): ?>
  <div class="notice"><?= e($m) ?></div>
<?php endif; ?>
