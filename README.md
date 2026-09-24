# NoteShare — เว็บแชร์โน้ต/ชีทสรุปสำหรับติวสอบ
โปรเจควิชา CP423324 Database and Web Security

## วิธีที่ 1: รันด้วย Docker (แนะนำ — ไม่ต้องติดตั้ง PHP/MySQL ลงเครื่อง)

```bash
cp .env.example .env      # แก้รหัสผ่านในไฟล์ .env ให้เป็นของจริงก่อนใช้งานจริง
docker compose up -d --build
```
แล้วเปิด **http://localhost:8000**

> phpMyAdmin **ไม่ได้เปิดมาให้อัตโนมัติ** (เพื่อความปลอดภัย — กันคนนอกเข้าถึงฐานข้อมูล)
> ต้องสั่งเปิดเองด้วย `docker compose --profile tools up -d phpmyadmin` แล้วเข้าได้เฉพาะที่ `http://127.0.0.1:8080` (จากเครื่องที่รัน docker เท่านั้น)

ฐานข้อมูล ตาราง และ Database User แบบ Least Privilege ถูกสร้างให้อัตโนมัติ
รายละเอียดเพิ่มเติม วิธีตั้งบัญชี Admin การเปิด phpMyAdmin และการแก้ปัญหา ดูที่ **[DOCKER.md](DOCKER.md)**

---

## วิธีที่ 2: ติดตั้งเองบนเครื่อง (XAMPP/Laragon)

### สิ่งที่ต้องมีก่อนติดตั้ง
- PHP 8.x พร้อม extension `mysqli` และ `fileinfo`
- MySQL หรือ MariaDB
- เว็บเซิร์ฟเวอร์ที่รองรับ `.htaccess` และตั้ง `AllowOverride All` (Apache + mod_rewrite/mod_authz_core) หรือปรับ config เทียบเท่าใน Nginx

### ขั้นตอนติดตั้ง

#### 1. สร้างฐานข้อมูล
```bash
mysql -u root -p < database/schema.sql
```
คำสั่งนี้จะสร้างฐานข้อมูล `sheetapp_db` พร้อมตารางทั้งหมดและข้อมูลตัวอย่าง (รายวิชา)

#### 2. สร้าง Database User เฉพาะระบบ (ห้ามใช้ root)
```sql
CREATE USER 'sheetapp_user'@'localhost' IDENTIFIED BY 'ChangeThisPassword!123';
GRANT SELECT, INSERT, UPDATE, DELETE ON sheetapp_db.* TO 'sheetapp_user'@'localhost';
FLUSH PRIVILEGES;
```
> เปลี่ยนรหัสผ่านให้เป็นของจริง แล้วไปแก้ในไฟล์ `config/db.php` (ค่า `DB_PASS`) ให้ตรงกัน

#### 3. ตั้งค่าการเชื่อมต่อฐานข้อมูล
แก้ไขค่าคงที่ในไฟล์ `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'sheetapp_user');
define('DB_PASS', 'รหัสผ่านที่ตั้งไว้');
define('DB_NAME', 'sheetapp_db');
```

#### 4. ตั้งค่าสิทธิ์โฟลเดอร์ uploads
```bash
chmod 755 uploads
```
โฟลเดอร์นี้ต้องเขียนได้โดย process ของเว็บเซิร์ฟเวอร์ (เช่น www-data)

#### 5. เปิดเว็บผ่าน PHP built-in server (ทดสอบเร็ว ๆ)
```bash
php -S localhost:8000
```
แล้วเปิดเบราว์เซอร์ไปที่ `http://localhost:8000`

หรือถ้าใช้ Apache/XAMPP ให้ copy โฟลเดอร์นี้ไปไว้ใน `htdocs` แล้วเปิดผ่าน `http://localhost/notesharing`

#### 6. สร้างบัญชี Admin คนแรก
1. สมัครสมาชิกผ่านหน้าเว็บตามปกติ (จะได้ role = 'user')
2. เข้าไปที่ฐานข้อมูล แล้วรันคำสั่งนี้เพื่อเลื่อนเป็น admin (แทน `your_username` ด้วยชื่อผู้ใช้จริง):
```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```
3. Login ใหม่อีกครั้ง จะเห็นเมนู 🛠 Admin ปรากฏขึ้น

