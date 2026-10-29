<?php
// เพิ่มยาให้ผู้ป่วย พร้อมกำหนดเวลาที่ต้องกิน (ข้อมูลผู้ใช้กรอกเอง ระบบไม่ให้คำแนะนำขนาดยา)
require_once __DIR__ . '/includes/functions.php';

$u = require_login();
$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
if (!can_access_patient($pdo, $u['user_id'], $patientId, true)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เพิ่มยาให้บุคคลนี้');
}

$error = '';
$medicines = $pdo->query('SELECT medicine_id, name, dosage_form FROM medicines ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $medicineId = (int)($_POST['medicine_id'] ?? 0);
    $newMed     = trim($_POST['new_medicine'] ?? '');
    $dose       = trim($_POST['dose'] ?? '');
    $start      = $_POST['start_date'] ?? date('Y-m-d');
    $end        = ($_POST['end_date'] ?? '') !== '' ? $_POST['end_date'] : null;
    $stock      = max(0, (int)($_POST['stock_left'] ?? 0));
    $days       = array_values(array_filter(array_map('intval', $_POST['days'] ?? []), fn($d) => $d >= 1 && $d <= 7));
    $times      = array_values(array_unique(array_filter($_POST['times'] ?? [],
                    fn($t) => preg_match('/^\d{2}:\d{2}$/', $t))));

    if ($newMed === '' && $medicineId === 0) {
        $error = 'กรุณาเลือกยา หรือพิมพ์ชื่อยาใหม่';
    } elseif ($dose === '') {
        $error = 'กรุณากรอกขนาดที่กินต่อครั้ง (เช่น 1 เม็ด)';
    } elseif (!$times) {
        $error = 'กรุณากำหนดเวลากินอย่างน้อย 1 เวลา';
    } elseif (!$days) {
        $error = 'กรุณาเลือกวันที่ต้องกินอย่างน้อย 1 วัน';
    } elseif ($end !== null && $end < $start) {
        $error = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม';
    } else {
        $pdo->beginTransaction();
        if ($newMed !== '') {
            $pdo->prepare('INSERT IGNORE INTO medicines (name) VALUES (?)')->execute([mb_substr($newMed, 0, 120)]);
            $f = $pdo->prepare('SELECT medicine_id FROM medicines WHERE name = ?');
            $f->execute([mb_substr($newMed, 0, 120)]);
            $medicineId = (int)$f->fetchColumn();
        }
        $pdo->prepare('INSERT INTO prescriptions (patient_id, medicine_id, dose, start_date, end_date, stock_left, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$patientId, $medicineId, mb_substr($dose, 0, 60), $start, $end, $stock, $u['user_id']]);
        $rxId = (int)$pdo->lastInsertId();
        $s = $pdo->prepare('INSERT INTO schedules (prescription_id, time_of_day, days_of_week) VALUES (?, ?, ?)');
        foreach ($times as $t) {
            $s->execute([$rxId, $t . ':00', implode(',', $days)]);
        }
        $pdo->commit();
        flash('เพิ่มยาเรียบร้อย');
        redirect('patient_detail.php?id=' . $patientId);
    }
}

$pageTitle = 'เพิ่มยา';
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h1>เพิ่มยา</h1>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">

    <label>เลือกยาจากรายการ
      <select name="medicine_id">
        <option value="0">-- เลือก --</option>
        <?php foreach ($medicines as $m): ?>
          <option value="<?= (int)$m['medicine_id'] ?>"><?= e($m['name']) ?> (<?= e($m['dosage_form']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>หรือพิมพ์ชื่อยาใหม่ <input type="text" name="new_medicine" maxlength="120"></label>
    <label>ขนาดที่กินต่อครั้ง (ตามฉลากยา/ที่แพทย์สั่ง) <input type="text" name="dose" maxlength="60" placeholder="1 เม็ด" required></label>

    <fieldset>
      <legend>เวลาที่ต้องกิน (ใส่ได้สูงสุด 4 เวลา)</legend>
      <input type="time" name="times[]" value="08:00">
      <input type="time" name="times[]">
      <input type="time" name="times[]">
      <input type="time" name="times[]">
    </fieldset>

    <fieldset>
      <legend>วันที่ต้องกิน</legend>
      <?php foreach ([1 => 'จ.', 2 => 'อ.', 3 => 'พ.', 4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.', 7 => 'อา.'] as $n => $label): ?>
        <label class="inline"><input type="checkbox" name="days[]" value="<?= $n ?>" checked> <?= $label ?></label>
      <?php endforeach; ?>
    </fieldset>

    <label>วันเริ่ม <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label>
    <label>วันสิ้นสุด (ไม่ใส่ = กินต่อเนื่อง) <input type="date" name="end_date"></label>
    <label>จำนวนยาคงเหลือ (เม็ด) <input type="number" name="stock_left" min="0" value="30"></label>

    <button class="btn primary" type="submit">บันทึก</button>
    <a class="btn" href="patient_detail.php?id=<?= $patientId ?>">ยกเลิก</a>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
