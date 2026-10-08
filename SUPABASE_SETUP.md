# ตั้งค่า NoteShare ด้วย Next.js + Supabase

เว็บเวอร์ชันใหม่ไม่ใช้ Docker, PHP, MySQL หรือ phpMyAdmin ในการทำงาน ใช้ Node.js รัน Next.js และเชื่อม Supabase ที่ให้บริการ PostgreSQL, Auth และ Storage

## 1. เตรียมโปรเจกต์ Supabase

1. เปิด https://supabase.com/dashboard แล้วสร้าง New project
2. รอให้ฐานข้อมูลพร้อมใช้งาน
3. เปิด SQL Editor > New query
4. คัดลอกเนื้อหาทั้งหมดจาก `supabase/migrations/202610080001_noteshare.sql` แล้วกด Run
5. ตรวจว่า Table Editor มี `profiles`, `subjects`, `notes`, `comments`, `download_logs`, `security_logs`
6. ใน Storage ต้องมี bucket `notes` เป็น **Private** จำกัดไฟล์ 10 MiB

สคริปต์นี้สำหรับโปรเจกต์ใหม่และรันครั้งเดียว อย่าวางทับโปรเจกต์ที่มีตารางชื่อเดียวกัน ห้ามปรับ bucket เป็น Public หรือเพิ่ม policy ที่ให้ผู้ใช้ upload/download โดยตรง เพราะจะข้ามการตรวจชนิดไฟล์และการตรวจสิทธิ์ของเซิร์ฟเวอร์

## 2. ตั้งค่าไฟล์ในเครื่อง

ต้องมี Node.js 22.13 ขึ้นไป (แนะนำ Node.js 24 LTS) เปิด PowerShell ใน `D:\notesharing`:

```powershell
Copy-Item .env.example .env.local
npm install
```

เปิด `.env.local` แล้วใส่:

```dotenv
NEXT_PUBLIC_SUPABASE_URL=https://PROJECT_REF.supabase.co
NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY=ค่าจากโปรเจกต์
SUPABASE_SECRET_KEY=Secret-Key-สำหรับเซิร์ฟเวอร์
APP_SESSION_SECRET=ค่าสุ่มอย่างน้อย32ตัวอักษร
NEXT_PUBLIC_SITE_URL=http://localhost:3000
TRUST_PROXY=false
```

Project URL และ Publishable Key ดูได้จาก Connect หรือ Project Settings > API Keys ตามหน้าตาของ Dashboard ส่วน Secret Key ใช้เฉพาะฝั่งเซิร์ฟเวอร์ ห้ามส่งในแชต ห้าม commit และห้ามตั้งชื่อตัวแปรเป็น `NEXT_PUBLIC_...`

รองรับคีย์เดิมด้วย: `NEXT_PUBLIC_SUPABASE_ANON_KEY` แทน publishable key และ `SUPABASE_SERVICE_ROLE_KEY` แทน secret key ใส่รูปแบบใหม่หรือรูปแบบเดิมเพียงชุดเดียว

สร้าง Session Secret:

```powershell
node -e "console.log(require('node:crypto').randomBytes(32).toString('hex'))"
```

นำค่าที่ได้ใส่ `APP_SESSION_SECRET` ไฟล์ `.env.local` ถูกละเว้นจาก Git แล้ว หากเว็บยังแสดงหน้าตั้งค่า แปลว่ายังใส่ค่าไม่ครบหรือยังเป็น placeholder

หลังใส่คีย์แล้วรัน `npm run setup:storage` หนึ่งครั้ง เพื่อสร้าง private bucket `note-upload-staging` จำกัด 10 MiB และ MIME `application/octet-stream` โดยไม่เพิ่ม Storage policy ให้สมาชิก ไฟล์จากเบราว์เซอร์เข้า staging ด้วย signed upload URL ที่เซิร์ฟเวอร์ออกให้เฉพาะสมาชิก ก่อนเผยแพร่เซิร์ฟเวอร์ตรวจลายเซ็นเจ้าของ อายุคำขอ 15 นาที ขนาดและเนื้อหาไฟล์ แล้วเก็บไฟล์ที่ผ่านใน `notes` และลบ staging วิธีนี้รองรับไฟล์ 10 MiB แม้ Vercel จำกัด request body ที่ 4.5 MB

