import { createServerClient } from '@supabase/ssr';
import { NextResponse, type NextRequest } from 'next/server';
import { randomBytes } from 'node:crypto';
import { isConfigured, getPublicEnv, secureCookies } from '@/lib/env';
import { ACTIVITY_COOKIE, activityToken, activityValid } from '@/lib/tokens';

export async function proxy(request: NextRequest) {
  const nonce = randomBytes(16).toString('base64');
  const dev = process.env.NODE_ENV !== 'production';
  const storageOrigin = isConfigured() ? ` ${new URL(getPublicEnv().url).origin}` : '';
  const csp = `default-src 'self'; script-src 'self' 'nonce-${nonce}'${dev ? " 'unsafe-eval'" : ''}; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'${storageOrigin}${dev ? ' ws: wss:' : ''}; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'`;
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set('x-nonce', nonce);
  requestHeaders.set('Content-Security-Policy', csp);
  let response = NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set('Content-Security-Policy', csp);
  response.headers.set('Cache-Control', 'private, no-store');
  if (!isConfigured()) return response;
  const { url, key } = getPublicEnv();
  const db = createServerClient(url, key, {
    cookieOptions: { httpOnly: true, sameSite: 'lax', secure: secureCookies(), path: '/' },
    cookies: {
      getAll: () => request.cookies.getAll(),
      setAll(values) {
        values.forEach(({ name, value }) => request.cookies.set(name, value));
        requestHeaders.set('cookie', request.headers.get('cookie') || '');
        const previousCookies = response.cookies.getAll();
        response = NextResponse.next({ request: { headers: requestHeaders } });
        response.headers.set('Content-Security-Policy', csp);
        response.headers.set('Cache-Control', 'private, no-store');
        previousCookies.forEach(cookie => response.cookies.set(cookie));
        values.forEach(({ name, value, options }) => response.cookies.set(name, value, { ...options, httpOnly: true, sameSite: 'lax', secure: secureCookies() }));
      },
    },
  });
  const { data } = await db.auth.getClaims();
  const uid = data?.claims.sub;
  if (uid) {
    const valid = activityValid(request.cookies.get(ACTIVITY_COOKIE)?.value, uid, process.env.APP_SESSION_SECRET!);
    const account = valid ? await db.from('profiles').select('status').eq('user_id',uid).maybeSingle() : null;
    const revoked = valid && (account?.error || account?.data?.status !== 'active');
    if (!valid || revoked) {
      await db.auth.signOut({ scope: 'local' });
      response.cookies.delete(ACTIVITY_COOKIE);
      if (!request.nextUrl.pathname.startsWith('/auth/') && !['/login', '/register'].includes(request.nextUrl.pathname)) {
        const target = NextResponse.redirect(new URL(`/login?reason=${revoked ? 'suspended' : 'timeout'}`, request.url));
        response.cookies.getAll().forEach(cookie => target.cookies.set(cookie));
        target.headers.set('Cache-Control', 'no-store');
        return target;
      }
    } else if (!request.headers.has('next-router-prefetch') && !request.headers.has('purpose')) {
      const value = activityToken(uid, process.env.APP_SESSION_SECRET!);
      request.cookies.set(ACTIVITY_COOKIE, value);
      requestHeaders.set('cookie',request.headers.get('cookie') || '');
      const previousCookies = response.cookies.getAll();
      response = NextResponse.next({ request: { headers: requestHeaders } });
      response.headers.set('Content-Security-Policy',csp);
      response.headers.set('Cache-Control','private, no-store');
      previousCookies.forEach(cookie => response.cookies.set(cookie));
      response.cookies.set(ACTIVITY_COOKIE, value, { httpOnly: true, sameSite: 'lax', secure: secureCookies(), path: '/', maxAge: 60*60*24*7 });
    }
  }
  return response;
}

export const config = { matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)'] };
