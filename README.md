# MedTrack — ระบบเตือนยาและติดตามการกินยาของผู้สูงอายุ

เว็บ PHP + MySQL ให้ผู้สูงอายุกดยืนยัน "กินแล้ว" และให้ลูกหลานดูสถานะจากที่ไกล พร้อมแจ้งเตือนเมื่อข้ามมื้อหรือยาใกล้หมด

## วิธีติดตั้ง

1. ติดตั้ง XAMPP (หรือ PHP 8+ และ MySQL/MariaDB) แล้วเปิด Apache กับ MySQL
2. คัดลอกโฟลเดอร์ `medtrack` ไปไว้ใน `htdocs` (XAMPP)
3. นำเข้าฐานข้อมูล: เปิด phpMyAdmin แล้ว Import ไฟล์ `database.sql`
   (หรือรัน `mysql -u root -p < database.sql`)
4. คัดลอก `config.example.php` เป็น `config.php` แล้วใส่ค่าฐานข้อมูลของเครื่องคุณ (DB_NAME / DB_USER / DB_PASS)
   (`config.php` อยู่ใน `.gitignore` จึงไม่ถูกอัปโหลดขึ้น GitHub)
5. เปิดเบราว์เซอร์ไปที่ `http://localhost/medtrack/`
6. (ไม่บังคับ) สร้างข้อมูลตัวอย่างสำหรับนำเสนอ:
   `php seed_demo.php` จะได้บัญชี
   - ผู้สูงอายุ `demo.patient@example.com` / `demo1234`
   - ลูกหลาน `demo.child@example.com` / `demo1234`
7. (ไม่บังคับ) ตั้งให้รัน `php cron.php` ทุก 5 นาที เพื่อให้แจ้งเตือนข้ามมื้อทันเวลา
   ถ้าไม่ตั้ง ระบบจะตรวจเมื่อมีคนเปิดหน้าเว็บ

## โครงสร้างโฟลเดอร์

```
medtrack/
├── config.example.php       ตัวอย่างไฟล์ตั้งค่า (คัดลอกเป็น config.php) มี MISSED_AFTER_MIN, LOW_STOCK_DAYS
├── .gitignore               กัน config.php ที่มีรหัสผ่านจริงขึ้น Git
├── database.sql             สร้างฐานข้อมูล 7 ตาราง
├── includes/
│   ├── db.php               เชื่อมต่อ PDO
│   ├── functions.php        ตรรกะหลักของระบบ (ดูด้านล่าง)
│   ├── header.php / footer.php
├── assets/style.css
├── index.php                เปลี่ยนเส้นทางตามบทบาท
├── login.php / register.php / logout.php
├── patient.php              หน้าผู้สูงอายุ ปุ่มใหญ่ "กินแล้ว"
├── caregiver.php            แดชบอร์ดลูกหลาน + การแจ้งเตือน
├── link_patient.php         ลูกหลานเชื่อมกับบัญชีผู้สูงอายุ
├── patient_detail.php       ยาที่กิน สต็อก ประวัติ 7 วัน % ความสม่ำเสมอ
├── add_prescription.php     เพิ่มยา + เวลากิน
├── delete_prescription.php
├── take.php                 กดว่า "กินแล้ว"
├── cron.php                 สร้างมื้อยาประจำวัน + ตรวจข้ามมื้อ
└── seed_demo.php            ข้อมูลตัวอย่าง
```

## ฟังก์ชันหลักใน includes/functions.php

| ฟังก์ชัน | หน้าที่ |
|---|---|
| `generate_logs_for_today` | สร้างแถว intake_logs ของมื้อวันนี้จาก schedules |
| `mark_missed` | มื้อที่เลย 30 นาทีแล้วยังไม่กิน → missed + สร้าง alert ให้ผู้ดูแลทุกคน |
| `take_dose` | บันทึกว่ากินแล้ว ตัดสต็อก 1 เม็ด และเรียก `check_low_stock` |
| `check_low_stock` | ถ้ายาเหลือไม่เกิน 5 วัน สร้าง alert (ไม่ซ้ำถ้ายังไม่ได้อ่าน) |
| `adherence_percent` | % การกินครบใน N วัน |
| `can_access_patient` | ตรวจสิทธิ์ว่าเป็นเจ้าของข้อมูลหรือผู้ดูแลที่เชื่อมไว้ |

## ความปลอดภัยที่ทำไว้

- รหัสผ่านเก็บด้วย `password_hash`
- ใช้ PDO prepared statements ทุกคำสั่ง SQL (กัน SQL injection)
- ใช้ `htmlspecialchars` ผ่านฟังก์ชัน `e()` (กัน XSS)
- ฟอร์ม POST ทุกอันมี CSRF token
- ตรวจสิทธิ์ทุกครั้งก่อนดู/แก้ข้อมูลของผู้ป่วย

## ข้อจำกัด (เอาไว้ตอบตอนนำเสนอ)

- เป็นระบบช่วยเตือนเท่านั้น ไม่ให้คำแนะนำเรื่องขนาดยา ข้อมูลยาผู้ใช้กรอกเอง
- การแจ้งเตือนแสดงบนเว็บ ยังไม่ส่ง LINE/อีเมล (ต่อยอดได้ เช่น LINE Notify หรือ Telegram Bot ใน `mark_missed`)

## ไอเดียต่อยอดเพิ่มคะแนน

- ตาราง `drug_interactions` เตือนยาที่ไม่ควรกินใกล้กัน
- หน้ารายงานรายสัปดาห์สำหรับพิมพ์ให้หมอ
- กราฟความสม่ำเสมอด้วย Chart.js
