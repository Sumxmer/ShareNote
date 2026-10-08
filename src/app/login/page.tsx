import Link from 'next/link';
import { redirect } from 'next/navigation';
import { isConfigured } from '@/lib/env';
import { viewer } from '@/lib/auth';
import { ActionForm } from '@/components/action-form';
import { PasswordField } from '@/components/fields';
import { PaperArt } from '@/components/ui';
import { loginAction } from '@/app/actions';
export const metadata = { title: 'เข้าสู่ระบบ' };

export default async function Login({ searchParams }: { searchParams: Promise<{ reason?: string }> }) {
  if (!isConfigured()) redirect('/setup');
  if (await viewer()) redirect('/dashboard');
  const params = await searchParams;
  return <section className="auth-layout"><div className="auth-story"><div className="eyebrow">Welcome back</div><h1>กลับมาเรียนรู้<br/>ไปด้วยกัน</h1><p>ชีทสรุปดี ๆ และความรู้จากเพื่อน ๆ รอคุณอยู่<br/>เข้าสู่ระบบเพื่อดาวน์โหลดและแบ่งปันชีทของคุณ</p><PaperArt/></div><div className="auth-form"><h2 className="page-title">เข้าสู่ระบบ</h2><p className="subtitle">ใช้บัญชีที่สมัครในเว็บนี้ หากเพิ่งสมัคร กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ</p>{params.reason === 'timeout' && <div className="alert alert-error">Session หมดอายุหลังไม่ใช้งาน 30 นาที กรุณาเข้าสู่ระบบใหม่</div>}{params.reason === 'confirmation' && <div className="alert alert-error">ลิงก์ยืนยันไม่ถูกต้องหรือหมดอายุ กรุณาตรวจอีเมลอีกครั้ง</div>}
    {params.reason === 'suspended' && <div className="alert alert-error">บัญชีไม่พร้อมใช้งานหรือสิทธิ์ถูกระงับ กรุณาติดต่อผู้ดูแลระบบ</div>}
    <ActionForm action={loginAction} label="เข้าสู่ระบบ"><label htmlFor="email">อีเมล</label><input id="email" name="email" type="email" autoComplete="email" required maxLength={100} placeholder="you@example.com"/><PasswordField/></ActionForm><p className="auth-switch">ยังไม่มีบัญชี? <Link href="/register">สมัครสมาชิกฟรี</Link></p></div></section>;
}
