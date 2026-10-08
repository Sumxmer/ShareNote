'use client';

import { useState, useTransition } from 'react';
import { Download } from 'lucide-react';
import { requestDownloadAction } from '@/app/actions';

export function DownloadButton({ noteId }: { noteId: number }) {
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState('');
  return <><button className="btn" type="button" disabled={pending} onClick={() => {
    setError('');
    startTransition(async () => {
      try {
        const data = new FormData(); data.set('note_id', String(noteId));
        const result = await requestDownloadAction(data);
        // A document navigation starts the attachment download without leaving a pending form action.
        window.location.assign(result.url);
      } catch { setError('ดาวน์โหลดไม่สำเร็จ กรุณาลองใหม่หรือเข้าสู่ระบบอีกครั้ง'); }
    });
  }}><Download className="icon"/>{pending ? 'กำลังเตรียมไฟล์…' : 'ดาวน์โหลดชีท'}</button>{error && <p className="alert alert-error" role="alert">{error}</p>}</>;
}
