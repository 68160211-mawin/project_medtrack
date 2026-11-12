<?php
// เพิ่มยาให้ผู้ป่วย พร้อมกำหนดเวลาที่ต้องกิน (ข้อมูลผู้ใช้กรอกเอง ระบบไม่ให้คำแนะนำขนาดยา)
require_once __DIR__ . '/includes/functions.php';

$u = require_login($conn);
$patientId = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
if (!can_access_patient($conn, $u['user_id'], $patientId, true)) {
    forbidden('ไม่มีสิทธิ์เพิ่มยาให้บุคคลนี้');
}

// ตรวจรูปแบบวันที่ YYYY-MM-DD และเป็นวันที่จริง
function valid_date(?string $d): bool
{
    return is_string($d) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

$error = '';
$medicines = db_all($conn, 'SELECT medicine_id, name, dosage_form FROM project_medicines ORDER BY name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $medicineId = (int)($_POST['medicine_id'] ?? 0);
    $newMed     = trim($_POST['new_medicine'] ?? '');
    $dose       = trim($_POST['dose'] ?? '');
    $start      = $_POST['start_date'] ?? '';
    $end        = ($_POST['end_date'] ?? '') !== '' ? $_POST['end_date'] : null;
    $stock      = max(0, (int)($_POST['stock_left'] ?? 0));
    $days       = array_values(array_unique(array_filter(array_map('intval', $_POST['days'] ?? []), fn($d) => $d >= 1 && $d <= 7)));
    $times      = array_values(array_unique(array_filter($_POST['times'] ?? [],
                    fn($t) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t))));
    $times      = array_slice($times, 0, 4);

    if ($newMed === '' && $medicineId === 0) {
        $error = 'กรุณาเลือกยา หรือพิมพ์ชื่อยาใหม่';
    } elseif ($dose === '') {
        $error = 'กรุณากรอกขนาดที่กินต่อครั้ง (เช่น 1 เม็ด)';
    } elseif (!$times) {
        $error = 'กรุณากำหนดเวลากินอย่างน้อย 1 เวลา';
    } elseif (!$days) {
        $error = 'กรุณาเลือกวันที่ต้องกินอย่างน้อย 1 วัน';
    } elseif (!valid_date($start) || ($end !== null && !valid_date($end))) {
        $error = 'รูปแบบวันที่ไม่ถูกต้อง';
    } elseif ($end !== null && $end < $start) {
        $error = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม';
    } else {
        // เพิ่มยา + เวลากินทั้งหมดใน Transaction เดียว: สำเร็จพร้อมกันหรือยกเลิกทั้งหมด
        $conn->begin_transaction();
        try {
            if ($newMed !== '') {
                $name = mb_substr($newMed, 0, 120);
                db_exec($conn, 'INSERT IGNORE INTO project_medicines (name) VALUES (?)', 's', [$name]);
                $medicineId = (int)db_val($conn, 'SELECT medicine_id FROM project_medicines WHERE name = ?', 's', [$name]);
            } elseif (!db_val($conn, 'SELECT 1 FROM project_medicines WHERE medicine_id = ?', 'i', [$medicineId])) {
                throw new RuntimeException('ไม่พบยาที่เลือก');
            }
            db_exec($conn, 'INSERT INTO project_prescriptions (patient_id, medicine_id, dose, start_date, end_date, stock_left, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?)',
                'iisssii', [$patientId, $medicineId, mb_substr($dose, 0, 60), $start, $end, $stock, $u['user_id']]);
            $rxId = $conn->insert_id;
            foreach ($times as $t) {
                db_exec($conn, 'INSERT INTO project_schedules (prescription_id, time_of_day, days_of_week) VALUES (?, ?, ?)',
                    'iss', [$rxId, $t . ':00', implode(',', $days)]);
            }
            $conn->commit();
            flash('เพิ่มยาเรียบร้อย');
            redirect('patient_detail.php?id=' . $patientId);
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log($ex->getMessage());
            $error = 'บันทึกไม่สำเร็จ กรุณาตรวจข้อมูลแล้วลองใหม่';
        }
    }
}

$pageTitle = 'เพิ่มยา';
include __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h3 mb-3">เพิ่มยา</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="patient_id" value="<?= $patientId ?>">

          <div class="mb-3">
            <label class="form-label" for="medicine_id">เลือกยาจากรายการ</label>
            <select class="form-select" id="medicine_id" name="medicine_id">
              <option value="0">-- เลือก --</option>
              <?php foreach ($medicines as $m): ?>
                <option value="<?= (int)$m['medicine_id'] ?>"><?= e($m['name']) ?> (<?= e($m['dosage_form']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="new_medicine">หรือพิมพ์ชื่อยาใหม่</label>
            <input class="form-control" type="text" id="new_medicine" name="new_medicine" maxlength="120">
          </div>
          <div class="mb-3">
            <label class="form-label" for="dose">ขนาดที่กินต่อครั้ง (ตามฉลากยา/ที่แพทย์สั่ง)</label>
            <input class="form-control" type="text" id="dose" name="dose" maxlength="60" placeholder="1 เม็ด" required>
          </div>

          <div class="mb-3">
            <label class="form-label">เวลาที่ต้องกิน (ใส่ได้สูงสุด 4 เวลา)</label>
            <div class="row g-2">
              <div class="col-6 col-md-3"><input class="form-control" type="time" name="times[]" value="08:00"></div>
              <div class="col-6 col-md-3"><input class="form-control" type="time" name="times[]"></div>
              <div class="col-6 col-md-3"><input class="form-control" type="time" name="times[]"></div>
              <div class="col-6 col-md-3"><input class="form-control" type="time" name="times[]"></div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label d-block">วันที่ต้องกิน</label>
            <?php foreach ([1 => 'จ.', 2 => 'อ.', 3 => 'พ.', 4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.', 7 => 'อา.'] as $n => $label): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="days[]" id="d<?= $n ?>" value="<?= $n ?>" checked>
                <label class="form-check-label" for="d<?= $n ?>"><?= $label ?></label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" for="start_date">วันเริ่ม</label>
              <input class="form-control" type="date" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="end_date">วันสิ้นสุด (ไม่ใส่ = กินต่อเนื่อง)</label>
              <input class="form-control" type="date" id="end_date" name="end_date">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="stock_left">จำนวนยาคงเหลือ (เม็ด)</label>
            <input class="form-control" type="number" id="stock_left" name="stock_left" min="0" value="30">
          </div>

          <button class="btn btn-mt" type="submit">บันทึก</button>
          <a class="btn btn-outline-secondary" href="patient_detail.php?id=<?= $patientId ?>">ยกเลิก</a>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