## โครงสร้างโปรเจค
```
notesharing/
├── config/
│   ├── db.php          # การเชื่อมต่อฐานข้อมูล (ข้อ 5)
│   ├── session.php      # Session security (ข้อ 4)
│   ├── csrf.php          # CSRF token helpers (ข้อ 9)
│   ├── functions.php     # ฟังก์ชันช่วยเหลือ: validation, logging, upload
│   └── .htaccess         # ปิดการเข้าถึงโฟลเดอร์นี้จากภายนอก
├── database/
│   └── schema.sql        # โครงสร้างฐานข้อมูล + ER 5 Entity
├── includes/
│   ├── header.php / footer.php
├── admin/
│   ├── index.php         # แดชบอร์ด admin
│   ├── manage_users.php  # จัดการผู้ใช้ทั้งหมด
│   ├── manage_notes.php  # จัดการชีททั้งหมด
│   └── security_logs.php # ดู security logs
├── uploads/               # ไฟล์ชีทที่อัปโหลด (rename แบบสุ่มแล้ว)
├── index.php               # หน้าแรก + ค้นหา
├── register.php / login.php / logout.php
├── dashboard.php           # ชีทของฉัน
├── upload.php               # อัปโหลดชีทใหม่
├── note_detail.php          # รายละเอียดชีท + คอมเมนต์
├── edit_note.php / delete_note.php
├── delete_comment.php
└── download.php             # ดาวน์โหลดไฟล์ (ผ่าน PHP, ต้อง login)
```

## Mapping ข้อกำหนดโครงงาน → จุดที่ implement ในโค้ด

| ข้อ | หัวข้อ | ไฟล์ที่เกี่ยวข้อง |
|---|---|---|
| 1 | ฟังก์ชันพื้นฐาน (CRUD, Search) | index.php, upload.php, edit_note.php, delete_note.php |
| 2 | Authentication | register.php, login.php (password_hash/verify), config/functions.php (require_login) |
| 3 | Authorization (user/admin) | config/functions.php (is_admin, require_admin), edit_note.php, delete_note.php, admin/* |
| 4 | Session Security | config/session.php (regenerate_id, timeout, cookie params) |
| 5 | Database Security | config/db.php (non-root user), database/schema.sql |
| 6 | SQL Injection Prevention | ทุกไฟล์ที่ query DB ใช้ prepare/bind_param/execute |
| 7 | Input Validation & Error Handling | config/functions.php (validate_*), mysqli_report(OFF), custom error messages |
| 8 | XSS Prevention | config/functions.php (ฟังก์ชัน `e()` = htmlspecialchars) ใช้ทุกจุดที่ echo ข้อมูลผู้ใช้ |
| 9 | CSRF Protection | config/csrf.php + ทุกฟอร์ม POST (upload, edit, delete, comment, admin actions) |
| 10 | Security Logging | config/functions.php (log_security_event) + ตาราง security_logs + admin/security_logs.php |
| หมายเหตุ | Upload File Validation | config/functions.php (validate_and_store_upload: extension, MIME, ขนาด, สุ่มชื่อไฟล์) |
| เสริม | ป้องกัน Brute-force | config/functions.php (is_login_locked_out) + login.php — ล็อกบัญชี/IP ชั่วคราว 15 นาที หลัง login ผิดครบ 5 ครั้ง |
| เสริม | จำกัดการเข้าถึงเครื่องมือผู้ดูแล | docker-compose.yml — phpMyAdmin bind เฉพาะ 127.0.0.1 และต้องสั่งเปิดเอง (`--profile tools`) |
| เสริม | ไม่ฝังรหัสผ่านในโค้ด | .env / .env.example — ทุก credential อ่านจาก environment variable ไม่ commit เข้า git |
| เสริม | HTTP Security Headers | .htaccess (ราก) — Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Permissions-Policy, ปิด Server banner |
| เสริม | ไม่มี inline JavaScript | assets/confirm.js — ย้ายจาก `onsubmit="confirm(...)"` มาเป็นไฟล์แยก ทำให้ตั้ง CSP แบบ `script-src 'self'` ได้โดยไม่ต้องเปิด `unsafe-inline` |
| เสริม | PHP/Apache Hardening | Dockerfile — ปิด allow_url_fopen, disable_functions อันตราย, session hardening, ซ่อนเวอร์ชัน Server |

## การสาธิตช่องโหว่ตอนนำเสนอ (แนะนำอย่างน้อย 2 เรื่องตามที่โจทย์กำหนด)
1. **SQL Injection**: ลองแก้โค้ดชั่วคราวให้ใช้ string concatenation แทน prepared statement ในช่อง login/search แล้วป้อน `' OR '1'='1` เทียบกับตอนที่แก้กลับมาใช้ prepared statement
2. **XSS**: ลองคอมเมนต์ด้วยข้อความ `<script>alert('XSS')</script>` เทียบผลระหว่างก่อน/หลังใช้ `htmlspecialchars()`
3. **CSRF**: ลองสร้างฟอร์มปลอมจากเว็บอื่นที่ยิง POST ไปยัง delete_note.php โดยไม่มี token ที่ถูกต้อง แล้วแสดงว่าระบบปฏิเสธคำขอ
4. **Unauthorized Access**: ลอง login เป็น user ทั่วไปแล้วพยายามแก้ไข/ลบชีทของคนอื่นโดยเปลี่ยน note_id ใน URL
