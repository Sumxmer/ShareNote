# รันด้วย Docker

ประกอบด้วย 3 service:

| Service | พอร์ต | เข้าถึงได้จาก | หน้าที่ |
|---|---|---|---|
| web | http://localhost:8000 | ทุกที่ (public) | เว็บแอป (PHP 8.3 + Apache) |
| phpmyadmin | http://127.0.0.1:8080 | **เฉพาะเครื่องนี้เท่านั้น** และต้องสั่งเปิดเอง | จัดการฐานข้อมูลผ่านหน้าเว็บ |
| db | 127.0.0.1:3307 → 3306 | **เฉพาะเครื่องนี้เท่านั้น** | MySQL 8.0 |

## ตั้งค่าก่อนรันครั้งแรก (สำคัญ)

รหัสผ่านทั้งหมดย้ายไปเก็บในไฟล์ `.env` (ไม่ถูก commit เข้า git และไม่ถูกส่งเข้า docker image) ต้องสร้างไฟล์นี้ก่อนรัน:

```bash
cp .env.example .env
```

แล้วเปิดไฟล์ `.env` แก้ค่ารหัสผ่านทั้ง 4 ตัวให้เป็นของจริง (อย่าใช้ค่าตัวอย่างที่ให้มา) — โดยเฉพาะก่อนนำไปใช้งานจริงหรือ deploy ขึ้นเซิร์ฟเวอร์ที่เข้าถึงจากอินเทอร์เน็ตได้

## วิธีรัน

```bash
docker compose up -d --build
```

รอบแรกใช้เวลาสักครู่ เพราะต้อง build image และ MySQL ต้อง initialize ฐานข้อมูล
(`database/schema.sql` และ `database/app_user_privileges.sql` จะถูกรันอัตโนมัติ)

เปิดเว็บที่ **http://localhost:8000**

> คำสั่งนี้จะ **ไม่รัน phpMyAdmin** ให้อัตโนมัติ (เพื่อความปลอดภัย) ต้องสั่งเปิดเองตามหัวข้อถัดไป

## เปิดใช้งาน phpMyAdmin (เมื่อจำเป็นเท่านั้น)

```bash
docker compose --profile tools up -d phpmyadmin
```
แล้วเปิด **http://127.0.0.1:8080**

**ทำไมต้องพิมพ์คำสั่งแยก และทำไมต้องเป็น `127.0.0.1`:**
- ปกติ `docker compose up -d` จะไม่สร้าง service นี้เลย (อยู่ใน profile `tools`) ลดพื้นที่โจมตี (attack surface) ของระบบที่เปิดให้บริการจริง
- พอร์ต bind กับ `127.0.0.1` แปลว่าเข้าได้แค่จาก **เครื่องที่รัน docker เท่านั้น** — ต่อให้อยู่ใน WiFi/LAN เดียวกันก็เข้าไม่ได้ ต้องเข้าเครื่อง server ตรงๆ หรือผ่าน SSH tunnel เท่านั้น จึง "ห้ามคนนอกเข้า" ตามที่ต้องการ
- ใช้เสร็จแล้วควรปิด: `docker compose --profile tools stop phpmyadmin`

**ล็อกอิน phpMyAdmin ด้วยบัญชีในไฟล์ `.env` ที่คุณตั้งไว้:**
- `sheetapp_user` / (ค่า `DB_PASSWORD`) — สิทธิ์จำกัดตาม Least Privilege ใช้งานทั่วไป
- `root` / (ค่า `DB_ROOT_PASSWORD`) — ใช้เฉพาะตอนต้องแก้โครงสร้างตาราง

ไม่มี auto-login ตั้งไว้ (ไม่ได้ตั้ง `PMA_USER`/`PMA_PASSWORD` ใน compose) ต้องพิมพ์รหัสเองทุกครั้ง

## สร้างบัญชี Admin คนแรก

1. สมัครสมาชิกที่ http://localhost:8000/register.php ตามปกติ
2. เปิด phpMyAdmin ตามขั้นตอนด้านบน แล้วล็อกอินด้วย `sheetapp_user`
3. เลือกฐานข้อมูล `sheetapp_db` → แท็บ SQL → รันคำสั่ง (แทน `your_username` ด้วยชื่อที่สมัครไว้):
```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```
4. Logout แล้ว login ใหม่ในเว็บ จะเห็นเมนู 🛠 Admin

