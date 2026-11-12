<?php
// ส่วนหัวของทุกหน้า: Bootstrap 5 (CDN) + เมนูตามบทบาท + ข้อความแจ้งผล (flash)
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<nav class="navbar navbar-expand-md navbar-dark mt-nav">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">💊 MedTrack</a>
    <?php if ($u): ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="เมนู">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
          <?php if ($u['role'] === 'caregiver'): ?>
            <li class="nav-item"><a class="nav-link" href="caregiver.php">ผู้ที่ดูแล</a></li>
            <li class="nav-item"><a class="nav-link" href="link_patient.php">เพิ่มผู้สูงอายุ</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="patient.php">ยาวันนี้</a></li>
            <li class="nav-item"><a class="nav-link" href="patient_detail.php?id=<?= (int)$u['user_id'] ?>">ยาของฉัน</a></li>
          <?php endif; ?>
        </ul>
        <span class="navbar-text me-3"><?= e($u['name']) ?></span>
        <a class="btn btn-outline-light btn-sm" href="logout.php">ออกจากระบบ</a>
      </div>
    <?php endif; ?>
  </div>
</nav>
<main class="container py-4">
<?php if ($m = flash()): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= e($m) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
  </div>
<?php endif; ?>
