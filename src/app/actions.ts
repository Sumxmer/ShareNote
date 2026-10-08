'use server';

import { redirect } from 'next/navigation';
import { cookies, headers } from 'next/headers';
import { isIP } from 'node:net';
import { revalidatePath } from 'next/cache';
import { requireUser, requireAdmin, startActivity } from '@/lib/auth';
import { isConfigured, siteUrl } from '@/lib/env';
import { ACTIVITY_COOKIE, downloadTicket } from '@/lib/tokens';
import { supabaseServer, supabaseService } from '@/lib/supabase/server';
import { formText, registerSchema, loginSchema, noteSchema, commentSchema, positiveId } from '@/lib/validation';
import { inspectUpload } from '@/lib/uploads';
import { STAGING_BUCKET } from '@/lib/upload-policy';
import { readUploadTicket } from '@/lib/upload-ticket';
import { findNote, approvalReady } from '@/lib/data';
import { canDownloadNote } from '@/lib/note-status';

export type ActionState = { error?: string; message?: string };
const generic = 'ไม่สามารถทำรายการได้ กรุณาลองใหม่ภายหลัง';

async function trustedIp() {
  if (process.env.TRUST_PROXY !== 'true') return null;
  const value = (await headers()).get('x-forwarded-for')?.split(',')[0]?.trim();
  return value && isIP(value) ? value : null;
}
async function authEvent(action: string, email: string, userId: string | null = null) {
  const result = await supabaseService().rpc('record_auth_event', { p_action: action, p_email: email, p_user_id: userId, p_ip: await trustedIp() });
  if (result.error) throw result.error;
}
function actionError(error: unknown): ActionState {
  console.error('[NoteShare:action]', error);
  return { error: generic };
}

