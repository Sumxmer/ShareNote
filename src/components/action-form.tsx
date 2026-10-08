'use client';

import { useActionState, type ReactNode } from 'react';
import { useFormStatus } from 'react-dom';
import type { ActionState } from '@/app/actions';

export function Submit({ children, className = 'btn' }: { children: ReactNode; className?: string }) {
  const { pending } = useFormStatus();
  return <button type="submit" className={className} disabled={pending}>{pending ? 'กำลังดำเนินการ…' : children}</button>;
}
export function ActionForm({ action, children, label, className = '' }: {
  action: (previous: ActionState, data: FormData) => Promise<ActionState>;
  children: ReactNode; label: string; className?: string;
}) {
  const [state, formAction] = useActionState(action, {});
  return <form action={formAction} className={className}>
    <div aria-live="polite">{state.error && <div role="alert" className="alert alert-error">{state.error}</div>}
      {state.message && <div role="status" className="alert alert-success">{state.message}</div>}</div>
    {children}<Submit>{label}</Submit>
  </form>;
}
export function ConfirmForm({ action, children, message, className = '' }: {
  action: (data: FormData) => Promise<void>; children: ReactNode; message: string; className?: string;
}) {
  return <form action={action} className={className} onSubmit={event => { if (!window.confirm(message)) event.preventDefault(); }}>{children}</form>;
}
