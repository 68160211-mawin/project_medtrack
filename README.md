# MedTrack — ระบบเตือนยาและติดตามการกินยาของผู้สูงอายุ

เว็บ PHP + MySQL ให้ผู้สูงอายุกดยืนยัน "กินแล้ว" และให้ลูกหลานดูสถานะจากที่ไกล พร้อมแจ้งเตือนเมื่อข้ามมื้อหรือยาใกล้หมด

## เทคโนโลยี

Native PHP 8 + MySQLi (prepared statements) + MySQL/MariaDB, Bootstrap 5 (CDN), ตารางทั้งหมดขึ้นต้นด้วย `project_`

## วิธีติดตั้งบนเซิร์ฟเวอร์ Angsila

1. อัปโหลดโฟลเดอร์โปรเจกต์ไปที่ `public_html/medtrack/` ด้วย FileZilla
2. คัดลอก `config/db.example.php` เป็น `config/db.php` แล้วใส่ host / user / password / ชื่อฐานข้อมูลจริง
   (`config/db.php` อยู่ใน `.gitignore` จึงไม่ขึ้น GitHub)
3. เปิด phpMyAdmin เลือกฐานข้อมูลของคุณ แล้ว Import `database/setup_server.sql`
   (สร้างเฉพาะตาราง `project_*` ไม่แตะตารางอื่น)
4. เปิด `https://angsila.cs.buu.ac.th/~รหัสนิสิต/medtrack/login.php`

## วิธีติดตั้งบน XAMPP

1. วางโฟลเดอร์ไว้ใน `htdocs/medtrack` เปิด Apache + MySQL
2. phpMyAdmin → Import `database/setup.sql` (สร้างฐานข้อมูล `medtrack` ให้)
3. คัดลอก `config/db.example.php` เป็น `config/db.php` (ค่าเริ่มต้นของ XAMPP: user root, รหัสผ่านว่าง, db medtrack)
4. เปิด `http://localhost/medtrack/`

## ข้อมูลตัวอย่าง และ cron 

- `php seed_demo.php` สร้างบัญชี `demo.patient@example.com` (ผู้สูงอายุ) และ `demo.child@example.com` (ลูกหลาน) รหัสผ่าน `demo1234`
- `php cron.php` ตั้งรันทุก 5 นาทีเพื่อแจ้งเตือนข้ามมื้อทันเวลา ถ้าไม่ตั้ง ระบบตรวจเมื่อมีคนเปิดหน้าเว็บ (รันได้จาก CLI เท่านั้น)

## โครงสร้างโฟลเดอร์

```
medtrack/
├── config/db.example.php    ตัวอย่างการเชื่อมต่อ MySQLi (คัดลอกเป็น db.php)
├── database/
│   ├── setup_server.sql     สำหรับ Angsila (ไม่มี CREATE DATABASE)
│   └── setup.sql            สำหรับ XAMPP
├── includes/
│   ├── settings.php         ค่าคงที่ (MISSED_AFTER_MIN, LOW_STOCK_DAYS) + session
│   ├── functions.php        ตรรกะหลัก + helper ของ MySQLi
│   └── header.php / footer.php
├── assets/style.css
├── index.php / login.php / register.php / logout.php
├── patient.php / caregiver.php / link_patient.php
├── patient_detail.php / add_prescription.php / delete_prescription.php
├── take.php  cron.php  seed_demo.php
```

## ฟังก์ชันหลักใน includes/functions.php

| ฟังก์ชัน | หน้าที่ |
|---|---|
| `generate_logs_for_today` | สร้างแถว project_intake_logs ของมื้อวันนี้จาก schedules |
| `mark_missed` | มื้อที่เลย 30 นาทีแล้วยังไม่กิน → missed + สร้าง alert ให้ผู้ดูแลทุกคน |
| `take_dose` | บันทึกว่ากินแล้ว ตัดสต็อก 1 เม็ด และเรียก `check_low_stock` |
| `check_low_stock` | ถ้ายาเหลือไม่เกิน 5 วัน สร้าง alert (ไม่ซ้ำถ้ายังไม่ได้อ่าน) |
| `adherence_percent` | % การกินครบใน N วัน |
| `can_access_patient` | ตรวจสิทธิ์ว่าเป็นเจ้าของข้อมูลหรือผู้ดูแลที่เชื่อมไว้ |

## ความปลอดภัยที่ทำไว้

- รหัสผ่านเก็บด้วย `password_hash`
- ใช้ MySQLi prepared statements ทุกคำสั่ง SQL (กัน SQL injection)
- ใช้ `htmlspecialchars` ผ่านฟังก์ชัน `e()` (กัน XSS)
- ฟอร์ม POST ทุกอันมี CSRF token
- ตรวจสิทธิ์ทุกครั้งก่อนดู/แก้ข้อมูลของผู้ป่วย

## ข้อจำกัด

- เป็นระบบช่วยเตือนเท่านั้น ไม่ให้คำแนะนำเรื่องขนาดยา ข้อมูลยาผู้ใช้กรอกเอง
- การแจ้งเตือนแสดงบนเว็บ ยังไม่ส่ง LINE/อีเมล (ต่อยอดได้ เช่น LINE Notify หรือ Telegram Bot ใน `mark_missed`)

## ไอเดียต่อยอดเพิ่มคะแนน

- ตาราง `project_drug_interactions` เตือนยาที่ไม่ควรกินใกล้กัน
- หน้ารายงานรายสัปดาห์สำหรับพิมพ์ให้หมอ
- กราฟความสม่ำเสมอด้วย Chart.js
