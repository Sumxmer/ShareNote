export function getPublicEnv() {
  return {
    url: process.env.NEXT_PUBLIC_SUPABASE_URL?.trim() ?? '',
    key: (process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY || process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY)?.trim() ?? '',
  };
}

export function isConfigured() {
  const { url, key } = getPublicEnv();
  const secret = process.env.APP_SESSION_SECRET ?? '';
  const storageKey = process.env.SUPABASE_SECRET_KEY || process.env.SUPABASE_SERVICE_ROLE_KEY || '';
  try {
    return new URL(url).protocol === 'https:' && key.length > 20 && storageKey.length > 20 && secret.length >= 32
      && !url.includes('your-project') && !key.includes('your-') && !storageKey.includes('your-') && !secret.startsWith('replace-');
  } catch { return false; }
}

export function siteUrl() {
  if (process.env.NEXT_PUBLIC_SITE_URL) return process.env.NEXT_PUBLIC_SITE_URL;
  const domain = process.env.VERCEL_PROJECT_PRODUCTION_URL || process.env.VERCEL_URL;
  return domain ? `https://${domain}` : 'http://localhost:3000';
}

export function secureCookies() { return siteUrl().startsWith('https://'); }
