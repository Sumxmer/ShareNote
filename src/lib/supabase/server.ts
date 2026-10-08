import 'server-only';
import { createServerClient } from '@supabase/ssr';
import { createClient } from '@supabase/supabase-js';
import { cookies } from 'next/headers';
import { getPublicEnv, secureCookies } from '@/lib/env';

export async function supabaseServer() {
  const { url, key } = getPublicEnv();
  const store = await cookies();
  return createServerClient(url, key, {
    cookieOptions: { httpOnly: true, sameSite: 'lax', secure: secureCookies(), path: '/' },
    cookies: {
      getAll: () => store.getAll(),
      setAll(values) {
        try { values.forEach(({ name, value, options }) => store.set(name, value, { ...options, httpOnly: true, secure: secureCookies(), sameSite: 'lax' })); }
        catch { /* Proxy refreshes cookies during Server Component rendering. */ }
      },
    },
  });
}

export function supabaseService() {
  const { url } = getPublicEnv();
  const key = process.env.SUPABASE_SECRET_KEY || process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!key) throw new Error('ยังไม่ได้ตั้งค่าคีย์ฝั่งเซิร์ฟเวอร์ของ Supabase');
  return createClient(url, key, { auth: { persistSession: false, autoRefreshToken: false } });
}
