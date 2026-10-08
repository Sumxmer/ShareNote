import { NextRequest, NextResponse } from 'next/server';
import { supabaseServer } from '@/lib/supabase/server';
import { startActivity } from '@/lib/auth';
import { isConfigured } from '@/lib/env';

export async function GET(request: NextRequest) {
  if (!isConfigured()) return NextResponse.redirect(new URL('/setup',request.url));
  const token = request.nextUrl.searchParams.get('token_hash');
  if (token && request.nextUrl.searchParams.get('type') === 'email') {
    const result = await (await supabaseServer()).auth.verifyOtp({ token_hash: token,type: 'email' });
    if (!result.error && result.data.user) { await startActivity(result.data.user.id); return NextResponse.redirect(new URL('/dashboard',request.url)); }
  }
  return NextResponse.redirect(new URL('/login?reason=confirmation',request.url));
}
