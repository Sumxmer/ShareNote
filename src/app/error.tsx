'use client';
import Link from 'next/link';
export default function ErrorPage({ reset }: { reset: () => void }) {
  return <div className="empty-state"><h1>ยังไม่สามารถทำรายการได้</h1><p>กรุณาลองอีกครั้ง หากเพิ่งตั้งค่า Supabase ให้ตรวจคีย์และรันสคริปต์สร้างตารางตามคู่มือ</p><button className="btn" onClick={reset}>ลองใหม่</button> <Link className="btn btn-secondary" href="/setup">ดูขั้นตอนตั้งค่า</Link></div>;
}
