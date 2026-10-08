import { NextRequest } from 'next/server';
import { viewer } from '@/lib/auth';
import { findNote } from '@/lib/data';
import { positiveId } from '@/lib/validation';
import { ticketValid } from '@/lib/tokens';
import { supabaseServer, supabaseService } from '@/lib/supabase/server';

export async function GET(request: NextRequest, context: { params: Promise<{ id: string }> }) {
  const headers = { 'Cache-Control': 'private, no-store', 'X-Content-Type-Options': 'nosniff' };
  const user = await viewer(); if (!user) return new Response('กรุณาเข้าสู่ระบบ', { status: 401,headers });
  let id: number;
  try { id = positiveId((await context.params).id); } catch { return new Response('ไม่พบไฟล์',{ status: 404,headers }); }
  if (!ticketValid(request.nextUrl.searchParams.get('ticket') || '',user.user_id,id,process.env.APP_SESSION_SECRET!)) return new Response('ลิงก์หมดอายุ กรุณากดดาวน์โหลดจากหน้าชีทอีกครั้ง',{ status: 403,headers });
  const note = await findNote(id); if (!note) return new Response('ไม่พบไฟล์',{ status: 404,headers });
  const stored = await supabaseService().storage.from('notes').download(note.object_path);
  if (stored.error || !stored.data) { console.error('[NoteShare:download]',stored.error); return new Response('ไม่สามารถดาวน์โหลดได้ กรุณาลองใหม่',{ status: 503,headers }); }
  const logged = await (await supabaseServer()).rpc('record_download',{ p_note_id: id });
  // If the note was removed while loading the object, do not send it.
  if (logged.error) return new Response('ไม่สามารถดาวน์โหลดชีทนี้ได้',{ status: 403,headers });
  const name = note.original_file_name.replace(/[\r\n\x00-\x1f\x7f]/g,'').slice(0,255);
  const encoded = encodeURIComponent(name).replace(/['()*]/g,char => `%${char.charCodeAt(0).toString(16).toUpperCase()}`);
  return new Response(stored.data.stream(),{ headers: { ...headers,'Content-Type': note.mime_type,'Content-Length': String(stored.data.size),'Content-Disposition': `attachment; filename="note.${note.file_type}"; filename*=UTF-8''${encoded}` } });
}
