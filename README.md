NoteShare 📚
เว็บแชร์ชีทสรุปบทเรียนไว้ใช้อ่านสอบ ทำส่งโปรเจกต์ครับ พัฒนาด้วย Next.js 16 (App Router), React, TypeScript แล้วก็ใช้ Supabase ทำเป็น Backend + Database + Storage ครับ

วิธีรันโปรเจกต์บนเครื่อง (Local)
เปิด PowerShell แล้วรันตามนี้ได้เลยครับ:

PowerShell
cd D:\notesharing
npm install
Copy-Item .env.example .env.local
# อย่าลืมไปเอาค่า Config จาก Dashboard ของ Supabase มาใส่ใน .env.local ด้วยนะครับ
npm run setup:storage
npm run dev
เสร็จแล้วเปิดเบราว์เซอร์เข้า http://localhost:3000 ได้เลย ถ้ายังไม่ได้ผูก Supabase หน้าเว็บจะขึ้นเตือนให้ไปตั้งค่าก่อน (โปรเจกต์นี้ต่อ Database จริง ไม่มี Mock Data นะครับ)

วิธีผูก Supabase ตั้งค่าตาราง ยืนยันอีเมล แล้วก็ตั้งสิทธิ์ Admin ลองดูในไฟล์ SUPABASE_SETUP.md ได้เลยครับ เขียนสรุปไว้ให้แล้ว

ระบบทำอะไรได้บ้าง?
คนทั่วไป (ยังไม่ล็อกอิน): เปิดหาชีท กรองตามหมวดวิชา เข้าไปอ่านรายละเอียดและดูคอมเมนต์ใต้ชีทได้

สมาชิก (ล็อกอินแล้ว):

สมัครสมาชิก ยืนยันตัวตนผ่านอีเมล

เข้าสู่ระบบด้วยชื่อผู้ใช้ + รหัสผ่าน (ตัวพิมพ์เล็ก/ใหญ่ต้องตรงกับตอนสมัคร) อีเมลใช้ยืนยันบัญชี เซิร์ฟเวอร์ค้นหาอีเมลภายในโดยไม่ส่งกลับให้เบราว์เซอร์

อัปโหลดชีทสรุปเพื่อส่งให้ Admin ตรวจสอบก่อนเผยแพร่ และโหลดชีทที่ผ่านการอนุมัติแล้วได้

คอมเมนต์ใต้ชีท ลบคอมเมนต์ตัวเอง หรือปิดการเผยแพร่ชีทของตัวเองได้

Admin:

มีหน้า Dashboard สรุปภาพรวม

ตรวจไฟล์และอนุมัติ/ไม่อนุมัติชีทก่อนเผยแพร่ จัดการลบ/ซ่อนชีท ลบคอมเมนต์ที่ไม่เหมาะสม

แบนหรือปลดแบนผู้ใช้งาน และดู Security Logs ได้

หมายเหตุ: ในระบบไม่มีปุ่มกดตั้งใครเป็น Admin นะครับ เพื่อความปลอดภัย ถ้าจะให้ใครเป็น Admin ต้องไปแก้ role ผ่าน SQL Editor บน Supabase เอาเองครับ

เงื่อนไขไฟล์และการอัปโหลด
รองรับไฟล์: .pdf, .doc, .docx, .ppt, .pptx, .jpg, .jpeg, .png

ขนาดไฟล์: ไม่เกิน 10 MB ต่อไฟล์

เทคนิคที่ใช้: ยิงไฟล์ขึ้น Private Staging บน Supabase Storage ก่อน แล้วค่อยให้ฝั่งเซิร์ฟเวอร์ตรวจความถูกต้องอีกรอบถึงจะเปิดให้โหลด วิธีนี้ช่วยแก้ปัญหา Payload Size เกิน 4.5 MB ของ Vercel Serverless Function ได้ครับ

