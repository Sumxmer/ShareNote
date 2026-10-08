import { describe, expect, it } from 'vitest';
import { uploadTicket, readUploadTicket, UPLOAD_TICKET_MS } from '@/lib/upload-ticket';
import { MAX_FILE_SIZE } from '@/lib/upload-policy';

const userId = '11111111-1111-4111-8111-111111111111';
const secret = 'test-secret-0123456789abcdef0123456789';
const now = 10000000;
const claim = { userId, path: `${userId}/22222222-2222-4222-8222-222222222222`, name: 'ชีทเรียน.pdf', size: 50 };

describe('scoped upload tickets', () => {
  it('accepts an authentic ticket and preserves a Thai filename', () => {
    expect(readUploadTicket(uploadTicket(claim, secret, now), userId, secret, now)).toEqual({ ...claim, issued: now });
  });
  it('rejects a ticket used by another account', () => {
    expect(readUploadTicket(uploadTicket(claim, secret, now), 'other', secret, now)).toBeNull();
  });
  it('rejects an altered payload or signature', () => {
    const ticket = uploadTicket(claim, secret, now);
    expect(readUploadTicket(`x${ticket}`, userId, secret, now)).toBeNull();
    expect(readUploadTicket(`${ticket}x`, userId, secret, now)).toBeNull();
  });
  it('rejects expired and future tickets', () => {
    expect(readUploadTicket(uploadTicket(claim, secret, now), userId, secret, now + UPLOAD_TICKET_MS + 1)).toBeNull();
    expect(readUploadTicket(uploadTicket(claim, secret, now + 60000), userId, secret, now)).toBeNull();
  });
  it('rejects paths outside the account and path traversal', () => {
    for (const path of ['other/object', `${userId}/../../notes/file.pdf`, `${userId}/file.pdf`]) {
      expect(readUploadTicket(uploadTicket({ ...claim, path }, secret, now), userId, secret, now)).toBeNull();
    }
  });
  it('rejects oversized files and executable filenames even with a valid signature', () => {
    expect(readUploadTicket(uploadTicket({ ...claim, size: MAX_FILE_SIZE + 1 }, secret, now), userId, secret, now)).toBeNull();
    expect(readUploadTicket(uploadTicket({ ...claim, name: 'bad.php' }, secret, now), userId, secret, now)).toBeNull();
  });
});
