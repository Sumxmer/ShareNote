export type NoteStatus = 'active' | 'pending' | 'rejected' | 'removed';

export const noteStatusLabel: Record<NoteStatus, string> = {
  active: 'เผยแพร่แล้ว', pending: 'รออนุมัติ', rejected: 'ไม่อนุมัติ', removed: 'ถูกนำออก',
};

export function canDownloadNote(status: NoteStatus, role: 'user' | 'admin') {
  return status === 'active' || (role === 'admin' && (status === 'pending' || status === 'rejected'));
}
