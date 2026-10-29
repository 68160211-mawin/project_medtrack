<?php
require_once __DIR__ . '/db.php';

/* ---------- ตัวช่วยทั่วไป ---------- */
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
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

/* ---------- ระบบผู้ใช้ ---------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(?string $role = null): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    if ($role !== null && $u['role'] !== $role) {
        redirect('index.php');
    }
    return $u;
}

// ผู้ใช้ $uid ดู/แก้ข้อมูลของผู้ป่วย $pid ได้หรือไม่
function can_access_patient(PDO $pdo, int $uid, int $pid, bool $needEdit = false): bool
{
    if ($uid === $pid) {
        return true;
    }
    $q = $pdo->prepare('SELECT can_edit FROM care_links WHERE patient_id = ? AND caregiver_id = ?');
    $q->execute([$pid, $uid]);
    $r = $q->fetch();
    if (!$r) {
        return false;
    }
    return $needEdit ? (bool)$r['can_edit'] : true;
}

function caregivers_of(PDO $pdo, int $patientId): array
{
    $q = $pdo->prepare('SELECT caregiver_id FROM care_links WHERE patient_id = ?');
    $q->execute([$patientId]);
    return array_column($q->fetchAll(), 'caregiver_id');
}

/* ---------- ตรรกะหลักของระบบ ---------- */

// สร้างแถว intake_logs ของมื้อยาวันนี้ (INSERT IGNORE กันซ้ำด้วย UNIQUE KEY)
function generate_logs_for_today(PDO $pdo): void
{
    $sql = "INSERT IGNORE INTO intake_logs (schedule_id, scheduled_at)
            SELECT s.schedule_id, TIMESTAMP(:t1, s.time_of_day)
            FROM schedules s
            JOIN prescriptions p ON p.prescription_id = s.prescription_id
            WHERE p.start_date <= :t2
              AND (p.end_date IS NULL OR p.end_date >= :t3)
              AND TIMESTAMP(:t4, s.time_of_day) >= p.created_at
              AND FIND_IN_SET(:dow, s.days_of_week)";
    // เงื่อนไข :t4 กันไม่ให้มื้อที่เลยเวลาไปก่อนเพิ่มยา ถูกนับเป็นข้ามมื้อและส่งแจ้งเตือนผิด ๆ
    $today = date('Y-m-d');
    $pdo->prepare($sql)->execute([
        ':t1' => $today, ':t2' => $today, ':t3' => $today, ':t4' => $today,
        ':dow' => (string)date('N'),
    ]);
}

