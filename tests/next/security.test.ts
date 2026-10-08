import { describe, expect, it } from 'vitest';
import { activityToken, activityValid, downloadTicket, ticketValid, IDLE_TIMEOUT_MS } from '@/lib/tokens';
import { registerSchema, loginSchema, noteSchema, positiveId, localPath, formText } from '@/lib/validation';
import { canDownloadNote } from '@/lib/note-status';
import { inspectUpload, MAX_FILE_SIZE } from '@/lib/uploads';
import AdmZip from 'adm-zip';
import { renderToStaticMarkup } from 'react-dom/server';
import { createElement } from 'react';

const secret = '0123456789abcdef0123456789abcdef';
const uid = '11111111-1111-4111-8111-111111111111';
const time = 10000000;

describe('signed sessions and download tickets', () => {
  it('accepts an authentic session within 30 minutes', () => expect(activityValid(activityToken(uid,secret,time),uid,secret,time+IDLE_TIMEOUT_MS-1)).toBe(true));
  it('expires an idle session after 30 minutes', () => expect(activityValid(activityToken(uid,secret,time),uid,secret,time+IDLE_TIMEOUT_MS+1)).toBe(false));
  it('rejects an unsigned activity timestamp', () => expect(activityValid(`${uid}.${time}.forged`,uid,secret,time)).toBe(false));
  it('rejects a session belonging to another user', () => expect(activityValid(activityToken(uid,secret,time),'other',secret,time)).toBe(false));
  it('rejects a future timestamp', () => expect(activityValid(activityToken(uid,secret,time+60000),uid,secret,time)).toBe(false));
  it('accepts a valid download ticket', () => expect(ticketValid(downloadTicket(uid,12,secret,time),uid,12,secret,time)).toBe(true));
  it('expires a download ticket after 60 seconds', () => expect(ticketValid(downloadTicket(uid,12,secret,time),uid,12,secret,time+60001)).toBe(false));
  it('rejects a ticket for a different note or account', () => {
    const token = downloadTicket(uid,12,secret,time);
    expect(ticketValid(token,uid,13,secret,time)).toBe(false);
    expect(ticketValid(token,'other',12,secret,time)).toBe(false);
  });
  it('rejects an altered ticket', () => expect(ticketValid(downloadTicket(uid,12,secret,time)+'x',uid,12,secret,time)).toBe(false));
});

describe('validation and output encoding', () => {
  it('accepts username login and rejects email login and injected usernames', () => {
    expect(loginSchema.safeParse({ username: 'student_1', password: 'StudyTest123' }).success).toBe(true);
    expect(loginSchema.safeParse({ username: 'student@example.com', password: 'StudyTest123' }).success).toBe(false);
    expect(loginSchema.safeParse({ username: "' OR 1=1--", password: 'StudyTest123' }).success).toBe(false);
  });
  it('limits unpublished downloads to administrator review', () => {
    for (const status of ['pending','rejected'] as const) {
      expect(canDownloadNote(status,'user')).toBe(false);
      expect(canDownloadNote(status,'admin')).toBe(true);
    }
    expect(canDownloadNote('active','user')).toBe(true);
    expect(canDownloadNote('removed','admin')).toBe(false);
  });
  const valid = { username: 'student_1',email: 'student@example.com',full_name: 'นักศึกษาทดสอบ',password: 'StudyTest123',confirm_password: 'StudyTest123' };
  it('accepts Thai names and a valid registration', () => expect(registerSchema.safeParse(valid).success).toBe(true));
  it('rejects passwords exceeding 72 bytes', () => expect(registerSchema.safeParse({ ...valid,password: 'Ab1'+'ก'.repeat(24),confirm_password: 'Ab1'+'ก'.repeat(24) }).success).toBe(false));
  it('rejects passwords without a letter and digit', () => expect(registerSchema.safeParse({ ...valid,password: 'abcdefgh',confirm_password: 'abcdefgh' }).success).toBe(false));
  it('rejects mismatched password confirmation', () => expect(registerSchema.safeParse({ ...valid,confirm_password: 'different' }).success).toBe(false));
  it('rejects empty or oversized note fields', () => {
    expect(noteSchema.safeParse({ title: '',description: '',subject_name: 'SQL' }).success).toBe(false);
    expect(noteSchema.safeParse({ title: 'A',description: 'x'.repeat(2001),subject_name: 'SQL' }).success).toBe(false);
  });
  it('rejects duplicate form fields and file objects in text fields', () => {
    const data = new FormData(); data.append('title','one'); data.append('title','two');
    expect(() => formText(data,'title')).toThrow();
    const file = new FormData(); file.set('title',new File(['x'],'x.txt'));
    expect(() => formText(file,'title')).toThrow();
  });
  it('rejects invalid numeric IDs', () => {
    for (const id of ['0','-1','1 OR 1=1','2147483648','1.2']) expect(() => positiveId(id)).toThrow();
    expect(positiveId('12')).toBe(12);
  });
  it('rejects open redirects', () => {
    expect(localPath('//evil.test')).toBe('/dashboard'); expect(localPath('https://evil.test')).toBe('/dashboard');
    expect(localPath('/notes/12')).toBe('/notes/12');
  });
  it('renders user scripts as escaped text with React', () => {
    const html = renderToStaticMarkup(createElement('p',null,"<script>alert('XSS')</script>"));
    expect(html).not.toContain('<script>'); expect(html).toContain('&lt;script&gt;');
  });
});

