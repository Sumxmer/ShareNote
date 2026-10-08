# NoteShare

ระบบแบ่งปันชีทสรุปสำหรับทบทวนบทเรียน พัฒนาด้วย **Next.js 16 + React + TypeScript + Supabase** เปิดเว็บโดยไม่ต้องใช้ Docker

## เริ่มต้น

ต้องมี Node.js 22.13 ขึ้นไป แนะนำ Node.js 24 LTS

```powershell
cd D:\notesharing
npm install
Copy-Item .env.example .env.local
# ใส่ค่าของโปรเจกต์ Supabase ใน .env.local
npm run setup:storage
npm run dev
```

เปิด **http://localhost:3000** หากยังไม่ได้ตั้งค่า จะมีหน้าคำแนะนำให้เริ่มต้น ไม่มีข้อมูลสมมติหรือ backend จำลองแทน Supabase

อ่าน **[SUPABASE_SETUP.md](SUPABASE_SETUP.md)** สำหรับการสร้างโปรเจกต์ รัน migration ตั้งอีเมลยืนยัน และตั้ง Admin คนแรก

## ฟังก์ชัน

- ผู้เยี่ยมชม: ค้นหาชีท กรองรายวิชา ดูรายละเอียดและความคิดเห็น
- สมาชิก: สมัคร/ยืนยันอีเมล/Login อัปโหลด ดาวน์โหลด แสดงความคิดเห็น แก้ไข/หยุดเผยแพร่ชีทของตน และลบความคิดเห็นของตน
- ผู้ดูแล: ภาพรวมระบบ จัดการชีท/ความคิดเห็น ระงับหรือปลดระงับบัญชี และดู Security Logs
- **ไม่มีปุ่มหรือ RPC เปลี่ยน role เป็น Admin** ต้องตั้งโดยผู้ดูแลโปรเจกต์ใน Supabase SQL Editor
- Login ใช้อีเมล + รหัสผ่าน ชื่อผู้ใช้เป็นชื่ออ้างอิง/แสดงในชุมชน
- รับไฟล์ PDF, DOC, DOCX, PPT, PPTX, JPG, JPEG, PNG ไม่เกิน 10 MiB ต่อไฟล์
- อัปโหลดโดยตรงเข้า private staging ของ Supabase แล้วให้เซิร์ฟเวอร์ตรวจเนื้อหาก่อนเผยแพร่ จึงรองรับ 10 MiB บน Vercel

## โครงสร้าง

```text
src/
  app/                  หน้าจอ Next.js App Router และ Server Actions
    admin/              ภาพรวม ผู้ใช้ ชีท และ Logs
    auth/               ยืนยันอีเมลและ callback ของ Supabase
    download/[id]/      ส่งไฟล์หลังตรวจบัญชีและ download ticket
    globals.css         รูปแบบ UX/UI กลาง
  components/           เมนู ฟอร์ม ปุ่ม การ์ด และองค์ประกอบร่วม
  lib/
    supabase/server.ts  เชื่อม Supabase ฝั่งเซิร์ฟเวอร์
    auth.ts             ตรวจบัญชี สถานะ และบทบาท
    tokens.ts           ลงลายเซ็น Activity Cookie / download ticket
    uploads.ts          ตรวจไฟล์ก่อนเก็บใน Storage
    upload-ticket.ts    ลงลายเซ็นคำขออัปโหลดที่ผูกกับสมาชิกและไฟล์
    validation.ts      ตรวจข้อมูลฟอร์ม
    data.ts             อ่านข้อมูลและค้นหาชีท
  proxy.ts              refresh Auth Cookie, idle timeout, CSP nonce
supabase/migrations/    SQL สร้างตาราง RLS และ RPC
tests/next/             ทดสอบ PostgreSQL/RLS และ security helpers
legacy/php/             โค้ด PHP/MySQL เดิม เก็บไว้อ้างอิง
uploads/                ไฟล์อัปโหลดเดิม ยังไม่ได้ย้ายไป Supabase
```

## Security

ใช้ Supabase Auth, HttpOnly Cookies, idle timeout 30 นาที, Next.js Server Actions, React output escaping, CSP nonce, Row Level Security และ RPC ที่ตรวจสิทธิ์ในฐานข้อมูล Secret Key ใช้เฉพาะเซิร์ฟเวอร์สำหรับไฟล์ใน private bucket และ authentication logging ไม่ส่งให้ client

ตารางของแอปมี 6 ตาราง: profiles, subjects, notes, comments, download_logs, security_logs ส่วน auth.users เป็นตารางที่ Supabase ดูแลรหัสผ่านและตัวตน

## การตรวจสอบ

```powershell
npm run check
```

ตรวจ lint, TypeScript, tests และ production build ชุดทดสอบไม่ใช้ Docker และยังต้องทดสอบ Auth/Storage กับโปรเจกต์ Supabase จริงเมื่อใส่คีย์

## หมายเหตุการย้ายระบบ

โค้ดและฐานข้อมูลเดิมไม่ได้ถูกย้ายเข้า Supabase อัตโนมัติ คู่มือและ PDF เดิมยังอ้างอิง PHP/MySQL อ่านรายละเอียดใน SUPABASE_SETUP.md ก่อนใช้ข้อมูลหรือเอกสารเดิมกับระบบใหม่
