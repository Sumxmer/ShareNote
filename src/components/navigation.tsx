import Link from 'next/link';
import { LogOut } from 'lucide-react';
import { viewer } from '@/lib/auth';
import { logoutAction } from '@/app/actions';

export async function Navigation() {
  const user = await viewer();
  return <header className="site-nav"><div className="nav-inner"><Link className="brand" href="/"><svg width="38" height="38" viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" rx="12" fill="#216653"/><path d="M10 11h8c2 0 3 1 3 3v16c-2-2-4-3-7-3h-4zm20 0h-6c-2 0-3 1-3 3v16c2-2 4-3 7-3h2z" fill="none" stroke="white" strokeWidth="2"/></svg>NoteShare</Link><nav className="nav-links" aria-label="เมนูหลัก"><Link href="/">สำรวจชีทสรุป</Link>{user && <><Link href="/dashboard">ชีทของฉัน</Link><Link href="/upload">แบ่งปันชีท</Link>{user.role === 'admin' && <Link href="/admin">ดูแลระบบ</Link>}</>}</nav><div className="nav-account">{user ? <><span className="avatar" aria-hidden="true">{user.full_name.slice(0,1)}</span><span className="user-name">{user.full_name}</span><form action={logoutAction}><button type="submit" className="btn btn-ghost" aria-label="ออกจากระบบ"><LogOut className="icon"/></button></form></> : <><Link href="/login" className="text-link">เข้าสู่ระบบ</Link><Link href="/register" className="btn btn-sm">เริ่มต้นใช้งาน</Link></>}</div></div></header>;
}
