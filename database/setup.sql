-- MedTrack (เครื่องตัวเอง / XAMPP): ไฟล์นี้สร้างฐานข้อมูล medtrack ให้ด้วย
-- Import ผ่าน phpMyAdmin หรือ  mysql -u root -p < database/setup.sql
CREATE DATABASE IF NOT EXISTS medtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medtrack;

-- MedTrack: ระบบเตือนยาและติดตามการกินยาของผู้สูงอายุ
-- ไฟล์นี้ใช้บนเซิร์ฟเวอร์ Angsila: เลือกฐานข้อมูลของตัวเอง (เช่น s68160211) ใน phpMyAdmin ก่อน แล้ว Import
-- ไม่มีคำสั่ง CREATE DATABASE / USE และทุกตารางขึ้นต้นด้วย project_ จึงไม่ชนกับตารางงานอื่นในฐานข้อมูลเดียวกัน
-- คำสั่ง DROP TABLE ด้านล่างลบเฉพาะตาราง project_* ของโปรเจกต์นี้ (Import ซ้ำ = ล้างข้อมูลของ MedTrack ใหม่)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS project_alerts, project_intake_logs, project_schedules,
  project_prescriptions, project_medicines, project_care_links, project_users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1) ผู้ใช้ทุกคน (ผู้สูงอายุ = patient, ลูกหลาน/ผู้ดูแล = caregiver)
CREATE TABLE project_users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('patient','caregiver') NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) ความสัมพันธ์ผู้ดูแล <-> ผู้สูงอายุ (many-to-many)
CREATE TABLE project_care_links (
  link_id      INT AUTO_INCREMENT PRIMARY KEY,
  patient_id   INT NOT NULL,
  caregiver_id INT NOT NULL,
  relation     VARCHAR(50) NOT NULL DEFAULT 'ลูกหลาน',
  can_edit     TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_link (patient_id, caregiver_id),
  FOREIGN KEY (patient_id)   REFERENCES project_users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (caregiver_id) REFERENCES project_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) รายชื่อยา (ใช้ร่วมกันได้ทุกคน)
CREATE TABLE project_medicines (
  medicine_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL UNIQUE,
  dosage_form VARCHAR(50) NOT NULL DEFAULT 'เม็ด',
  notes       VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) ผู้ป่วยแต่ละคนต้องกินยาอะไร (ข้อมูลจากใบสั่งยาที่ผู้ใช้กรอกเอง)
CREATE TABLE project_prescriptions (
  prescription_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id      INT NOT NULL,
  medicine_id     INT NOT NULL,
  dose            VARCHAR(60) NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NULL,
  stock_left      INT NOT NULL DEFAULT 0,
  created_by      INT NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (stock_left >= 0),
  CHECK (end_date IS NULL OR end_date >= start_date),
  FOREIGN KEY (patient_id)  REFERENCES project_users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES project_medicines(medicine_id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by)  REFERENCES project_users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) เวลากินยาในแต่ละวัน (days_of_week: 1=จันทร์ ... 7=อาทิตย์)
CREATE TABLE project_schedules (
  schedule_id     INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  time_of_day     TIME NOT NULL,
  days_of_week    VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  FOREIGN KEY (prescription_id) REFERENCES project_prescriptions(prescription_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) บันทึกการกินจริงของแต่ละมื้อ
CREATE TABLE project_intake_logs (
  log_id       INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id  INT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  taken_at     DATETIME NULL,
  status       ENUM('pending','taken','missed','skipped') NOT NULL DEFAULT 'pending',
  UNIQUE KEY uq_log (schedule_id, scheduled_at),
  KEY idx_sched_time (scheduled_at, status),
  FOREIGN KEY (schedule_id) REFERENCES project_schedules(schedule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) การแจ้งเตือนที่ส่งให้ผู้ดูแล (ข้ามมื้อ / ยาใกล้หมด)
CREATE TABLE project_alerts (
  alert_id        INT AUTO_INCREMENT PRIMARY KEY,
  caregiver_id    INT NOT NULL,
  patient_id      INT NOT NULL,
  log_id          INT NULL,
  prescription_id INT NULL,
  type            ENUM('missed','low_stock') NOT NULL,
  message         VARCHAR(255) NOT NULL,
  sent_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_unread (caregiver_id, is_read),
  FOREIGN KEY (caregiver_id)    REFERENCES project_users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id)      REFERENCES project_users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (log_id)          REFERENCES project_intake_logs(log_id) ON DELETE SET NULL,
  FOREIGN KEY (prescription_id) REFERENCES project_prescriptions(prescription_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ชื่อยาตัวอย่าง (เป็นแค่รายชื่อให้เลือก ไม่มีคำแนะนำขนาดยา)
INSERT INTO project_medicines (name, dosage_form) VALUES
  ('Paracetamol', 'เม็ด'),
  ('Metformin', 'เม็ด'),
  ('Amlodipine', 'เม็ด'),
  ('Simvastatin', 'เม็ด'),
  ('Aspirin', 'เม็ด'),
  ('Omeprazole', 'แคปซูล'),
  ('Vitamin B complex', 'เม็ด'),
  ('Insulin', 'ฉีด');