> ถ้าอยากใช้ command line แทน: `docker compose exec db mysql -u root -p sheetapp_db` (ใส่รหัส root ตามใน `.env`)

## คำสั่งที่ใช้บ่อย

```bash
docker compose logs -f web              # ดู log ของเว็บ
docker compose logs -f db               # ดู log ฐานข้อมูล
docker compose restart web              # restart เฉพาะเว็บ
docker compose down                     # หยุด (ข้อมูลใน DB ยังอยู่)
docker compose down -v                  # หยุดและล้างฐานข้อมูลทิ้ง เริ่มใหม่หมด
docker compose --profile tools stop phpmyadmin   # ปิด phpMyAdmin เมื่อใช้เสร็จ
```

> แก้โค้ด PHP แล้วเห็นผลทันที ไม่ต้อง rebuild (เพราะ mount โค้ดเข้า container)
> ยกเว้นแก้ `Dockerfile` หรือ `docker-compose.yml` ต้อง `docker compose up -d --build` ใหม่

## ตรวจสอบว่าการป้องกันทำงานจริง

หลังรัน container แล้ว ลองคำสั่งเหล่านี้ — **ทุกอันต้องได้ 403**
```bash
curl -I http://localhost:8000/docker-compose.yml
curl -I http://localhost:8000/.env
curl -I http://localhost:8000/database/schema.sql
curl -I http://localhost:8000/config/db.php
```
ถ้าได้ 200 แปลว่า `.htaccess` ไม่ทำงาน (`AllowOverride` ไม่ได้เป็น All) ให้ rebuild ด้วย
`docker compose up -d --build` อีกครั้ง

ตรวจว่า security headers มาครบ:
```bash
curl -I http://localhost:8000/index.php
```
ควรเห็น `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` เป็นต้น

ตรวจว่า phpMyAdmin เข้าจากเครื่องอื่นไม่ได้ (รันจากเครื่องอื่นในวง LAN เดียวกัน แทน `<server-ip>` ด้วย IP จริงของเครื่องที่รัน docker):
```bash
curl -I http://<server-ip>:8080     # ต้อง connection refused/timeout ไม่ใช่ 200
```

ทดสอบว่าอัปโหลดไฟล์ได้ (สิทธิ์โฟลเดอร์ถูกต้อง):
```bash
docker compose logs web | grep noteshare
```
ควรเห็นข้อความ `[noteshare] uploads/ เขียนได้ปกติ`
ถ้าเห็นคำเตือน ให้รัน `chmod -R 777 uploads` บนเครื่องแล้ว `docker compose restart web`

ทดสอบระบบล็อกบัญชีกัน brute-force: ลอง login ด้วยรหัสผ่านผิด 5 ครั้งติด แล้วลองครั้งที่ 6
ด้วยรหัสที่ถูกต้อง — ควรถูกปฏิเสธชั่วคราว (ระบบล็อก 15 นาทีหลัง login ผิดครบ 5 ครั้ง)

## Troubleshooting

| อาการ | วิธีแก้ |
|---|---|
| เข้าเว็บแล้วขึ้น "ขณะนี้ระบบไม่สามารถให้บริการได้" | `docker compose logs db` ดูว่า init เสร็จหรือยัง รอสัก 30 วินาทีแล้วลองใหม่ |
| อัปโหลดไฟล์ไม่ได้ | `chmod -R 777 uploads` แล้ว `docker compose restart web` |
| พอร์ต 8000/8080/3307 ถูกใช้แล้ว | แก้เลขพอร์ตฝั่งซ้ายใน `docker-compose.yml` |
| แก้ schema.sql แล้วไม่มีผล | init script รันแค่ครั้งแรก ต้อง `docker compose down -v` แล้ว up ใหม่ |
| ลืมสร้างไฟล์ `.env` | compose จะใช้ค่า default ที่เขียนไว้ใน `docker-compose.yml` แทน (ปลอดภัยน้อยกว่า) ควรสร้าง `.env` เสมอก่อนใช้งานจริง |
| Login ถูกบล็อกทั้งที่รหัสถูก | ติด brute-force lockout (login ผิดเกิน 5 ครั้งใน 15 นาที ไม่ว่าจาก username หรือ IP เดียวกัน) รอ 15 นาทีแล้วลองใหม่ |
