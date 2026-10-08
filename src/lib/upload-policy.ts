export const MAX_FILE_SIZE = 10 * 1024 * 1024;
export const STAGING_BUCKET = 'note-upload-staging';
export const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];

export function uploadName(name: string, size: number) {
  if (!Number.isSafeInteger(size) || size <= 0 || size > MAX_FILE_SIZE) throw new Error('ไฟล์ต้องมีข้อมูลและมีขนาดไม่เกิน 10 MB');
  const clean = name.replaceAll('\\', '/').split('/').pop() || '';
  if (!clean || clean.length > 255 || /[\x00-\x1f\x7f]/.test(clean)) throw new Error('ชื่อไฟล์ไม่ถูกต้อง');
  if (!ALLOWED_EXTENSIONS.includes(clean.split('.').pop()?.toLowerCase() || '')) throw new Error('นามสกุลไฟล์ไม่อยู่ในรายการที่อนุญาต');
  return clean;
}