// มื้อที่เลยเวลาเกิน MISSED_AFTER_MIN นาทีและยังไม่กิน -> missed + แจ้งเตือนผู้ดูแล
function mark_missed(PDO $pdo): int
{
    $sql = "SELECT l.log_id, l.scheduled_at, p.patient_id, u.name AS patient_name, m.name AS med_name
            FROM intake_logs l
            JOIN schedules s ON s.schedule_id = l.schedule_id
            JOIN prescriptions p ON p.prescription_id = s.prescription_id
            JOIN medicines m ON m.medicine_id = p.medicine_id
            JOIN users u ON u.user_id = p.patient_id
            WHERE l.status = 'pending'
              AND l.scheduled_at < (NOW() - INTERVAL " . (int)MISSED_AFTER_MIN . " MINUTE)";
    $rows = $pdo->query($sql)->fetchAll();

    $upd = $pdo->prepare("UPDATE intake_logs SET status = 'missed' WHERE log_id = ? AND status = 'pending'");
    $ins = $pdo->prepare("INSERT INTO alerts (caregiver_id, patient_id, log_id, type, message)
                          VALUES (?, ?, ?, 'missed', ?)");
    foreach ($rows as $r) {
        $pdo->beginTransaction();
        $upd->execute([$r['log_id']]);
        if ($upd->rowCount() === 1) {
            $msg = sprintf('%s ยังไม่ได้กิน %s (กำหนด %s น.)',
                $r['patient_name'], $r['med_name'], date('H:i', strtotime($r['scheduled_at'])));
            foreach (caregivers_of($pdo, (int)$r['patient_id']) as $cid) {
                $ins->execute([$cid, $r['patient_id'], $r['log_id'], $msg]);
            }
        }
        $pdo->commit();
    }
    return count($rows);
}

// กดว่า "กินแล้ว": อัปเดตสถานะ ตัดสต็อก และเช็คยาใกล้หมด
function take_dose(PDO $pdo, int $logId, int $uid): bool
{
    $q = $pdo->prepare("SELECT l.log_id, l.status, p.prescription_id, p.patient_id
                        FROM intake_logs l
                        JOIN schedules s ON s.schedule_id = l.schedule_id
                        JOIN prescriptions p ON p.prescription_id = s.prescription_id
                        WHERE l.log_id = ?");
    $q->execute([$logId]);
    $log = $q->fetch();
    if (!$log || !can_access_patient($pdo, $uid, (int)$log['patient_id'])) {
        return false;
    }
    if ($log['status'] === 'taken') {
        return true;
    }
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE intake_logs SET status = 'taken', taken_at = NOW() WHERE log_id = ?")->execute([$logId]);
    $pdo->prepare("UPDATE prescriptions SET stock_left = GREATEST(stock_left - 1, 0) WHERE prescription_id = ?")
        ->execute([$log['prescription_id']]);
    check_low_stock($pdo, (int)$log['prescription_id']);
    $pdo->commit();
    return true;
}

function check_low_stock(PDO $pdo, int $prescriptionId): void
{
    $q = $pdo->prepare("SELECT p.stock_left, p.patient_id, u.name AS patient_name, m.name AS med_name,
                               (SELECT COUNT(*) FROM schedules s WHERE s.prescription_id = p.prescription_id) AS per_day
                        FROM prescriptions p
                        JOIN users u ON u.user_id = p.patient_id
                        JOIN medicines m ON m.medicine_id = p.medicine_id
                        WHERE p.prescription_id = ?");
    $q->execute([$prescriptionId]);
    $p = $q->fetch();
    if (!$p || (int)$p['per_day'] === 0) {
        return;
    }
    if ((int)$p['stock_left'] > (int)$p['per_day'] * LOW_STOCK_DAYS) {
        return;
    }
    $days = intdiv((int)$p['stock_left'], (int)$p['per_day']);
    $msg = sprintf('%s: %s เหลือประมาณ %d วัน (%d เม็ด) ควรไปรับยาเพิ่ม',
        $p['patient_name'], $p['med_name'], $days, $p['stock_left']);

    $exists = $pdo->prepare("SELECT 1 FROM alerts WHERE prescription_id = ? AND type = 'low_stock'
                             AND caregiver_id = ? AND is_read = 0");
    $ins = $pdo->prepare("INSERT INTO alerts (caregiver_id, patient_id, prescription_id, type, message)
                          VALUES (?, ?, ?, 'low_stock', ?)");
    foreach (caregivers_of($pdo, (int)$p['patient_id']) as $cid) {
        $exists->execute([$prescriptionId, $cid]);
        if (!$exists->fetch()) {
            $ins->execute([$cid, $p['patient_id'], $prescriptionId, $msg]);
        }
    }
}

// % การกินครบในช่วง $days วันที่ผ่านมา (คืน null ถ้ายังไม่มีข้อมูล)
function adherence_percent(PDO $pdo, int $patientId, int $days = 7): ?int
{
    $q = $pdo->prepare("SELECT SUM(l.status = 'taken') AS taken, SUM(l.status IN ('taken','missed')) AS total
                        FROM intake_logs l
                        JOIN schedules s ON s.schedule_id = l.schedule_id
                        JOIN prescriptions p ON p.prescription_id = s.prescription_id
                        WHERE p.patient_id = ?
                          AND l.scheduled_at >= (NOW() - INTERVAL " . (int)$days . " DAY)");
    $q->execute([$patientId]);
    $r = $q->fetch();
    $total = (int)($r['total'] ?? 0);
    return $total === 0 ? null : (int)round(100 * (int)$r['taken'] / $total);
}

function status_label(string $s): string
{
    return ['pending' => 'รอกิน', 'taken' => 'กินแล้ว', 'missed' => 'ข้ามมื้อ', 'skipped' => 'งดยา'][$s] ?? $s;
}

function day_names(string $csv): string
{
    $map = [1 => 'จ.', 2 => 'อ.', 3 => 'พ.', 4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.', 7 => 'อา.'];
    $d = array_filter(explode(',', $csv));
    if (count($d) === 7) {
        return 'ทุกวัน';
    }
    return implode(' ', array_map(fn($n) => $map[(int)$n] ?? '', $d));
}
