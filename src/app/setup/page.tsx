import { SetupGuide } from '@/components/setup-guide';
import { redirect } from 'next/navigation';
import { isConfigured } from '@/lib/env';
export const metadata = { title: 'ตั้งค่าเว็บไซต์' };
export default function SetupPage() {
  if (isConfigured()) redirect('/');
  return <SetupGuide/>;
}