export async function loginAction(_previous: ActionState, data: FormData): Promise<ActionState> {
  if (!isConfigured()) redirect('/setup');
  let uid: string;
  try {
    const fields = loginSchema.safeParse({ username: formText(data, 'username'), password: formText(data, 'password') });
    if (!fields.success) return { error: 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ถูกต้อง' };
    const limited = await supabaseService().rpc('login_is_locked', { p_email: fields.data.username, p_ip: await trustedIp() });
    if (limited.error) throw limited.error;
    if (limited.data) { await authEvent('ACCOUNT_LOCKOUT', fields.data.username); return { error: 'เข้าสู่ระบบไม่สำเร็จหลายครั้ง กรุณาลองใหม่ภายหลัง (ช่วงตรวจสอบ 15 นาที)' }; }
    // Resolve the private Auth email on the server, after throttling. Never return it to the browser.
    const account = await supabaseService().from('profiles').select('email').eq('username', fields.data.username).maybeSingle();
    if (account.error) throw account.error;
    if (!account.data) {
      await authEvent('LOGIN_FAILED', fields.data.username);
      return { error: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชียังไม่พร้อมใช้งาน' };
    }
    const db = await supabaseServer();
    const result = await db.auth.signInWithPassword({ email: account.data.email, password: fields.data.password });
    if (result.error || !result.data.user) {
      await authEvent('LOGIN_FAILED', fields.data.username);
      if (result.error?.code === 'email_not_confirmed') {
        return { error: 'กรุณายืนยันอีเมลก่อนเข้าสู่ระบบ เปิดอีเมลยืนยันที่ได้รับหลังสมัคร แล้วกดลิงก์ยืนยัน หากไม่พบให้ตรวจโฟลเดอร์สแปม' };
      }
      return { error: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชียังไม่พร้อมใช้งาน' };
    }
    uid = result.data.user.id;
    const profile = await db.from('profiles').select('status').eq('user_id', uid).single();
    if (profile.error) { await db.auth.signOut({ scope: 'local' }); throw profile.error; }
    if (profile.data.status !== 'active') {
      await db.auth.signOut({ scope: 'local' }); await authEvent('LOGIN_FAILED', fields.data.username);
      return { error: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชียังไม่พร้อมใช้งาน' };
    }
    await authEvent('LOGIN_SUCCESS', fields.data.username, uid);
    await startActivity(uid);
  } catch (error) { return actionError(error); }
  redirect('/dashboard');
}

export async function registerAction(_previous: ActionState, data: FormData): Promise<ActionState> {
  if (!isConfigured()) redirect('/setup');
  try {
    const fields = registerSchema.safeParse(Object.fromEntries(['username','email','full_name','password','confirm_password'].map(key => [key, formText(data,key)])));
    if (!fields.success) return { error: 'ตรวจสอบชื่อผู้ใช้ อีเมล และรหัสผ่าน 8-72 ไบต์ที่มีตัวอักษรอังกฤษและตัวเลข พร้อมยืนยันรหัสผ่านให้ตรงกัน' };
    const { username, email, full_name, password } = fields.data;
    const result = await (await supabaseServer()).auth.signUp({ email,password,options: { data: { username,full_name }, emailRedirectTo: `${siteUrl()}/auth/callback` } });
    if (result.error) return { error: 'สมัครไม่สำเร็จ กรุณาตรวจสอบข้อมูล หรือชื่อผู้ใช้/อีเมลอาจมีผู้ใช้งานแล้ว' };
    if (result.data.session && result.data.user) { await authEvent('LOGIN_SUCCESS',username,result.data.user.id); await startActivity(result.data.user.id); }
    else return { message: 'ส่งคำขอสมัครแล้ว กรุณาตรวจอีเมลเพื่อยืนยันบัญชีก่อนเข้าสู่ระบบ หากไม่พบอีเมลให้ตรวจโฟลเดอร์สแปม' };
  } catch (error) { return actionError(error); }
  redirect('/dashboard');
}

export async function logoutAction() {
  if (isConfigured()) {
    const db = await supabaseServer();
    const { data: { user } } = await db.auth.getUser();
    if (user) {
      try { await authEvent('LOGOUT', user.email || '', user.id); } catch (error) { console.error('[NoteShare:logout-log]', error); }
    }
    await db.auth.signOut({ scope: 'local' });
  }
  (await cookies()).delete(ACTIVITY_COOKIE);
  redirect('/login');
}

export async function createNoteAction(_previous: ActionState, data: FormData): Promise<ActionState> {
  const user = await requireUser();
  let id: number;
  let path: string | null = null;
  let stagingPath: string | null = null;
  try {
    const claim = readUploadTicket(formText(data, 'upload_ticket'), user.user_id, process.env.APP_SESSION_SECRET!);
    if (!claim) return { error: 'คำขออัปโหลดหมดอายุหรือไม่ถูกต้อง กรุณาเลือกไฟล์แล้วลองใหม่' };
    stagingPath = claim.path;
    if (!await approvalReady()) return { error: 'ระบบตรวจสอบชีทกำลังเตรียมพร้อม กรุณาลองใหม่ภายหลัง' };
    const parsed = noteSchema.safeParse({ title: formText(data,'title'),description: formText(data,'description'),subject_name: formText(data,'subject_name') });
    if (!parsed.success) return { error: 'กรุณาตรวจชื่อชีท (200 ตัวอักษร) คำอธิบาย (2,000) และรายวิชา (150)' };
    const staged = await supabaseService().storage.from(STAGING_BUCKET).download(claim.path);
    if (staged.error || !staged.data) throw staged.error;
    if (staged.data.size !== claim.size) return { error: 'ขนาดไฟล์ไม่ตรงกับคำขอ กรุณาอัปโหลดใหม่' };
    const file = new File([staged.data], claim.name);
    let inspected;
    try { inspected = await inspectUpload(file); } catch (error) { return { error: error instanceof Error ? error.message : 'ไฟล์ไม่ถูกต้อง' }; }
    path = `${user.user_id}/${inspected.randomName}`;
    const stored = await supabaseService().storage.from('notes').upload(path, inspected.buffer, { contentType: inspected.mime, upsert: false });
    if (stored.error) throw stored.error;
    const result = await (await supabaseServer()).rpc('create_note', {
      p_title: parsed.data.title, p_description: parsed.data.description, p_subject: parsed.data.subject_name,
      p_object_path: path, p_original_name: inspected.name, p_size: inspected.buffer.length, p_type: inspected.ext, p_mime: inspected.mime,
    });
    if (result.error) throw result.error;
    id = Number(result.data);
  } catch (error) {
    if (path) {
      const cleanup = await supabaseService().storage.from('notes').remove([path]);
      if (cleanup.error) console.error('[NoteShare:upload-cleanup]', cleanup.error);
    }
    return actionError(error);
  } finally {
    if (stagingPath) {
      const cleanup = await supabaseService().storage.from(STAGING_BUCKET).remove([stagingPath]);
      if (cleanup.error) console.error('[NoteShare:staging-cleanup]', cleanup.error);
    }
  }
  revalidatePath('/'); revalidatePath('/dashboard');
  redirect(`/notes/${id}`);
}

export async function editNoteAction(id: number, _previous: ActionState, data: FormData): Promise<ActionState> {
  await requireUser();
  try {
    if (!await approvalReady()) return { error: 'ระบบตรวจสอบชีทกำลังเตรียมพร้อม กรุณาลองใหม่ภายหลัง' };
    const parsed = noteSchema.safeParse({ title: formText(data,'title'),description: formText(data,'description'),subject_name: formText(data,'subject_name') });
    if (!parsed.success) return { error: 'กรุณาตรวจสอบข้อมูลชีทและความยาวข้อความ' };
    const result = await (await supabaseServer()).rpc('update_note', { p_note_id: positiveId(id),p_title: parsed.data.title,p_description: parsed.data.description,p_subject: parsed.data.subject_name });
    if (result.error) return { error: 'ไม่สามารถแก้ไขชีทนี้ได้ กรุณาตรวจสอบสิทธิ์และสถานะชีท' };
  } catch (error) { return actionError(error); }
  revalidatePath('/'); revalidatePath('/dashboard'); revalidatePath(`/notes/${id}`);
  redirect(`/notes/${id}`);
}

export async function removeNoteAction(data: FormData) {
  await requireUser();
  const id = positiveId(formText(data,'note_id'));
  const result = await (await supabaseServer()).rpc('remove_note', { p_note_id: id });
  if (result.error) throw new Error('ไม่สามารถนำชีทออกได้ กรุณาตรวจสอบสิทธิ์และสถานะชีท');
  revalidatePath('/'); revalidatePath('/dashboard'); revalidatePath('/admin/notes');
}
export async function addCommentAction(id: number, _previous: ActionState, data: FormData): Promise<ActionState> {
  await requireUser();
  try {
    const content = commentSchema.safeParse(formText(data,'content'));
    if (!content.success) return { error: 'กรุณากรอกความคิดเห็นไม่เกิน 1,000 ตัวอักษร' };
    const result = await (await supabaseServer()).rpc('add_comment', { p_note_id: positiveId(id),p_content: content.data });
    if (result.error) return { error: 'แสดงความคิดเห็นไม่สำเร็จ ชีทอาจหยุดเผยแพร่แล้ว' };
    revalidatePath(`/notes/${id}`);
    return { message: 'เพิ่มความคิดเห็นแล้ว' };
  } catch (error) { return actionError(error); }
}
export async function removeCommentAction(data: FormData) {
  await requireUser();
  const result = await (await supabaseServer()).rpc('remove_comment', { p_comment_id: positiveId(formText(data,'comment_id')) });
  if (result.error) throw new Error('ไม่สามารถลบความคิดเห็นนี้ได้');
  revalidatePath(`/notes/${positiveId(formText(data,'note_id'))}`);
}
export async function setUserStatusAction(data: FormData) {
  await requireAdmin();
  const result = await (await supabaseServer()).rpc('set_user_status', { p_user_id: formText(data,'user_id'),p_status: formText(data,'status') });
  if (result.error) throw new Error('ไม่สามารถเปลี่ยนสถานะบัญชีนี้ได้');
  revalidatePath('/admin/users');
}
export async function requestDownloadAction(data: FormData) {
  const user = await requireUser();
  const id = positiveId(formText(data,'note_id'));
  const note = await findNote(id);
  if (!note || !canDownloadNote(note.status, user.role)) redirect('/not-found');
  const ticket = downloadTicket(user.user_id,id,process.env.APP_SESSION_SECRET!);
  return { url: `/download/${id}?ticket=${encodeURIComponent(ticket)}` };
}

export async function reviewNoteAction(id: number, decision: 'active' | 'rejected', _previous: ActionState): Promise<ActionState> {
  await requireAdmin();
  try {
    if (!['active', 'rejected'].includes(decision)) return { error: 'ผลการตรวจสอบไม่ถูกต้อง' };
    const result = await (await supabaseServer()).rpc('review_note', { p_note_id: positiveId(id), p_decision: decision });
    if (result.error) return { error: 'ทำรายการไม่สำเร็จ ชีทนี้อาจได้รับการตรวจสอบแล้ว กรุณารีเฟรชหน้า' };
  } catch (error) { return actionError(error); }
  revalidatePath('/'); revalidatePath('/dashboard'); revalidatePath('/admin'); revalidatePath('/admin/notes'); revalidatePath(`/notes/${id}`);
  return { message: decision === 'active' ? 'อนุมัติและเผยแพร่ชีทแล้ว' : 'ไม่อนุมัติชีทนี้ เจ้าของสามารถแก้ไขเพื่อส่งตรวจใหม่ได้' };
}
