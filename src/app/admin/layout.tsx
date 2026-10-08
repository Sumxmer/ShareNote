import Link from 'next/link';
import { requireAdmin } from '@/lib/auth';

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  await requireAdmin();
  return <><nav className="admin-tabs" aria-label="เมนูผู้ดูแลระบบ"><Link href="/admin">ภาพรวม</Link><Link href="/admin/users">ผู้ใช้งาน</Link><Link href="/admin/notes">ชีททั้งหมด</Link><Link href="/admin/logs">บันทึกเหตุการณ์</Link></nav>{children}</>;
}
