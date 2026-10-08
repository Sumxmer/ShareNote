import Link from 'next/link';
import { Upload, FileText, BookOpen, Download, MessageCircle } from 'lucide-react';
import { requireUser } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, formatDate, type Note } from '@/lib/data';
import { Stats, EmptyState } from '@/components/ui';
import { ConfirmForm, Submit } from '@/components/action-form';
import { removeNoteAction } from '@/app/actions';
import { NoteStatusBadge } from '@/components/note-status';
export const metadata = { title: 'ชีทของฉัน' };

export default async function Dashboard() {
  const user = await requireUser(); const db = await supabaseServer();
  const [rows, count] = await Promise.all([
    db.from('note_catalog').select('*').eq('user_id',user.user_id).order('created_at',{ ascending: false }),
    db.rpc('my_stats'),
  ]);
  checkDb(rows.error,'my-notes'); checkDb(count.error,'my-stats');
  const notes = (rows.data ?? []) as Note[]; const stats = count.data as Record<string,number>;
  return <><div className="page-heading"><div><div className="eyebrow">My study shelf</div><h1 className="page-title">ชีทของฉัน</h1><p className="subtitle">ทุกสรุปที่คุณแบ่งปัน ช่วยให้ใครอีกคนเรียนรู้ได้ง่ายขึ้น</p></div><Link className="btn" href="/upload"><Upload className="icon"/>แบ่งปันชีทใหม่</Link></div><Stats items={[
    { value: stats.total,label: 'ชีททั้งหมด',icon: <FileText className="icon"/> },
    { value: stats.active,label: 'กำลังเผยแพร่',icon: <BookOpen className="icon"/> },
    { value: stats.pending ?? 0,label: 'รออนุมัติ',icon: <FileText className="icon"/> },
    { value: stats.downloads,label: 'ดาวน์โหลดรวม',icon: <Download className="icon"/> },
    { value: stats.comments,label: 'ความคิดเห็น',icon: <MessageCircle className="icon"/> },
  ]}/>{!notes.length ? <EmptyState title="เริ่มเก็บเรื่องราวการเรียนรู้ของคุณ" href="/upload" label="แบ่งปันชีทแรก">อัปโหลดชีทแรก แล้วจัดการทุกสรุปของคุณได้จากหน้านี้</EmptyState> : <div className="card table-card"><div className="table-title"><h3>ชีทที่ฉันแบ่งปัน</h3><span className="count-label">{notes.length} รายการ</span></div><div className="table-wrap"><table><thead><tr><th>ชีทสรุป</th><th>ดาวน์โหลด</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>{notes.map(note => <tr key={note.note_id}><td>{note.status !== 'removed' ? <Link href={`/notes/${note.note_id}`}>{note.title}</Link> : <strong>{note.title}</strong>}<div className="note-meta">{note.file_type.toUpperCase()} · {formatDate(note.created_at)}</div></td><td>{note.download_count}</td><td><NoteStatusBadge status={note.status}/></td><td>{note.status !== 'removed' ? <div className="actions"><Link className="btn btn-sm btn-secondary" href={`/notes/${note.note_id}/edit`}>แก้ไข</Link><ConfirmForm action={removeNoteAction} message="ยืนยันการนำชีทนี้ออกจากการเผยแพร่?"><input name="note_id" type="hidden" value={note.note_id}/><Submit className="btn btn-sm btn-danger">ลบ</Submit></ConfirmForm></div> : <span className="note-meta">หยุดเผยแพร่แล้ว</span>}</td></tr>)}</tbody></table></div></div>}</>;
}
