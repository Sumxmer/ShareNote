import { EmptyState } from '@/components/ui';
export default function Forbidden() { return <EmptyState title="บัญชีนี้ไม่มีสิทธิ์เข้าถึงหน้านี้" href="/" label="กลับหน้าหลัก">เฉพาะเจ้าของข้อมูลหรือผู้ดูแลระบบที่ได้รับสิทธิ์เท่านั้น</EmptyState>; }
