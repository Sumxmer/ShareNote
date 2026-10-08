'use client';

import { useState } from 'react';
import { Eye, EyeOff, Upload } from 'lucide-react';

export function PasswordField({ name = 'password', label = 'รหัสผ่าน', autocomplete = 'current-password' }: { name?: string; label?: string; autocomplete?: string }) {
  const [visible, setVisible] = useState(false);
  return <><label htmlFor={name}>{label}</label><div className="password-row">
    <input id={name} name={name} type={visible ? 'text' : 'password'} required maxLength={72} autoComplete={autocomplete}/>
    <button type="button" className="password-toggle" aria-label={visible ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'} aria-pressed={visible} onClick={() => setVisible(!visible)}>{visible ? <EyeOff className="icon"/> : <Eye className="icon"/>}</button>
  </div></>;
}
export function UploadField() {
  const [name, setName] = useState('');
  return <><label htmlFor="file">ไฟล์ชีทสรุป</label><div className="dropzone">
    <Upload className="icon"/><strong>เลือกไฟล์หรือลากไฟล์มาวางที่นี่</strong>
    <small>PDF, DOC, DOCX, PPT, PPTX, JPG, PNG · ไม่เกิน 10 MB</small>
    <input type="file" id="file" name="file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" onChange={event => {
      const file = event.target.files?.[0]; setName(file ? `${file.name} · ${(file.size / 1048576).toFixed(2)} MB` : '');
    }}/><span className="selected-file" aria-live="polite">{name}</span>
  </div></>;
}