หากปิดหน้าจอหรือการเชื่อมต่อขาดระหว่างอัปโหลด อาจเหลือไฟล์ staging ที่ยังไม่ได้เผยแพร่ ผู้ดูแลควรล้างไฟล์ที่ค้างเกิน 24 ชั่วโมงใน Storage ตามระยะเวลาที่เหมาะสม Signed upload URL ของ Supabase มีอายุ 2 ชั่วโมง แต่ไม่สามารถใช้แทนคำขอเผยแพร่ที่ลงลายเซ็นของแอปได้

## 3. ตั้งค่า Authentication

1. Authentication > Sign In / Providers: เปิด Email + Password
2. Authentication > URL Configuration:
   - Site URL: `http://localhost:3000`
   - Redirect URL: `http://localhost:3000/auth/callback`
   - Redirect URL: `http://localhost:3000/auth/confirm`
3. แนะนำให้เปิด Confirm email และตั้ง SMTP ของตนเองเมื่อใช้งานจริง
4. Email Templates > Confirm signup: สามารถตั้งลิงก์ยืนยันเป็น

```html
<a href="{{ .SiteURL }}/auth/confirm?token_hash={{ .TokenHash }}&type=email">ยืนยันบัญชี NoteShare</a>
```

รองรับ PKCE callback ที่ `/auth/callback` ด้วย ลิงก์ชนิด PKCE ควรเปิดในเบราว์เซอร์เดียวกับที่สมัคร ส่วนลิงก์ TokenHash ข้างต้นเหมาะกับการยืนยันทางอีเมล

เมื่อเปลี่ยนโดเมน ให้แก้ Site URL, Redirect URL และ `NEXT_PUBLIC_SITE_URL` ให้ตรงกัน ใช้ HTTPS บนระบบจริงเพื่อให้ Auth Cookie มีธง Secure

## 4. เปิดเว็บ

```powershell
cd D:\notesharing
npm run dev
```

เปิด http://localhost:3000 ไม่ต้องเปิด Docker หากเปลี่ยน `.env.local` ให้หยุดด้วย Ctrl+C แล้วเปิดใหม่

สำหรับทดสอบ production:

```powershell
npm run build
npm start
```

## 5. ตั้ง Admin คนแรก

สมัครสมาชิกและยืนยันอีเมลผ่านเว็บก่อน จากนั้นผู้ดูแลโปรเจกต์ Supabase รันใน SQL Editor:

```sql
update public.profiles
set role = 'admin'
where email = 'อีเมลของบัญชีที่สมัครแล้ว';
```

บัญชีปกติไม่สามารถแก้ `role` ผ่าน Supabase API ได้ แม้เป็น Admin ของเว็บก็ไม่มีสิทธิ์ UPDATE ตาราง profiles โดยตรง หน้าจัดการผู้ใช้รองรับเฉพาะระงับ/ปลดระงับผ่าน RPC ที่ตรวจบทบาท ไม่ให้เปลี่ยนสิทธิ์ Admin ผ่านเว็บ

สิทธิ์จะอ่านจากฐานข้อมูลในคำขอถัดไป ไม่ต้องแก้ User Metadata และไม่ควรใส่ Admin ใน metadata ที่ส่งจากฟอร์มสมัคร

## 6. ความปลอดภัยและข้อจำกัด

