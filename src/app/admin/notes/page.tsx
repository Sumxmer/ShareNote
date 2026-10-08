import Link from 'next/link';
import { requireAdmin } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, formatDate, type Note } from '@/lib/data';
import { ConfirmForm, Submit } from '@/components/action-form';
import { removeNoteAction } from '@/app/actions';
import { EmptyState } from '@/components/ui';
import { NoteStatusBadge, NoteReviewActions } from '@/components/note-status';
export const metadata = { title: 'ตรวจสอบและอนุมัติชีท' };

const filters = [ ['pending', 'รออนุมัติ'], ['active', 'เผยแพร่แล้ว'], ['rejected', 'ไม่อนุมัติ'], ['all', 'ทั้งหมด'] ] as const;

export default async function AdminNotes({ searchParams }: { searchParams: Promise<{ page?: string; status?: string }> }) {
  await requireAdmin(); const params = await searchParams;
  const status = filters.some(([value]) => value === params.status) ? params.status! : 'pending';
  const requested = typeof params.page === 'string' && /^[1-9][0-9]{0,5}$/.test(params.page) ? Number(params.page) : 1;
  const db = await supabaseServer();
  let counter = db.from('notes').select('note_id', { count: 'exact', head: true });
  if (status !== 'all') counter = counter.eq('status', status);
  const count = await counter; checkDb(count.error, 'admin-notes-count');
  const pages = Math.max(1, Math.ceil((count.count || 0) / 50)); const page = Math.min(requested, pages);
  let query = db.from('note_catalog').select('*');
  if (status !== 'all') query = query.eq('status', status);
  const result = await query.order('created_at', { ascending: false }).order('note_id', { ascending: false }).range((page - 1) * 50, page * 50 - 1);
  checkDb(result.error, 'admin-notes'); const notes = (result.data || []) as Note[];
  return <>
    <div className="page-heading"><div><div className="eyebrow">Review &amp; share</div><h1 className="page-title">ตรวจสอบและอนุมัติชีท</h1><p className="subtitle">เปิดอ่านไฟล์ก่อนอนุมัติ ชีทจะเผยแพร่เมื่อผ่านการตรวจสอบแล้วเท่านั้น</p></div><span className="count-label">{count.count || 0} ชีท</span></div>
    <nav className="review-filters" aria-label="กรองสถานะชีท">{filters.map(([value, label]) => <Link key={value} href={`?status=${value}`} className={`btn btn-sm ${status === value ? '' : 'btn-secondary'}`} aria-current={status === value ? 'page' : undefined}>{label}</Link>)}</nav>
    {!notes.length ? <EmptyState title="ไม่มีชีทในสถานะนี้">ชีทที่สมาชิกส่งตรวจจะอยู่ในรายการรออนุมัติ</EmptyState> : <div className="card table-card"><div className="table-wrap"><table><thead><tr><th>ชีทสรุป</th><th>เจ้าของ</th><th>รายวิชา</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>{notes.map(note => <tr key={note.note_id}>
      <td>{note.status !== 'removed' ? <Link href={`/notes/${note.note_id}`}>{note.title}</Link> : <strong>{note.title}</strong>}<div className="note-meta">{formatDate(note.created_at)}</div></td><td>{note.username}</td><td>{note.subject_name}</td><td><NoteStatusBadge status={note.status}/></td>
      <td>{note.status !== 'removed' ? <><div className="actions"><Link href={`/notes/${note.note_id}`} className="btn btn-sm btn-secondary">ตรวจดูชีท</Link><Link href={`/notes/${note.note_id}/edit`} className="btn btn-sm btn-secondary">แก้ไข</Link><ConfirmForm action={removeNoteAction} message="ยืนยันการนำชีทนี้ออกจากระบบ?"><input type="hidden" name="note_id" value={note.note_id}/><Submit className="btn btn-sm btn-danger">ลบ</Submit></ConfirmForm></div>{note.status === 'pending' && <NoteReviewActions noteId={note.note_id}/>}</> : '-'}</td>
    </tr>)}</tbody></table></div></div>}
    {pages > 1 && <nav className="pagination" aria-label="หน้ารายการชีท">{page > 1 && <Link className="btn btn-secondary btn-sm" href={`?status=${status}&page=${page - 1}`}>ก่อนหน้า</Link>}<span>หน้า {page} จาก {pages}</span>{page < pages && <Link className="btn btn-secondary btn-sm" href={`?status=${status}&page=${page + 1}`}>ถัดไป</Link>}</nav>}
  </>;
}
