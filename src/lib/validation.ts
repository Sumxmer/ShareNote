import { z } from 'zod';

const clean = (min: number, max: number) => z.string().trim().min(min).max(max).refine(v => !v.includes('\0'), 'ข้อมูลไม่ถูกต้อง');
export const noteSchema = z.object({ title: clean(1, 200), description: clean(0, 2000), subject_name: clean(1, 150) });
export const registerSchema = z.object({
  username: z.string().regex(/^[A-Za-z0-9_]{4,50}$/),
  email: z.email().max(100).transform(v => v.toLowerCase()),
  full_name: clean(1, 100),
  password: z.string().refine(v => Buffer.byteLength(v, 'utf8') >= 8 && Buffer.byteLength(v, 'utf8') <= 72 && /[A-Za-z]/.test(v) && /[0-9]/.test(v)),
  confirm_password: z.string(),
}).refine(v => v.password === v.confirm_password, { message: 'รหัสผ่านยืนยันไม่ตรงกัน', path: ['confirm_password'] });
export const loginSchema = z.object({ email: z.email().max(100).transform(v => v.toLowerCase()), password: z.string().min(1).max(200) });
export const commentSchema = clean(1, 1000);
export function formText(data: FormData, name: string): string {
  const value = data.get(name);
  if (typeof value !== 'string' || data.getAll(name).length !== 1 || value.includes('\0')) throw new Error('ข้อมูลฟอร์มไม่ถูกต้อง');
  return value;
}
export function positiveId(value: unknown): number {
  const id = String(value ?? '');
  if (!/^[1-9][0-9]{0,9}$/.test(id) || Number(id) > 2147483647) throw new Error('รหัสข้อมูลไม่ถูกต้อง');
  return Number(id);
}
export function localPath(value: string | undefined, fallback = '/dashboard') {
  return value && /^\/(?!\/)[A-Za-z0-9/_-]*(?:\?[A-Za-z0-9%=&_-]*)?$/.test(value) ? value : fallback;
}
