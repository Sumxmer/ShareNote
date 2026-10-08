import { requireAdmin } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, formatDate, type SecurityLog } from '@/lib/data';
export const metadata = { title: 'บันทึกเหตุการณ์' };
const actions = ['REGISTER','LOGIN_SUCCESS','LOGIN_FAILED','ACCOUNT_LOCKOUT','LOGOUT','NOTE_CREATE','NOTE_UPDATE','NOTE_DELETE','FILE_UPLOAD','FILE_DOWNLOAD','COMMENT_CREATE','COMMENT_DELETE','ADMIN_USER_STATUS_CHANGE'];

export default async function AdminLogs({ searchParams }: { searchParams: Promise<{ action?: string }> }) {
  await requireAdmin(); const param = (await searchParams).action;
  const action = typeof param==='string' && actions.includes(param) ? param : '';
  const db = await supabaseServer();
  let query = db.from('security_logs').select('*').order('created_at',{ ascending: false }).order('log_id',{ ascending: false }).limit(200);
  if (action) query = query.eq('action',action);
  const result = await query; checkDb(result.error,'security-logs');
  const logs = (result.data || []) as SecurityLog[];
  return <><div className="page-heading"><div><div className="eyebrow">Security & activity</div><h1 className="page-title">บันทึกเหตุการณ์</h1><p className="subtitle">ตรวจสอบกิจกรรมและเหตุการณ์ความปลอดภัย 200 รายการล่าสุด</p></div></div><div className="card table-card"><div className="table-title"><h3>Security Logs</h3><form action="/admin/logs" method="GET" className="filter-form"><label htmlFor="action"><span className="sr-only">ประเภทเหตุการณ์</span><select id="action" name="action" defaultValue={action}><option value="">ทุกเหตุการณ์</option>{actions.map(a => <option value={a} key={a}>{a}</option>)}</select></label><button type="submit" className="btn btn-sm">กรอง</button></form></div><div className="table-wrap"><table><thead><tr><th>เวลา</th><th>บัญชี</th><th>เหตุการณ์</th><th>รายละเอียด</th><th>IP</th></tr></thead><tbody>{logs.map(log => <tr key={log.log_id}><td>{formatDate(log.created_at)}</td><td>{log.username_attempt || '-'}</td><td><span className={`event-tag ${['LOGIN_FAILED','ACCOUNT_LOCKOUT'].includes(log.action) ? 'failed' : ''}`}>{log.action}</span></td><td className="log-detail">{log.detail || '-'}</td><td>{log.ip_address || '-'}</td></tr>)}{!logs.length && <tr><td colSpan={5} className="table-empty">ยังไม่มีเหตุการณ์ในหมวดนี้</td></tr>}</tbody></table></div></div></>;
}
