import { noteStatusLabel, type NoteStatus } from '@/lib/note-status';
import { ActionForm } from '@/components/action-form';
import { reviewNoteAction } from '@/app/actions';

export function NoteStatusBadge({ status }: { status: NoteStatus }) {
  return <span className={`badge ${status === 'pending' ? 'badge-pending' : status === 'rejected' || status === 'removed' ? 'badge-suspended' : ''}`}>{noteStatusLabel[status]}</span>;
}

export function NoteReviewActions({ noteId }: { noteId: number }) {
  return <div className="review-actions">
    <ActionForm action={reviewNoteAction.bind(null, noteId, 'active')} label="อนุมัติ" className="inline-action" children={null}/>
    <ActionForm action={reviewNoteAction.bind(null, noteId, 'rejected')} label="ไม่อนุมัติ" className="inline-action review-reject" children={null}/>
  </div>;
}