โครงสร้างโฟลเดอร์หลักๆ
Plaintext
src/
  app/                  พวกหน้า UI (Next.js App Router) กับ Server Actions
    admin/              หน้าจัดการของผู้ดูแลระบบ (ดูสถิติ, จัดการ User, ดู Logs)
    auth/               หน้าระบบล็อกอิน และพวก Callback ยืนยันอีเมล
    download/[id]/      ตัวจัดการจ่ายไฟล์ดาวน์โหลด (ตรวจสิทธิ์ก่อนปล่อยไฟล์)
    globals.css         ไฟล์ CSS กลาง
  components/           พวก UI ทั่วไป เช่น ปุ่ม การ์ด ฟอร์ม Navbar
  lib/                  ฟังก์ชันช่วยเหลือต่างๆ
    supabase/server.ts  ตัวเชื่อม Supabase ฝั่ง Server
    auth.ts             ฟังก์ชันเช็กสิทธิ์และสถานะบัญชี
    tokens.ts           ทำ Cookie กับ Download Ticket
    uploads.ts          ตรวจความถูกต้องของไฟล์
    upload-ticket.ts    ทำ Ticket ยืนยันการอัปโหลดของ User
    validation.ts       ตรวจความถูกต้องของข้อมูลใน Form (Validation)
    data.ts             พวกคำสั่ง Query ดึงข้อมูลชีท
  proxy.ts              ตัวจัดการ Cookie, ดักจับ Session Timeout, ทำ CSP Nonce
supabase/migrations/    ไฟล์ Migration สร้าง Table, RLS, และ RPC ใน PostgreSQL
tests/next/             ไฟล์ Unit Test และ Integration Test
legacy/php/             โค้ดเก่าตอนทำด้วย PHP/MySQL เก็บไว้ดูอ้างอิงเฉยๆ ครับ
uploads/                โฟลเดอร์ไฟล์อัปโหลดเดิม (ยังไม่ได้โยกขึ้น Cloud)
ความปลอดภัย (Security)
ระบบ Auth ใช้ของ Supabase ทำงานคู่กับ HttpOnly Cookie ป้องกัน XSS

มี Idle Timeout ตัด Session อัตโนมัติถ้าไม่มีการใช้งานเกิน 30 นาที

ฐานข้อมูลตั้งค่า Row Level Security (RLS) ล็อกสิทธิ์ระดับแถวข้อมูล และเช็กสิทธิ์ซ้ำผ่าน RPC

จัดการ Secret Key ไว้ฝั่ง Server ทั้งหมด ไม่หลุดไปที่ Client

มีทั้งหมด 6 ตารางหลัก: profiles, subjects, notes, comments, download_logs, security_logs (ส่วนข้อมูลล็อกอินเก็บใน auth.users ของ Supabase)

ทดสอบโค้ดก่อนส่งงาน
PowerShell
npm run check
คำสั่งนี้จะรันทั้ง Lint, เช็ก TypeScript, รัน Tests แล้วลอง Build โปรเจกต์รอบนึง เพื่อดูว่ามี Error ตรงไหนก่อน Deploy ครับ


การอนุมัติชีทและการอัปเดตฐานข้อมูลเดิม

ชีทใหม่เป็น `pending` และมีเพียง Admin ที่ยังใช้งานได้เป็นผู้อนุมัติให้เป็น `active` สมาชิกอื่นจะไม่เห็นหรือดาวน์โหลดชีทระหว่างรอตรวจ หากไม่อนุมัติจะเป็น `rejected` เจ้าของสามารถแก้ไขและส่งตรวจใหม่ได้ การแก้รายละเอียดชีทที่เผยแพร่แล้วจะกลับไปรอตรวจด้วย

ก่อน deploy รุ่นนี้ ให้รัน `supabase/migrations/20261008162738_note_approval.sql` เพิ่มเติมใน SQL Editor หากมีตารางเดิมแล้วไม่ต้องรัน migration ไฟล์แรกซ้ำ ชีทที่เผยแพร่เดิมจะเข้าคิวอนุมัติหนึ่งครั้ง บัญชีและไฟล์ยังอยู่ครบ
