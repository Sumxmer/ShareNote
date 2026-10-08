import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { requireUser } from '@/lib/auth';
import { findNote } from '@/lib/data';
import { positiveId } from '@/lib/validation';
import { ActionForm } from '@/components/action-form';
import { editNoteAction } from '@/app/actions';
export const metadata = { title: 'แก้ไขชีท' };

export default async function EditNote({ params }: { params: Promise<{ id: string }> }) {
  const user = await requireUser(); let id: number;
  try { id = positiveId((await params).id); } catch { notFound(); }
  const note = await findNote(id); if (!note) notFound();
  if (note.user_id !== user.user_id && user.role !== 'admin') redirect('/forbidden');
  return <><div className="page-heading"><div><div className="eyebrow">Refine your notes</div><h1 className="page-title">แก้ไขรายละเอียดชีท</h1><p className="subtitle">เมื่อบันทึกการแก้ไข ชีทจะกลับไปรอผู้ดูแลอนุมัติก่อนเผยแพร่</p></div></div><div className="form-layout"><div className="card form-card"><ActionForm action={editNoteAction.bind(null,id)} label="บันทึกและส่งตรวจใหม่"><label htmlFor="title">ชื่อชีท</label><input id="title" name="title" required maxLength={200} defaultValue={note.title}/><label htmlFor="subject_name">รายวิชา</label><input id="subject_name" name="subject_name" required maxLength={150} defaultValue={note.subject_name}/><label htmlFor="description">คำอธิบาย</label><textarea id="description" name="description" maxLength={2000} rows={6} defaultValue={note.description}/></ActionForm><Link className="text-link cancel-link" href={`/notes/${id}`}>กลับไปดูชีท</Link></div><aside className="help-card"><h3>ไฟล์ที่แนบอยู่</h3><p className="file-name">{note.original_file_name}</p><p>หน้านี้แก้ไขเฉพาะรายละเอียด หากต้องการเปลี่ยนไฟล์ ให้หยุดเผยแพร่ชีทเดิมและอัปโหลดชีทใหม่</p></aside></div></>;
}
