import Link from 'next/link';
import { redirect } from 'next/navigation';
import { viewer } from '@/lib/auth';
import { isConfigured } from '@/lib/env';
import { ActionForm } from '@/components/action-form';
import { PasswordField } from '@/components/fields';
import { PaperArt } from '@/components/ui';
import { registerAction } from '@/app/actions';
export const metadata = { title: 'สมัครสมาชิก' };

export default async function Register() {
  if (!isConfigured()) redirect('/setup');
  if (await viewer()) redirect('/dashboard');
  return <section className="auth-layout"><div className="auth-story"><div className="eyebrow">Study · Share · Grow</div><h1>ความรู้ของคุณ<br/>มีคุณค่ากับใครอีกคน</h1><p>เริ่มแบ่งปันชีทที่ตั้งใจทำ และค้นพบสรุปดี ๆ จากเพื่อนในชุมชน</p><PaperArt/></div><div className="auth-form"><h2 className="page-title">สมัครสมาชิก</h2><p className="subtitle">สร้างบัญชีเพื่อเริ่มเรียนรู้ไปด้วยกัน</p><ActionForm action={registerAction} label="สร้างบัญชี"><label htmlFor="full_name">ชื่อที่แสดง</label><input id="full_name" name="full_name" required maxLength={100} autoComplete="name"/><label htmlFor="username">ชื่อผู้ใช้</label><input id="username" name="username" required minLength={4} maxLength={50} pattern="[A-Za-z0-9_]{4,50}" autoComplete="username"/><p className="field-help">ตัวอักษรอังกฤษ ตัวเลข หรือ _ จำนวน 4-50 ตัวอักษร</p><label htmlFor="email">อีเมลสำหรับยืนยันบัญชี</label><input id="email" name="email" type="email" required maxLength={100} autoComplete="email"/><p className="field-help">ใช้ยืนยันบัญชี เข้าสู่ระบบด้วยชื่อผู้ใช้</p><PasswordField autocomplete="new-password"/><p className="field-help">8-72 ไบต์ มีตัวอักษรอังกฤษและตัวเลข</p><PasswordField name="confirm_password" label="ยืนยันรหัสผ่าน" autocomplete="new-password"/></ActionForm><p className="auth-switch">มีบัญชีแล้ว? <Link href="/login">เข้าสู่ระบบ</Link></p></div></section>;
}
