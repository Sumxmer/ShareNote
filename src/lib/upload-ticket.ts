import { createHmac, timingSafeEqual } from 'node:crypto';
import { uploadName } from '@/lib/upload-policy';

export const UPLOAD_TICKET_MS = 15 * 60 * 1000;
type UploadClaim = { userId: string; path: string; name: string; size: number; issued: number };
function mac(value: string, secret: string) {
  return createHmac('sha256', secret).update(`upload:${value}`).digest('base64url');
}
export function uploadTicket(claim: Omit<UploadClaim, 'issued'>, secret: string, now = Date.now()) {
  const value = Buffer.from(JSON.stringify({ ...claim, issued: now })).toString('base64url');
  return `${value}.${mac(value, secret)}`;
}
export function readUploadTicket(token: string, userId: string, secret: string, now = Date.now()): UploadClaim | null {
  if (token.length > 3000) return null;
  const [value, signature, extra] = token.split('.');
  if (!value || !signature || extra) return null;
  const expected = Buffer.from(mac(value, secret)); const received = Buffer.from(signature);
  if (expected.length !== received.length || !timingSafeEqual(expected, received)) return null;
  try {
    const claim = JSON.parse(Buffer.from(value, 'base64url').toString('utf8')) as UploadClaim;
    if (claim.userId !== userId || typeof claim.path !== 'string' || !claim.path.startsWith(`${userId}/`)
      || !/^[a-f0-9-]{36}\/([a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})$/i.test(claim.path)
      || typeof claim.name !== 'string' || uploadName(claim.name, claim.size) !== claim.name
      || !Number.isSafeInteger(claim.issued) || claim.issued > now + 5000 || now - claim.issued > UPLOAD_TICKET_MS) return null;
    return claim;
  } catch { return null; }
}
