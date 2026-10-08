import { UserRound, BookOpen, Download, MessageCircle } from 'lucide-react';
import { requireAdmin } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, formatDate, type SecurityLog } from '@/lib/data';
import { Stats } from '@/components/ui';
export const metadata = { title: 'ภาพรวมระบบ' };

export default async function AdminPage() {
  await requireAdmin(); const db = await supabaseServer();
  const [count, recent] = await Promise.all([db.rpc('admin_stats'),db.from('security_logs').select('*').order('created_at',{ ascending: false }).order('log_id',{ ascending: false }).limit(10)]);
  checkDb(count.error,'admin-stats'); checkDb(recent.error,'admin-recent');
  const stats = count.data as Record<string,number>;
  return <><div className="page-heading"><div><div className="eyebrow">Community overview</div><h1 className="page-title">ภาพรวมระบบ</h1><p className="subtitle">ดูแลให้พื้นที่แบ่งปันความรู้พร้อมใช้งานสำหรับทุกคน</p></div></div><Stats items={[
    { value: stats.users,label: 'บัญชีผู้ใช้',icon: <UserRound className="icon"/> },
    { value: stats.notes,label: 'ชีทที่เผยแพร่',icon: <BookOpen className="icon"/> },
    { value: stats.downloads,label: 'ดาวน์โหลดทั้งหมด',icon: <Download className="icon"/> },
    { value: stats.comments,label: 'ความคิดเห็น',icon: <MessageCircle className="icon"/> },
  ]}/><div className="card table-card"><div className="table-title"><h3>เหตุการณ์ล่าสุด</h3><span className="count-label">10 รายการล่าสุด</span></div><div className="table-wrap"><table><thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>เหตุการณ์</th><th>รายละเอียด</th></tr></thead><tbody>{((recent.data || []) as SecurityLog[]).map(log => <tr key={log.log_id}><td>{formatDate(log.created_at)}</td><td>{log.username_attempt || '-'}</td><td><span className="event-tag">{log.action}</span></td><td className="log-detail">{log.detail || '-'}</td></tr>)}</tbody></table></div></div></>;
}
