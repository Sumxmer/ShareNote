import { createHmac, timingSafeEqual, randomUUID } from 'node:crypto';

export const IDLE_TIMEOUT_MS = 30 * 60 * 1000;
export const ACTIVITY_COOKIE = 'noteshare-activity';
const TICKET_LIFETIME_MS = 60 * 1000;

function signature(value: string, secret: string) {
  return createHmac('sha256', secret).update(value).digest('base64url');
}
function equal(a: string, b: string) {
  const aa = Buffer.from(a); const bb = Buffer.from(b);
  return aa.length === bb.length && timingSafeEqual(aa, bb);
}
export function activityToken(userId: string, secret: string, now = Date.now()) {
  const value = `${userId}.${now}`;
  return `${value}.${signature(`activity:${value}`, secret)}`;
}
export function activityValid(token: string | undefined, userId: string, secret: string, now = Date.now()) {
  if (!token) return false;
  const [uid, stamp, mac, extra] = token.split('.');
  const time = Number(stamp);
  return !extra && uid === userId && Number.isSafeInteger(time) && time <= now + 5000
    && now-time <= IDLE_TIMEOUT_MS && equal(mac || '', signature(`activity:${uid}.${stamp}`, secret));
}
export function downloadTicket(userId: string, noteId: number, secret: string, now = Date.now()) {
  const value = `${userId}.${noteId}.${now}.${randomUUID()}`;
  return `${value}.${signature(`download:${value}`, secret)}`;
}
export function ticketValid(token: string, userId: string, noteId: number, secret: string, now = Date.now()) {
  const [uid, id, stamp, nonce, mac, extra] = token.split('.');
  const time = Number(stamp);
  return !extra && uid === userId && id === String(noteId) && !!nonce
    && Number.isSafeInteger(time) && time <= now + 5000 && now-time <= TICKET_LIFETIME_MS
    && equal(mac || '', signature(`download:${uid}.${id}.${stamp}.${nonce}`, secret));
}
