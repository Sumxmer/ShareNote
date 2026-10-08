import { randomUUID } from 'node:crypto';
import { fileTypeFromBuffer } from 'file-type';
import { imageSize } from 'image-size';
import AdmZip from 'adm-zip';
import { uploadName } from '@/lib/upload-policy';
export { MAX_FILE_SIZE, ALLOWED_EXTENSIONS } from '@/lib/upload-policy';

export async function inspectUpload(file: File) {
  const name = uploadName(file.name, file.size);
  const ext = name.split('.').pop()?.toLowerCase() || '';
  const buffer = Buffer.from(await file.arrayBuffer());
  const office = ext === 'docx' || ext === 'pptx';
  const detected = office ? undefined : await fileTypeFromBuffer(buffer);
  let mime = detected?.mime || '';
  if (ext === 'doc' || ext === 'ppt') {
    if (!buffer.subarray(0, 8).equals(Buffer.from('d0cf11e0a1b11ae1', 'hex'))) throw new Error('เนื้อหาไฟล์ไม่ตรงกับนามสกุล');
    mime = ext === 'doc' ? 'application/msword' : 'application/vnd.ms-powerpoint';
  } else if (ext === 'docx' || ext === 'pptx') {
    let entries;
    try { entries = new AdmZip(buffer).getEntries(); } catch { throw new Error('เอกสาร Office ไม่ถูกต้อง'); }
    const names = entries.map(entry => entry.entryName);
    const main = ext === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
    if (entries.length > 2000 || new Set(names).size !== names.length || !names.includes('[Content_Types].xml') || !names.includes(main)
      || entries.reduce((sum, entry) => sum + entry.header.size, 0) > 100 * 1024 * 1024
      || names.some(n => n.startsWith('/') || n.includes('\\') || /(^|\/)\.\.(\/|$)|vbaProject\.bin$/i.test(n))) throw new Error('โครงสร้างเอกสารหรือเนื้อหาไฟล์ไม่ถูกต้อง');
    mime = ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
  } else {
    const expected: Record<string, string> = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' };
    if (mime !== expected[ext]) throw new Error('เนื้อหาไฟล์ไม่ตรงกับนามสกุล');
    if (ext !== 'pdf') {
      let image;
      try { image = imageSize(buffer); } catch { throw new Error('ไฟล์รูปภาพไม่ถูกต้อง'); }
      if (!image.width || !image.height || image.width * image.height > 40_000_000) throw new Error('รูปภาพไม่ถูกต้องหรือมีขนาดภาพใหญ่เกินไป');
    }
  }
  return { buffer, name, ext, mime, randomName: `${randomUUID()}.${ext}` };
}
