import Link from 'next/link';
import { ArrowRight, Upload, Search } from 'lucide-react';
import { isConfigured } from '@/lib/env';
import { searchNotes } from '@/lib/data';
import { PaperArt, NoteCard, EmptyState } from '@/components/ui';
import { SetupGuide } from '@/components/setup-guide';

export default async function Home({ searchParams }: { searchParams: Promise<{ q?: string; subject?: string; page?: string }> }) {
  if (!isConfigured()) return <SetupGuide/>;
  const params = await searchParams;
  const q = typeof params.q === 'string' ? params.q.trim().slice(0,100) : '';
  const subject = typeof params.subject === 'string' && /^[1-9][0-9]{0,9}$/.test(params.subject) && Number(params.subject)<=2147483647 ? Number(params.subject) : null;
  const requestedPage = typeof params.page === 'string' && /^[1-9][0-9]{0,5}$/.test(params.page) ? Math.min(Number(params.page),100000) : 1;
  const result = await searchNotes(q,subject,requestedPage);
  const href = (page: number) => `/?${new URLSearchParams({ q,subject: subject ? String(subject) : '',page: String(page) })}#library`;
  return <><section className="hero"><div><div className="eyebrow">Your shared study space</div><h1>ชีทเล็ก ๆ ของคุณ<br/><span>อาจช่วยใครได้อีกมาก</span></h1><p>พื้นที่รวมชีทสรุปจากเพื่อน ๆ ค้นหาวิชาที่กำลังเรียน<br/>ทบทวนก่อนสอบ และส่งต่อความรู้ในแบบของคุณ</p><div className="hero-actions"><Link href="/upload" className="btn"><Upload className="icon"/>เริ่มแบ่งปันชีท</Link><a href="#library" className="text-link">ค้นหาชีทที่ต้องการ<ArrowRight className="icon"/></a></div></div><PaperArt/></section>
    <form method="GET" action="/" className="search-panel" id="library"><div className="search-field"><label htmlFor="q" className="sr-only">ค้นหาชีทหรือรายวิชา</label><Search className="icon"/><input type="search" id="q" name="q" defaultValue={q} maxLength={100} placeholder="วันนี้อยากเรียนรู้อะไร? ค้นหาชีทหรือรายวิชา…"/></div><label className="sr-only" htmlFor="subject">รายวิชา</label><select id="subject" name="subject" defaultValue={subject || ''}><option value="">ทุกรายวิชา</option>{result.subjects.map(s => <option key={s.subject_id} value={s.subject_id}>{s.subject_name}</option>)}</select><button className="btn" type="submit">ค้นหา</button></form>
    <div className="section-heading"><div><h2>{q || subject ? 'ผลการค้นหา' : 'ชีทสรุปล่าสุด'}</h2><p>{q ? `ผลลัพธ์สำหรับ “${q}”` : 'ความรู้ที่เพื่อน ๆ พร้อมแบ่งปันให้คุณ'}</p></div><span className="count-label">{result.total.toLocaleString('th-TH')} ชีท</span></div>
    {result.notes.length ? <div className="note-grid">{result.notes.map(note => <NoteCard key={note.note_id} note={note}/>)}</div> : <EmptyState title={q || subject ? 'ยังไม่พบชีทที่ค้นหา' : 'พื้นที่นี้รอชีทแรกจากคุณ'} href={q || subject ? '/' : '/upload'} label={q || subject ? 'ดูชีททั้งหมด' : 'แบ่งปันชีทแรก'}>{q || subject ? 'ลองเปลี่ยนคำค้นหาหรือเลือกดูทุกรายวิชา' : 'แบ่งปันสรุปที่คุณตั้งใจทำ ให้เป็นประโยชน์กับเพื่อน ๆ'}</EmptyState>}
    {result.pages > 1 && <nav className="pagination" aria-label="หน้าผลการค้นหา">{result.page > 1 && <Link className="btn btn-secondary btn-sm" href={href(result.page-1)}>ก่อนหน้า</Link>}<span>หน้า {result.page} จาก {result.pages}</span>{result.page < result.pages && <Link className="btn btn-secondary btn-sm" href={href(result.page+1)}>ถัดไป</Link>}</nav>}
  </>;
}
