import { requireAdmin, type Profile } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, formatDate } from '@/lib/data';
import { ConfirmForm, Submit } from '@/components/action-form';
import { setUserStatusAction } from '@/app/actions';
export const metadata = { title: 'จัดการผู้ใช้งาน' };

export default async function AdminUsers() {
  const user = await requireAdmin();
  const result = await (await supabaseServer()).rpc('admin_users'); checkDb(result.error,'admin-users');
  const rows = (result.data || []) as (Profile & { email: string; created_at: string })[];
  return <><div className="page-heading"><div><div className="eyebrow">People in the community</div><h1 className="page-title">ผู้ใช้งานในระบบ</h1><p className="subtitle">จัดการสถานะบัญชี เพื่อดูแลชุมชนให้พร้อมใช้งาน</p></div><span className="count-label">{rows.length} บัญชี</span></div><div className="card table-card"><div className="table-wrap"><table><thead><tr><th>ผู้ใช้งาน</th><th>อีเมล</th><th>สิทธิ์</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>{rows.map(row => <tr key={row.user_id}><td><strong>{row.full_name}</strong><div className="note-meta">@{row.username} · {formatDate(row.created_at)}</div></td><td>{row.email}</td><td><span className={`badge ${row.role==='admin' ? 'badge-admin' : ''}`}>{row.role==='admin' ? 'Admin' : 'User'}</span></td><td><span className={`badge ${row.status==='suspended' ? 'badge-suspended' : ''}`}>{row.status==='active' ? 'ใช้งานปกติ' : 'ระงับบัญชี'}</span></td><td>{row.user_id===user.user_id ? <span className="note-meta">บัญชีของคุณ</span> : <ConfirmForm action={setUserStatusAction} message="ยืนยันการเปลี่ยนสถานะบัญชีนี้?"><input type="hidden" name="user_id" value={row.user_id}/><input type="hidden" name="status" value={row.status==='active' ? 'suspended' : 'active'}/><Submit className={`btn btn-sm ${row.status==='active' ? 'btn-danger' : 'btn-secondary'}`}>{row.status==='active' ? 'ระงับ' : 'ปลดระงับ'}</Submit></ConfirmForm>}</td></tr>)}</tbody></table></div></div></>;
}
