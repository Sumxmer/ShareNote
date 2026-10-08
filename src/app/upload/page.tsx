import { requireUser } from '@/lib/auth';
import { supabaseServer } from '@/lib/supabase/server';
import { checkDb, type Subject } from '@/lib/data';
import { UploadForm } from '@/components/upload-form';
import { getPublicEnv } from '@/lib/env';
import { UploadField } from '@/components/fields';
export const metadata = { title: 'แบ่งปันชีท' };

export default async function UploadPage() {
  await requireUser();
  const { url, key } = getPublicEnv();
  const subjects = await (await supabaseServer()).from('subjects').select('subject_id,subject_name').order('subject_name');
  checkDb(subjects.error,'upload-subjects');
  return <><div className="page-heading"><div><div className="eyebrow">Share what you know</div><h1 className="page-title">แบ่งปันชีทสรุป</h1><p className="subtitle">ส่งต่อความรู้ที่คุณตั้งใจทำ ให้ช่วยเพื่อนอีกหลายคน</p></div></div><div className="form-layout"><div className="card form-card"><h2>รายละเอียดชีทของคุณ</h2><UploadForm url={url} publishableKey={key}><label htmlFor="title">ชื่อชีทสรุป</label><input id="title" name="title" required maxLength={200} placeholder="เช่น สรุป SQL ก่อนสอบปลายภาค"/><label htmlFor="subject_name">รายวิชา</label><input id="subject_name" name="subject_name" required maxLength={150} list="subjects" placeholder="เลือกหรือพิมพ์ชื่อรายวิชา"/><datalist id="subjects">{((subjects.data || []) as Subject[]).map(s => <option key={s.subject_id} value={s.subject_name}/>)}</datalist><label htmlFor="description">คำอธิบาย</label><textarea id="description" name="description" maxLength={2000} rows={5} placeholder="ชีทนี้ครอบคลุมเรื่องอะไรบ้าง?"/><UploadField/></UploadForm></div><aside className="help-card"><h3>แบ่งปันอย่างไรให้อ่านง่าย</h3><div className="step-item"><span>1</span><p><strong>ตั้งชื่อให้ชัดเจน</strong>บอกหัวข้อหรือบทเรียนที่สรุป</p></div><div className="step-item"><span>2</span><p><strong>เลือกรายวิชา</strong>ช่วยให้เพื่อนค้นหาได้ตรงเรื่อง</p></div><div className="step-item"><span>3</span><p><strong>เลือกไฟล์ที่อ่านออก</strong>ใช้ภาพและตัวอักษรที่ชัดเจน</p></div><p>ไฟล์ไม่เกิน 10 MB ชีทจะรอผู้ดูแลอนุมัติก่อนเผยแพร่และเปิดให้สมาชิกดาวน์โหลด</p></aside></div></>;
}
