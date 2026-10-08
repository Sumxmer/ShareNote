import type { Metadata } from 'next';
import { Navigation } from '@/components/navigation';
import './globals.css';

export const metadata: Metadata = { title: { default: 'NoteShare · แบ่งปันความรู้', template: '%s · NoteShare' }, description: 'พื้นที่แบ่งปันชีทสรุปและความรู้สำหรับการเรียนและติวสอบ' };
export const dynamic = 'force-dynamic';

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return <html lang="th" data-scroll-behavior="smooth"><body><a href="#main" className="skip-link">ข้ามไปเนื้อหา</a><Navigation/><main className="container" id="main">{children}</main><footer className="site-footer"><span><strong>NoteShare</strong> · ความรู้ดี ๆ เริ่มต้นที่การแบ่งปัน</span><span>Next.js + Supabase · {new Date().getFullYear()}</span></footer></body></html>;
}
