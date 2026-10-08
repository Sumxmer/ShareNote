# เผยแพร่ NoteShare บน Vercel

โปรเจกต์ใช้ Next.js และ Supabase ไม่ต้องเปิด Docker เมื่อเผยแพร่แล้ว ผู้ใช้เปิดจากมือถือหรือคอมพิวเตอร์เครื่องอื่นได้ผ่าน HTTPS

ก่อน deploy รุ่นที่มีระบบอนุมัติชีท ให้รัน `supabase/migrations/20261008162738_note_approval.sql` ในฐานข้อมูลที่มีตารางเดิมแล้ว ชีทที่เผยแพร่เดิมจะเข้าคิวอนุมัติหนึ่งครั้ง การรันไฟล์ซ้ำจะไม่ถอนการอนุมัติที่ทำหลังอัปเดต

## ตั้งค่าครั้งแรก

1. ทำตาม `SUPABASE_SETUP.md` และรัน `npm run setup:storage` หลังใส่คีย์ของ Supabase ใน `.env.local`
2. นำ repository นี้เข้า Vercel เลือก Framework **Next.js**, Root Directory เป็นรากโปรเจกต์ และ Node.js **24.x**
3. ตั้ง Environment Variables สำหรับ **Production**:

| ชื่อ | ประเภท | ค่า |
|---|---|---|
| `NEXT_PUBLIC_SUPABASE_URL` | Config | Project URL ของ Supabase |
| `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY` | Config | Publishable Key |
| `SUPABASE_SECRET_KEY` | Secret | Server Secret Key |
| `APP_SESSION_SECRET` | Secret | ค่าสุ่มใหม่อย่างน้อย 32 ตัวอักษรสำหรับ production |
| `TRUST_PROXY` | Config | `false` |
| `NEXT_PUBLIC_SITE_URL` | Config | URL จริงแบบ HTTPS หากใช้ custom domain |

เมื่อไม่ตั้ง `NEXT_PUBLIC_SITE_URL` เว็บใช้ `VERCEL_PROJECT_PRODUCTION_URL` ของ Vercel โดยอัตโนมัติ ห้ามนำ `.env.local` หรือ `.vercel/` ขึ้น Git

4. Deploy production แล้วตรวจหน้าแรก สมัครสมาชิก และ Login
5. ใน Supabase > Authentication > URL Configuration ตั้ง **Site URL** เป็น URL production และเพิ่ม Redirect URLs เป็น `https://โดเมนจริง/auth/callback` และ `https://โดเมนจริง/auth/confirm` เก็บ localhost ไว้ได้หากยังพัฒนาในเครื่อง
6. หากใช้ email template แบบ TokenHash ในคู่มือ Supabase ลิงก์ยืนยันจะอ้างถึง Site URL ที่ตั้งไว้และเปิดข้ามอุปกรณ์ได้ ลิงก์เก่าที่อ้าง localhost ควรใช้การส่งอีเมลใหม่

## อัปโหลดบน Vercel

เบราว์เซอร์ส่งไฟล์ไม่เกิน 10 MiB ไปยัง private bucket `note-upload-staging` ด้วย signed upload URL เซิร์ฟเวอร์ตรวจเจ้าของและอายุ upload ticket (15 นาที) แล้วตรวจ bytes, MIME และโครงสร้างไฟล์ก่อนเก็บใน private bucket `notes` จึงไม่ส่งไฟล์ขนาดใหญ่ผ่าน request body ของ Vercel และไม่เปิดให้สมาชิกเขียนไฟล์เผยแพร่โดยตรง

ไฟล์ staging จะถูกลบหลังประมวลผล หากผู้ใช้ปิดหน้าหรือเครือข่ายขาดอาจเหลือไฟล์ที่ค้าง ผู้ดูแลควรตรวจและล้างไฟล์ staging ที่เก่ากว่า 24 ชั่วโมง ส่วนไฟล์เผยแพร่ยังใช้การตรวจสมาชิกและลิงก์ดาวน์โหลดของแอป ห้ามเปลี่ยน bucket เป็น Public

## ตรวจสอบก่อนอัปเวอร์ชัน

รัน `npm run check` แล้ว push เข้า `main` เมื่อเชื่อม GitHub integration แล้ว Vercel จะสร้าง production deployment จาก branch นี้ ดูผล build และ runtime errors ใน Vercel Dashboard ทุกครั้ง คีย์และข้อมูลเดิมใน `.env.local`, `uploads/`, `tmp/`, `output/` และ `legacy/` จะไม่ถูกส่งไปกับ CLI deployment
