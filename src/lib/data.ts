import 'server-only';
import { supabaseServer } from '@/lib/supabase/server';

export type Note = {
  note_id: number; user_id: string; subject_id: number; title: string; description: string;
  object_path: string; original_file_name: string; file_size: number; file_type: string; mime_type: string;
  download_count: number; status: 'active' | 'removed'; created_at: string; updated_at: string;
  username: string; full_name: string; subject_name: string;
};
export type Subject = { subject_id: number; subject_name: string };
export type Comment = { comment_id: number; user_id: string; content: string; created_at: string; username: string; full_name: string };
export type SecurityLog = { log_id: number; user_id: string | null; username_attempt: string | null; action: string; detail: string; ip_address: string | null; created_at: string };

export function checkDb(error: { message: string; code?: string } | null, context: string) {
  if (error) { console.error(`[NoteShare:${context}]`, error); throw new Error('ระบบไม่สามารถอ่านข้อมูลได้ กรุณาลองใหม่หรือตรวจสอบการตั้งค่า Supabase'); }
}
export async function searchNotes(q: string, subject: number | null, page: number) {
  const db = await supabaseServer();
  const [count, subjects] = await Promise.all([
    db.rpc('search_notes_count', { p_query: q, p_subject: subject }),
    db.from('subjects').select('subject_id,subject_name').order('subject_name'),
  ]);
  checkDb(count.error, 'search-count'); checkDb(subjects.error, 'subjects');
  const total = Number(count.data || 0); const pages = Math.max(1, Math.ceil(total/12));
  const current = Math.min(page, pages);
  const notes = await db.rpc('search_notes', { p_query: q, p_subject: subject, p_page: current });
  checkDb(notes.error, 'search');
  return { notes: (notes.data ?? []) as Note[], subjects: (subjects.data ?? []) as Subject[], total, pages, page: current };
}
export async function findNote(id: number) {
  const db = await supabaseServer();
  const result = await db.from('note_catalog').select('*').eq('note_id', id).eq('status', 'active').maybeSingle();
  checkDb(result.error, 'note');
  return result.data as Note | null;
}
export async function noteComments(id: number) {
  const db = await supabaseServer();
  const result = await db.from('comments').select('comment_id,user_id,content,created_at,profiles(username,full_name)').eq('note_id', id).order('created_at');
  checkDb(result.error, 'comments');
  return (result.data ?? []).map(row => {
    const profile = row.profiles as unknown as { username: string; full_name: string };
    return { comment_id: row.comment_id, user_id: row.user_id, content: row.content, created_at: row.created_at, username: profile?.username || 'สมาชิก', full_name: profile?.full_name || 'สมาชิก' } as Comment;
  });
}
export function formatDate(value: string) {
  return new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeZone: 'Asia/Bangkok' }).format(new Date(value));
}
export function formatBytes(bytes: number) { return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${(bytes/1024).toFixed(1)} KB`; }
export function formatLogTime(value: string) {
  return new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium',timeStyle: 'short',timeZone: 'Asia/Bangkok' }).format(new Date(value));
}
