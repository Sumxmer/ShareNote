'use client';
import Link from 'next/link';
export default function ErrorPage({ reset }: { reset: () => void }) {
  return <div className="empty-state"><h1>ยังไม่สามารถทำรายการได้</h1><p>กรุณาลองอีกครั้ง หากยังใช้งานไม่ได้ ให้ติดต่อผู้ดูแลเว็บไซต์</p><button className="btn" onClick={reset}>ลองใหม่</button> <Link className="btn btn-secondary" href="/">กลับหน้าหลัก</Link></div>;
}
