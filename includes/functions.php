<?php
// ฟังก์ชันกลางของ MedTrack: ตัวช่วย MySQLi, Session/สิทธิ์, CSRF และตรรกะหลักของระบบ
// ทุกหน้า PHP เรียกไฟล์นี้ด้วย require_once ที่บรรทัดแรก

require_once __DIR__ . '/settings.php';   // ค่าตั้งต้น + เริ่ม Session
require_once __DIR__ . '/../config/db.php'; // ได้ตัวแปร $conn (mysqli)

// ให้เวลาของฐานข้อมูลตรงกับเวลาไทย (NOW() ใช้เปรียบเทียบมื้อยา)
$conn->query("SET time_zone = '+07:00'");

/* ---------- ตัวช่วยฐานข้อมูล (ทุกคำสั่งใช้ Prepared Statement + bind_param) ---------- */

// รัน SQL แบบ Prepared Statement  $types เช่น 'is' = int, string  คืน mysqli_stmt
function db_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// SELECT หลายแถว คืนเป็นอาร์เรย์ของแถว
function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_query($conn, $sql, $types, $params);
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

// SELECT แถวเดียว (ไม่พบคืน null)
function db_row(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

// SELECT ค่าเดียว (คอลัมน์แรกของแถวแรก)
function db_val(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $row = db_row($conn, $sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

// INSERT / UPDATE / DELETE คืนจำนวนแถวที่ได้รับผลกระทบ (รหัสที่เพิ่งสร้างดูได้จาก $conn->insert_id)
function db_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = db_query($conn, $sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

/* ---------- ตัวช่วยทั่วไป ---------- */

// ป้องกัน XSS: แสดงข้อความทุกครั้งผ่านฟังก์ชันนี้
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// CSRF Token: สร้างค่าสุ่มเก็บใน Session และใส่ในทุกฟอร์ม POST
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('คำขอไม่ถูกต้อง (CSRF)');
    }
}

// ข้อความแจ้งผลครั้งเดียว (Flash message) ใช้คู่กับ Post/Redirect/Get
function flash(?string $msg = null)
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- ผู้ใช้และสิทธิ์ ---------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

// หน้า 403 เมื่อไม่มีสิทธิ์
function forbidden(string $msg = 'ไม่มีสิทธิ์เข้าถึงหน้านี้'): void
{
    http_response_code(403);
    $pageTitle = 'ไม่มีสิทธิ์เข้าถึง';
    include __DIR__ . '/header.php';
    echo '<div class="alert alert-danger">' . e($msg) . '</div>';
    echo '<a class="btn btn-outline-secondary" href="index.php">กลับหน้าแรก</a>';
    include __DIR__ . '/footer.php';
    exit;
}

// ต้องล็อกอินก่อน (และตรงบทบาทถ้าระบุ $role) โหลดบัญชีจากฐานข้อมูลทุกครั้ง
// ถ้าบัญชีถูกลบไปแล้ว จะถูกออกจากระบบทันที
function require_login(mysqli $conn, ?string $role = null): array
{
    $sess = current_user();
    if (!$sess) {
        redirect('login.php');
    }
    $row = db_row($conn, 'SELECT user_id, name, role FROM project_users WHERE user_id = ?', 'i', [(int)$sess['user_id']]);
    if (!$row) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }
    $_SESSION['user'] = ['user_id' => (int)$row['user_id'], 'name' => $row['name'], 'role' => $row['role']];
    if ($role !== null && $row['role'] !== $role) {
        forbidden();
    }
    return $_SESSION['user'];
}

// ผู้ใช้ $uid ดู/แก้ข้อมูลของผู้ป่วย $pid ได้หรือไม่ (เจ้าของข้อมูล หรือผู้ดูแลที่เชื่อมไว้)
function can_access_patient(mysqli $conn, int $uid, int $pid, bool $needEdit = false): bool
{
    if ($uid === $pid) {
        return true;
    }
    $r = db_row($conn, 'SELECT can_edit FROM project_care_links WHERE patient_id = ? AND caregiver_id = ?', 'ii', [$pid, $uid]);
    if (!$r) {
        return false;
    }
    return $needEdit ? (bool)$r['can_edit'] : true;
}

function caregivers_of(mysqli $conn, int $patientId): array
{
    $rows = db_all($conn, 'SELECT caregiver_id FROM project_care_links WHERE patient_id = ?', 'i', [$patientId]);
    return array_map('intval', array_column($rows, 'caregiver_id'));
}

/* ---------- ตรรกะหลักของระบบ ---------- */

// สร้างแถว project_intake_logs ของมื้อยาวันนี้ (INSERT IGNORE + UNIQUE KEY กันซ้ำ)
// ไม่สร้างมื้อที่เวลาผ่านไปแล้วก่อนเพิ่มยา เพื่อไม่ให้ถูกนับเป็นข้ามมื้อผิด ๆ
function generate_logs_for_today(mysqli $conn): void
{
    $sql = "INSERT IGNORE INTO project_intake_logs (schedule_id, scheduled_at)
            SELECT s.schedule_id, TIMESTAMP(?, s.time_of_day)
            FROM project_schedules s
            JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
            WHERE p.start_date <= ?
              AND (p.end_date IS NULL OR p.end_date >= ?)
              AND TIMESTAMP(?, s.time_of_day) >= p.created_at
              AND FIND_IN_SET(?, s.days_of_week)";
    $today = date('Y-m-d');
    db_exec($conn, $sql, 'sssss', [$today, $today, $today, $today, (string)date('N')]);
}

// มื้อที่เลยเวลาเกิน MISSED_AFTER_MIN นาทีและยังไม่กิน -> missed + แจ้งเตือนผู้ดูแลทุกคน
function mark_missed(mysqli $conn): int
{
    $sql = "SELECT l.log_id, l.scheduled_at, p.patient_id, u.name AS patient_name, m.name AS med_name
            FROM project_intake_logs l
            JOIN project_schedules s ON s.schedule_id = l.schedule_id
            JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
            JOIN project_medicines m ON m.medicine_id = p.medicine_id
            JOIN project_users u ON u.user_id = p.patient_id
            WHERE l.status = 'pending'
              AND l.scheduled_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    $rows = db_all($conn, $sql, 'i', [MISSED_AFTER_MIN]);

    foreach ($rows as $r) {
        $conn->begin_transaction();
        try {
            // เปลี่ยนสถานะเฉพาะที่ยัง pending (กันการทำงานซ้อนกันสร้างแจ้งเตือนซ้ำ)
            $n = db_exec($conn, "UPDATE project_intake_logs SET status = 'missed' WHERE log_id = ? AND status = 'pending'", 'i', [$r['log_id']]);
            if ($n === 1) {
                $msg = sprintf('%s ยังไม่ได้กิน %s (กำหนด %s น.)',
                    $r['patient_name'], $r['med_name'], date('H:i', strtotime($r['scheduled_at'])));
                foreach (caregivers_of($conn, (int)$r['patient_id']) as $cid) {
                    db_exec($conn, "INSERT INTO project_alerts (caregiver_id, patient_id, log_id, type, message) VALUES (?, ?, ?, 'missed', ?)",
                        'iiis', [$cid, $r['patient_id'], $r['log_id'], $msg]);
                }
            }
            $conn->commit();
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            error_log($ex->getMessage());
        }
    }
    return count($rows);
}

// กดว่า "กินแล้ว": อัปเดตสถานะ ตัดสต็อก 1 และเช็คยาใกล้หมด (ทั้งหมดอยู่ใน Transaction เดียว)
function take_dose(mysqli $conn, int $logId, int $uid): bool
{
    $log = db_row($conn, "SELECT l.log_id, l.status, p.prescription_id, p.patient_id
                          FROM project_intake_logs l
                          JOIN project_schedules s ON s.schedule_id = l.schedule_id
                          JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
                          WHERE l.log_id = ?", 'i', [$logId]);
    if (!$log || !can_access_patient($conn, $uid, (int)$log['patient_id'])) {
        return false;
    }
    if ($log['status'] === 'taken') {
        return true;
    }
    $conn->begin_transaction();
    try {
        // เงื่อนไข status <> 'taken' กันกดซ้ำสองครั้งแล้วตัดสต็อกสองรอบ
        $n = db_exec($conn, "UPDATE project_intake_logs SET status = 'taken', taken_at = NOW() WHERE log_id = ? AND status <> 'taken'", 'i', [$logId]);
        if ($n === 1) {
            db_exec($conn, 'UPDATE project_prescriptions SET stock_left = GREATEST(stock_left - 1, 0) WHERE prescription_id = ?', 'i', [$log['prescription_id']]);
            check_low_stock($conn, (int)$log['prescription_id']);
        }
        $conn->commit();
        return true;
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        error_log($ex->getMessage());
        return false;
    }
}

// ถ้ายาเหลือไม่เกิน LOW_STOCK_DAYS วัน แจ้งเตือนผู้ดูแล (ไม่ซ้ำถ้ายังมีแจ้งเตือนเดิมที่ยังไม่อ่าน)
function check_low_stock(mysqli $conn, int $prescriptionId): void
{
    $p = db_row($conn, "SELECT p.stock_left, p.patient_id, u.name AS patient_name, m.name AS med_name,
                               (SELECT COUNT(*) FROM project_schedules s WHERE s.prescription_id = p.prescription_id) AS per_day
                        FROM project_prescriptions p
                        JOIN project_users u ON u.user_id = p.patient_id
                        JOIN project_medicines m ON m.medicine_id = p.medicine_id
                        WHERE p.prescription_id = ?", 'i', [$prescriptionId]);
    if (!$p || (int)$p['per_day'] === 0) {
        return;
    }
    if ((int)$p['stock_left'] > (int)$p['per_day'] * LOW_STOCK_DAYS) {
        return;
    }
    $days = intdiv((int)$p['stock_left'], (int)$p['per_day']);
    $msg  = sprintf('%s: %s เหลือประมาณ %d วัน (%d เม็ด) ควรไปรับยาเพิ่ม',
        $p['patient_name'], $p['med_name'], $days, $p['stock_left']);

    foreach (caregivers_of($conn, (int)$p['patient_id']) as $cid) {
        $dup = db_val($conn, "SELECT 1 FROM project_alerts WHERE prescription_id = ? AND type = 'low_stock' AND caregiver_id = ? AND is_read = 0",
            'ii', [$prescriptionId, $cid]);
        if (!$dup) {
            db_exec($conn, "INSERT INTO project_alerts (caregiver_id, patient_id, prescription_id, type, message) VALUES (?, ?, ?, 'low_stock', ?)",
                'iiis', [$cid, $p['patient_id'], $prescriptionId, $msg]);
        }
    }
}

// % การกินครบในช่วง $days วันที่ผ่านมา (คืน null ถ้ายังไม่มีข้อมูล)
function adherence_percent(mysqli $conn, int $patientId, int $days = 7): ?int
{
    $r = db_row($conn, "SELECT SUM(l.status = 'taken') AS taken, SUM(l.status IN ('taken','missed')) AS total
                        FROM project_intake_logs l
                        JOIN project_schedules s ON s.schedule_id = l.schedule_id
                        JOIN project_prescriptions p ON p.prescription_id = s.prescription_id
                        WHERE p.patient_id = ?
                          AND l.scheduled_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", 'ii', [$patientId, $days]);
    $total = (int)($r['total'] ?? 0);
    return $total === 0 ? null : (int)round(100 * (int)$r['taken'] / $total);
}

function status_label(string $s): string
{
    return ['pending' => 'รอกิน', 'taken' => 'กินแล้ว', 'missed' => 'ข้ามมื้อ', 'skipped' => 'งดยา'][$s] ?? $s;
}

// แปลง '1,2,3' เป็น 'จ. อ. พ.' (ครบ 7 วัน = ทุกวัน)
function day_names(string $csv): string
{
    $map = [1 => 'จ.', 2 => 'อ.', 3 => 'พ.', 4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.', 7 => 'อา.'];
    $d = array_filter(explode(',', $csv));
    if (count($d) === 7) {
        return 'ทุกวัน';
    }
    return implode(' ', array_map(fn($n) => $map[(int)$n] ?? '', $d));
}