describe('server-side upload validation', () => {
  const pdf = Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n');
  it('accepts PDF content and generates a random filename', async () => {
    const result = await inspectUpload(new File([pdf],'ชีทเรียน.pdf',{ type: 'text/plain' }));
    expect(result.mime).toBe('application/pdf'); expect(result.randomName).toMatch(/^[a-f0-9-]{36}\.pdf$/);
  });
  it('rejects script extensions', async () => { await expect(inspectUpload(new File([pdf],'bad.php'))).rejects.toThrow(); });
  it('rejects disguised PHP content', async () => { await expect(inspectUpload(new File(['<?php echo 1;'],'bad.pdf',{ type: 'application/pdf' }))).rejects.toThrow(); });
  it('rejects extension/content mismatch', async () => { await expect(inspectUpload(new File([pdf],'bad.png'))).rejects.toThrow(); });
  it('rejects empty uploads', async () => { await expect(inspectUpload(new File([],'empty.pdf'))).rejects.toThrow(); });
  it('accepts a valid PNG and rejects it with a PDF extension', async () => {
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=','base64');
    expect((await inspectUpload(new File([png],'image.png'))).mime).toBe('image/png');
    await expect(inspectUpload(new File([png],'image.pdf'))).rejects.toThrow();
  });
  it('rejects files larger than 10 MiB', async () => { await expect(inspectUpload(new File([new Uint8Array(MAX_FILE_SIZE+1)],'large.pdf'))).rejects.toThrow(); });
  it('validates DOCX and prevents disguising DOCX as PPTX', async () => {
    const zip = new AdmZip(); zip.addFile('[Content_Types].xml',Buffer.from('<Types/>')); zip.addFile('word/document.xml',Buffer.from('<document/>'));
    const buffer = zip.toBuffer();
    expect((await inspectUpload(new File([new Uint8Array(buffer)],'sample.docx'))).ext).toBe('docx');
    await expect(inspectUpload(new File([new Uint8Array(buffer)],'sample.pptx'))).rejects.toThrow();
  });
  it('rejects macro payloads in DOCX archives', async () => {
    const zip = new AdmZip(); zip.addFile('[Content_Types].xml',Buffer.from('<Types/>')); zip.addFile('word/document.xml',Buffer.from('<document/>')); zip.addFile('word/vbaProject.bin',Buffer.from('bad'));
    await expect(inspectUpload(new File([new Uint8Array(zip.toBuffer())],'macro.docx'))).rejects.toThrow();
  });
});
