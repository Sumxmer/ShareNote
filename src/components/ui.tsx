import Link from 'next/link';
import { BookOpen, FileText, ArrowRight, UserRound, Download, Search } from 'lucide-react';
import type { Note } from '@/lib/data';
import type { ReactNode } from 'react';

export function PaperArt() {
  return <div className="hero-art" aria-hidden="true"><div className="paper-stack"><div className="paper-icon"><BookOpen className="icon"/></div><strong>เรียนรู้<br/>ไปด้วยกัน</strong><div className="paper-line"/><div className="paper-line"/><div className="paper-line short"/><div className="paper-label">STUDY · SHARE · GROW</div></div><div className="float-tag first"><FileText className="icon"/>ชีทดี ๆ ที่อยากส่งต่อ</div><div className="float-tag second"><BookOpen className="icon"/>อ่าน · สรุป · แบ่งปัน</div></div>;
}
export function NoteCard({ note }: { note: Note }) {
  const color = ['jpg','jpeg','png'].includes(note.file_type) ? 'image' : ['doc','docx'].includes(note.file_type) ? 'word' : ['ppt','pptx'].includes(note.file_type) ? 'slides' : '';
  return <article className="note-card"><div className="note-card-top"><span className={`file-icon ${color}`}>{note.file_type.toUpperCase()}</span><span className="badge" title={note.subject_name}>{note.subject_name}</span></div><h3><Link href={`/notes/${note.note_id}`}>{note.title}</Link></h3><p className="note-summary">{note.description || 'เปิดดูรายละเอียดและดาวน์โหลดชีทนี้'}</p><div className="note-card-footer"><span><UserRound className="icon"/>{note.username}</span><span><Download className="icon"/>{note.download_count}</span><Link href={`/notes/${note.note_id}`} aria-label={`ดูชีท ${note.title}`}><ArrowRight className="icon"/></Link></div></article>;
}
export function EmptyState({ title, children, href, label }: { title: string; children: ReactNode; href?: string; label?: string }) {
  return <div className="empty-state"><div className="empty-icon"><Search className="icon"/></div><h3>{title}</h3><p>{children}</p>{href && <Link className="btn btn-secondary" href={href}>{label}</Link>}</div>;
}
export function Stats({ items }: { items: { value: number; label: string; icon: ReactNode }[] }) {
  return <div className="stat-grid">{items.map(item => <div className="stat-box" key={item.label}><span className="stat-icon">{item.icon}</span><div className="num">{item.value.toLocaleString('th-TH')}</div><div className="label">{item.label}</div></div>)}</div>;
}
