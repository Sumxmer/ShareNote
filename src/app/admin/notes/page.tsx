import Link from 'next/link';
import { requireAdmin } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, formatDate, type Note } from '@/lib/data';
import { ConfirmForm, Submit } from '@/components/action-form';
import { removeNoteAction } from '@/app/actions';
import { EmptyState } from '@/components/ui';
export const metadata = { title: 'จัดการชีททั้งหมด' };

export default async function AdminNotes({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  await requireAdmin(); const params = await searchParams;
  const requested = typeof params.page==='string' && /^[1-9][0-9]{0,5}$/.test(params.page) ? Number(params.page) : 1;
  const db = await supabaseServer();
  const count = await db.from('notes').select('note_id',{ count: 'exact',head: true }); checkDb(count.error,'admin-notes-count');
  const pages = Math.max(1,Math.ceil((count.count || 0)/50)); const page = Math.min(requested,pages);
  const result = await db.from('note_catalog').select('*').order('created_at',{ ascending: false }).order('note_id',{ ascending: false }).range((page-1)*50,page*50-1);
  checkDb(result.error,'admin-notes'); const notes = (result.data || []) as Note[];
  return <><div className="page-heading"><div><div className="eyebrow">Shared knowledge</div><h1 className="page-title">ชีททั้งหมด</h1><p className="subtitle">ตรวจสอบและดูแลชีทที่สมาชิกแบ่งปันในชุมชน</p></div><span className="count-label">{count.count || 0} ชีท</span></div>{!notes.length ? <EmptyState title="ยังไม่มีชีทในระบบ">เมื่อสมาชิกเริ่มแบ่งปัน ชีทจะแสดงที่นี่</EmptyState> : <div className="card table-card"><div className="table-wrap"><table><thead><tr><th>ชีทสรุป</th><th>เจ้าของ</th><th>รายวิชา</th><th>ดาวน์โหลด</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>{notes.map(note => <tr key={note.note_id}><td>{note.status==='active' ? <Link href={`/notes/${note.note_id}`}>{note.title}</Link> : <strong>{note.title}</strong>}<div className="note-meta">{formatDate(note.created_at)}</div></td><td>{note.username}</td><td>{note.subject_name}</td><td>{note.download_count}</td><td><span className={`badge ${note.status==='removed' ? 'badge-suspended' : ''}`}>{note.status==='active' ? 'เผยแพร่' : 'ถูกนำออก'}</span></td><td>{note.status==='active' ? <div className="actions"><Link href={`/notes/${note.note_id}/edit`} className="btn btn-sm btn-secondary">แก้ไข</Link><ConfirmForm action={removeNoteAction} message="ยืนยันการนำชีทนี้ออกจากการเผยแพร่?"><input type="hidden" name="note_id" value={note.note_id}/><Submit className="btn btn-sm btn-danger">ลบ</Submit></ConfirmForm></div> : '-'}</td></tr>)}</tbody></table></div></div>}{pages>1 && <nav className="pagination" aria-label="หน้ารายการชีท">{page>1 && <Link className="btn btn-secondary btn-sm" href={`?page=${page-1}`}>ก่อนหน้า</Link>}<span>หน้า {page} จาก {pages}</span>{page<pages && <Link className="btn btn-secondary btn-sm" href={`?page=${page+1}`}>ถัดไป</Link>}</nav>}</>;
}
