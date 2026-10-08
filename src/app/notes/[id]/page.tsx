import { DownloadButton } from '@/components/download-button';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { FileText } from 'lucide-react';
import { viewer } from '@/lib/auth';
import { isConfigured } from '@/lib/env';
import { findNote, noteComments, formatDate, formatBytes } from '@/lib/data';
import { positiveId } from '@/lib/validation';
import { ActionForm, ConfirmForm, Submit } from '@/components/action-form';
import { addCommentAction, removeCommentAction } from '@/app/actions';

export default async function NotePage({ params }: { params: Promise<{ id: string }> }) {
  if (!isConfigured()) notFound();
  let id: number;
  try { id = positiveId((await params).id); } catch { notFound(); }
  const note = await findNote(id); if (!note) notFound();
  const [user,comments] = await Promise.all([viewer(),noteComments(id)]);
  const canManage = user && (user.user_id === note.user_id || user.role === 'admin');
  return <><div className="breadcrumb"><Link href="/">สำรวจชีทสรุป</Link><span>/</span><span>{note.subject_name}</span></div><div className="detail-layout"><div><article className="card"><span className="badge">{note.subject_name}</span><h1 className="detail-title">{note.title}</h1><div className="detail-author"><span className="avatar" aria-hidden="true">{note.full_name.slice(0,1)}</span><span>แบ่งปันโดย <strong>{note.full_name}</strong><br/><span className="note-meta">{formatDate(note.created_at)}</span></span></div><div className="detail-body">{note.description || 'ผู้แบ่งปันยังไม่ได้เพิ่มคำอธิบายสำหรับชีทนี้'}</div>{canManage && <Link className="btn btn-secondary btn-sm" href={`/notes/${id}/edit`}><FileText className="icon"/>แก้ไขรายละเอียดชีท</Link>}</article><section className="card"><h2>ความคิดเห็น ({comments.length})</h2>{user ? <ActionForm action={addCommentAction.bind(null,id)} label="ส่งความคิดเห็น" className="comment-form"><label className="sr-only" htmlFor="content">ความคิดเห็น</label><textarea id="content" name="content" required maxLength={1000} rows={3} placeholder="แลกเปลี่ยนข้อคิดเห็นหรือขอบคุณผู้แบ่งปัน"/></ActionForm> : <p className="subtitle"><Link href="/login">เข้าสู่ระบบ</Link>เพื่อแสดงความคิดเห็น</p>}{comments.map(comment => <article className="comment-box" key={comment.comment_id}><div className="comment-top"><span className="avatar" aria-hidden="true">{comment.full_name.slice(0,1)}</span><strong>{comment.full_name}</strong><time dateTime={comment.created_at}>{formatDate(comment.created_at)}</time>{user && (user.user_id===comment.user_id || user.role==='admin') && <ConfirmForm action={removeCommentAction} message="ยืนยันการลบความคิดเห็นนี้?"><input name="comment_id" type="hidden" value={comment.comment_id}/><input name="note_id" type="hidden" value={id}/><Submit className="btn btn-sm btn-ghost">ลบ</Submit></ConfirmForm>}</div><p>{comment.content}</p></article>)}{!comments.length && <p className="note-meta">ยังไม่มีความคิดเห็น เริ่มบทสนทนาเป็นคนแรกได้เลย</p>}</section></div><aside className="download-card"><span className="file-icon">{note.file_type.toUpperCase()}</span><h3>ไฟล์ชีทสรุป</h3><p className="file-name">{note.original_file_name}</p><div className="info-row"><span>ประเภทไฟล์</span><strong>{note.file_type.toUpperCase()}</strong></div><div className="info-row"><span>ขนาดไฟล์</span><strong>{formatBytes(note.file_size)}</strong></div><div className="info-row"><span>ดาวน์โหลดแล้ว</span><strong>{note.download_count} ครั้ง</strong></div>{user ? <DownloadButton noteId={id}/> : <Link className="btn" href="/login">เข้าสู่ระบบเพื่อดาวน์โหลด</Link>}<p className="mini-note">ไฟล์เก็บในพื้นที่ส่วนตัว เปิดให้ดาวน์โหลดผ่านเว็บเท่านั้น</p></aside></div></>;
}
