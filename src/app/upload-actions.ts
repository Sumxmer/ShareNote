'use server';

import { randomUUID } from 'node:crypto';
import { requireUser } from '@/lib/auth';
import { supabaseService } from '@/lib/supabase/server';
import { uploadName, STAGING_BUCKET } from '@/lib/upload-policy';
import { uploadTicket } from '@/lib/upload-ticket';
import { approvalReady } from '@/lib/data';

export async function prepareUploadAction(name: string, size: number): Promise<
  { error: string } | { path: string; token: string; ticket: string }
> {
  const user = await requireUser();
  try {
    if (!await approvalReady()) return { error: 'ระบบตรวจสอบชีทกำลังเตรียมพร้อม กรุณาลองใหม่ภายหลัง' };
    const clean = uploadName(name, size);
    const path = `${user.user_id}/${randomUUID()}`;
    // Client receives a scoped write capability for staging only, never for published files.
    const signed = await supabaseService().storage.from(STAGING_BUCKET).createSignedUploadUrl(path, { upsert: false });
    if (signed.error || !signed.data) throw signed.error;
    return { path, token: signed.data.token, ticket: uploadTicket({ userId: user.user_id, path, name: clean, size }, process.env.APP_SESSION_SECRET!) };
  } catch (error) {
    console.error('[NoteShare:prepare-upload]', error);
    return { error: 'เตรียมอัปโหลดไม่สำเร็จ กรุณาตรวจไฟล์แล้วลองใหม่' };
  }
}
