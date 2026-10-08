import { NextRequest, NextResponse } from 'next/server';
import { supabaseServer } from '@/lib/supabase/server';
import { startActivity } from '@/lib/auth';
import { isConfigured } from '@/lib/env';

export async function GET(request: NextRequest) {
  if (!isConfigured()) return NextResponse.redirect(new URL('/setup',request.url));
  const code = request.nextUrl.searchParams.get('code');
  if (code) {
    const result = await (await supabaseServer()).auth.exchangeCodeForSession(code);
    if (!result.error && result.data.user) { await startActivity(result.data.user.id); return NextResponse.redirect(new URL('/dashboard',request.url)); }
  }
  return NextResponse.redirect(new URL('/login?reason=confirmation',request.url));
}
