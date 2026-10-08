import 'server-only';
import { cache } from 'react';
import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { isConfigured, secureCookies } from '@/lib/env';
import { ACTIVITY_COOKIE, activityToken, activityValid } from '@/lib/tokens';
import { supabaseServer } from '@/lib/supabase/server';

export type Profile = { user_id: string; username: string; full_name: string; role: 'user' | 'admin'; status: 'active' | 'suspended' };

export const viewer = cache(async (): Promise<Profile | null> => {
  if (!isConfigured()) return null;
  const db = await supabaseServer();
  const { data: { user }, error } = await db.auth.getUser();
  if (error || !user) return null;
  const store = await cookies();
  if (!activityValid(store.get(ACTIVITY_COOKIE)?.value, user.id, process.env.APP_SESSION_SECRET!)) return null;
  const result = await db.from('profiles').select('user_id,username,full_name,role,status').eq('user_id', user.id).single();
  if (result.error) throw new Error('ไม่สามารถอ่านบัญชีผู้ใช้ กรุณาตรวจสอบการตั้งค่าฐานข้อมูล');
  if (result.data.status !== 'active') return null;
  return result.data as Profile;
});

export async function requireUser() {
  if (!isConfigured()) redirect('/setup');
  const user = await viewer();
  if (!user) redirect('/login');
  return user;
}
export async function requireAdmin() {
  const user = await requireUser();
  if (user.role !== 'admin') redirect('/forbidden');
  return user;
}
export async function startActivity(userId: string) {
  const store = await cookies();
  store.set(ACTIVITY_COOKIE, activityToken(userId, process.env.APP_SESSION_SECRET!), {
    httpOnly: true, sameSite: 'lax', secure: secureCookies(), path: '/', maxAge: 60 * 60 * 24 * 7,
  });
}
