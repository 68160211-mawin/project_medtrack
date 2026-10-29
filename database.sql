-- MedTrack: ระบบเตือนยาและติดตามการกินยาของผู้สูงอายุ
-- นำเข้าด้วย: mysql -u root -p < database.sql   (หรือ Import ผ่าน phpMyAdmin)

CREATE DATABASE IF NOT EXISTS medtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medtrack;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS alerts, intake_logs, schedules, prescriptions, medicines, care_links, users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1) ผู้ใช้ทุกคน (ผู้สูงอายุ = patient, ลูกหลาน/ผู้ดูแล = caregiver)
CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('patient','caregiver') NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) ความสัมพันธ์ผู้ดูแล <-> ผู้สูงอายุ (many-to-many)
CREATE TABLE care_links (
  link_id      INT AUTO_INCREMENT PRIMARY KEY,
  patient_id   INT NOT NULL,
  caregiver_id INT NOT NULL,
  relation     VARCHAR(50) NOT NULL DEFAULT 'ลูกหลาน',
  can_edit     TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_link (patient_id, caregiver_id),
  FOREIGN KEY (patient_id)   REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (caregiver_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3) รายชื่อยา (ใช้ร่วมกันได้ทุกคน)
CREATE TABLE medicines (
  medicine_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL UNIQUE,
  dosage_form VARCHAR(50) NOT NULL DEFAULT 'เม็ด',
  notes       VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 4) ผู้ป่วยแต่ละคนต้องกินยาอะไร (ข้อมูลจากใบสั่งยาที่ผู้ใช้กรอกเอง)
CREATE TABLE prescriptions (
  prescription_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id      INT NOT NULL,
  medicine_id     INT NOT NULL,
  dose            VARCHAR(60) NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NULL,
  stock_left      INT NOT NULL DEFAULT 0,
  created_by      INT NOT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id)  REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id),
  FOREIGN KEY (created_by)  REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 5) เวลากินยาในแต่ละวัน (days_of_week: 1=จันทร์ ... 7=อาทิตย์)
CREATE TABLE schedules (
  schedule_id     INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  time_of_day     TIME NOT NULL,
  days_of_week    VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) บันทึกการกินจริงของแต่ละมื้อ
CREATE TABLE intake_logs (
  log_id       INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id  INT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  taken_at     DATETIME NULL,
  status       ENUM('pending','taken','missed','skipped') NOT NULL DEFAULT 'pending',
  UNIQUE KEY uq_log (schedule_id, scheduled_at),
  KEY idx_sched_time (scheduled_at, status),
  FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7) การแจ้งเตือนที่ส่งให้ผู้ดูแล (ข้ามมื้อ / ยาใกล้หมด)
CREATE TABLE alerts (
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
  FOREIGN KEY (caregiver_id)    REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id)      REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (log_id)          REFERENCES intake_logs(log_id) ON DELETE SET NULL,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ชื่อยาตัวอย่าง (เป็นแค่รายชื่อให้เลือก ไม่มีคำแนะนำขนาดยา)
INSERT INTO medicines (name, dosage_form) VALUES
  ('Paracetamol', 'เม็ด'),
  ('Metformin', 'เม็ด'),
  ('Amlodipine', 'เม็ด'),
  ('Simvastatin', 'เม็ด'),
  ('Aspirin', 'เม็ด'),
  ('Omeprazole', 'แคปซูล'),
  ('Vitamin B complex', 'เม็ด'),
  ('Insulin', 'ฉีด');