- Password: Supabase Auth จัดเก็บและตรวจรหัสผ่าน; profiles ไม่เก็บ password hash
- Session: Auth Cookies เป็น HttpOnly; เช็กผู้ใช้กับ Supabase และตรวจ Activity Cookie ที่ลงลายเซ็น มี idle timeout 30 นาทีสำหรับการเข้าใช้งานผ่านเว็บ ไม่ใช่การตั้งอายุ JWT ของ Supabase เป็น 30 นาที
- CSRF: การเปลี่ยนข้อมูลทำผ่าน Next.js Server Actions (POST + ตรวจ Origin/Host) ไม่เปิด logout/delete ผ่าน GET
- XSS: React แสดงข้อความด้วยการ escape; ไม่มี dangerouslySetInnerHTML; CSP ใช้ nonce สำหรับสคริปต์
- SQL Injection: ส่งค่าผ่าน Supabase query/RPC โดยไม่ประกอบ SQL จากข้อมูลผู้ใช้; ฟังก์ชัน SQL ใช้ค่าพารามิเตอร์โดยตรง
- Database: เปิด RLS ทุกตารางและถอนสิทธิ์เขียนตรง ให้ใช้ RPC ที่ตรวจบัญชีและเจ้าของข้อมูล
- Logs: สมาชิกอ่าน Security Logs ไม่ได้; ผู้ดูแลเว็บอ่านได้แต่ไม่มีสิทธิ์แก้/ลบ; service key/ผู้ดูแลฐานข้อมูลยังมีอำนาจมากกว่า จึงไม่ใช่ log ที่แก้ไขไม่ได้โดยทุกบัญชี
- Upload: ตรวจนามสกุล เนื้อหา MIME โครงสร้าง Office และภาพ; ไม่เกิน 10 MiB; ชื่อสุ่ม; bucket private; ยังไม่มี antivirus และโควตาพื้นที่รายผู้ใช้
- Download: ต้อง Login และกดผ่าน Server Action เพื่อออก ticket อายุ 60 วินาที เซิร์ฟเวอร์ตรวจซ้ำก่อนส่งไฟล์และบันทึกประวัติ ไม่มี public URL ของไฟล์
- Counter: นับคำขอดาวน์โหลดที่ผ่านการตรวจ ไม่ใช่จำนวนคนไม่ซ้ำหรือยืนยันว่าได้รับครบ
- Soft Delete: ชีทเปลี่ยนสถานะ removed และไฟล์ยังอยู่; ความคิดเห็นลบแถวจริง
- Brute force: นับ LOGIN_FAILED 5 ครั้งใน 15 นาทีล่าสุดตามอีเมล; IP ใช้เมื่อ TRUST_PROXY=true และตั้ง trusted proxy ถูกต้องเท่านั้น ไม่ได้รีเซ็ตจำนวนครั้งผิดเมื่อ Login สำเร็จทันที
- ประวัติ upload/comment/modify/download/status บันทึกในธุรกรรมของฐานข้อมูล การส่งไฟล์ไป Storage และธุรกรรม DB เป็นคนละระบบ มีการลบไฟล์ชดเชยเมื่อ DB ล้มเหลว แต่กรณี process หยุดทันทีอาจต้องเก็บกวาด orphan ภายหลัง
- Supabase SQL Editor ทำงานด้วยสิทธิ์ดูแลฐานข้อมูล ไม่ได้มีสิทธิ์เหมือนสมาชิกเว็บ

อย่าเปิด service key ให้เบราว์เซอร์ โค้ดใช้ `server-only` และตรวจผู้ใช้ก่อนเรียก Storage ด้วยคีย์นี้ การใช้งานทั่วไปกับฐานข้อมูลใช้คีย์ publishable และ JWT ของผู้ใช้เพื่อให้ RLS มีผล

## 7. ข้อมูลและเอกสารจากเวอร์ชัน PHP

- โค้ด PHP/MySQL และคู่มือเดิมเก็บใน `legacy/php/`
- ไฟล์อัปโหลดเดิมยังอยู่ใน `uploads/` ที่รากโปรเจกต์
- ไม่ได้ลบ Docker volumes หรือข้อมูล MySQL เดิม
- การเปิด Next.js ไม่ได้ย้ายข้อมูล MySQL ไป Supabase อัตโนมัติ
- บัญชีใหม่เป็น Supabase Auth และใช้ UUID ต้องสมัครใหม่หรือจัดทำขั้นตอนย้ายบัญชีผ่าน Auth Admin API ก่อน mapping ข้อมูลเดิม ห้ามคัดลอก password hash ลง profiles
- ไฟล์ `.sql` ของ MySQL เดิมนำไปรันเป็น PostgreSQL ตรง ๆ ไม่ได้
- รายงาน PDF และคำถาม-คำตอบเดิมอ้างอิง PHP/MySQL และยังไม่ได้แก้ตามเทคโนโลยีใหม่

## 8. ตรวจสอบโค้ด

```powershell
npm run lint
npm run typecheck
npm test
npm run build
```

ชุดทดสอบใหม่ใช้ PostgreSQL ผ่าน PGlite เพื่อรัน migration และตรวจ RLS/สิทธิ์/RPC โดยจำลองเฉพาะ auth/storage schemas ของแพลตฟอร์ม พร้อมทดสอบ input, session, ticket, XSS rendering และ file validation ไม่ต้องใช้ Docker

ชุดทดสอบนี้ไม่แทนการทดสอบ Supabase Auth, email, Storage และการดาวน์โหลดกับโปรเจกต์จริง เมื่อใส่คีย์แล้วควรทดสอบสมัคร/ยืนยันอีเมล/Login/อัปโหลด/ดาวน์โหลด/ระงับบัญชีแบบครบวงจร

เอกสารทางการ:
- https://supabase.com/docs/guides/auth/server-side/creating-a-client
- https://supabase.com/docs/guides/database/postgres/row-level-security
- https://supabase.com/docs/guides/storage/security/access-control
- https://nextjs.org/docs/app/guides/data-security
