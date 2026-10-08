'use client';

import { createClient } from '@supabase/supabase-js';
import type { ReactNode } from 'react';
import { ActionForm } from '@/components/action-form';
import { createNoteAction, type ActionState } from '@/app/actions';
import { prepareUploadAction } from '@/app/upload-actions';
import { uploadName, STAGING_BUCKET } from '@/lib/upload-policy';

export function UploadForm({ children, url, publishableKey }: { children: ReactNode; url: string; publishableKey: string }) {
  async function publish(previous: ActionState, data: FormData): Promise<ActionState> {
    const file = data.get('file');
    if (!(file instanceof File) || data.getAll('file').length !== 1) return { error: 'กรุณาเลือกไฟล์หนึ่งไฟล์' };
    try { uploadName(file.name, file.size); } catch (error) { return { error: error instanceof Error ? error.message : 'ไฟล์ไม่ถูกต้อง' }; }
    const prepared = await prepareUploadAction(file.name, file.size);
    if ('error' in prepared) return prepared;
    const storage = createClient(url, publishableKey, { auth: { persistSession: false, autoRefreshToken: false, detectSessionInUrl: false } }).storage;
    try {
      // Send bytes so multipart File MIME cannot override the staging content type.
      const uploaded = await storage.from(STAGING_BUCKET).uploadToSignedUrl(prepared.path, prepared.token, await file.arrayBuffer(), { contentType: 'application/octet-stream' });
      if (uploaded.error) return { error: 'อัปโหลดไม่สำเร็จ กรุณาตรวจการเชื่อมต่อแล้วลองใหม่' };
    } catch { return { error: 'อัปโหลดไม่สำเร็จ กรุณาตรวจการเชื่อมต่อแล้วลองใหม่' }; }
    // Only the small signed ticket and text fields traverse the Vercel function.
    data.delete('file'); data.set('upload_ticket', prepared.ticket);
    return createNoteAction(previous, data);
  }
  return <ActionForm action={publish} label="เผยแพร่ชีท">{children}</ActionForm>;
}
