import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
  rpc: vi.fn(), lookup: vi.fn(), matchUsername: vi.fn(), signIn: vi.fn(), profile: vi.fn(), signOut: vi.fn(), startActivity: vi.fn(),
}));
vi.mock('server-only', () => ({}));
vi.mock('next/navigation', () => ({ redirect: (path: string) => { throw new Error(`REDIRECT:${path}`); } }));
vi.mock('next/headers', () => ({ headers: async () => ({ get: () => null }), cookies: vi.fn() }));
vi.mock('next/cache', () => ({ revalidatePath: vi.fn() }));
vi.mock('@/lib/env', () => ({ isConfigured: () => true, siteUrl: () => 'http://localhost:3000' }));
vi.mock('@/lib/auth', () => ({ requireUser: vi.fn(), requireAdmin: vi.fn(), startActivity: mocks.startActivity }));
vi.mock('@/lib/supabase/server', () => ({
  supabaseService: () => ({ rpc: mocks.rpc, from: () => ({ select: () => ({ eq: mocks.matchUsername }) }) }),
  supabaseServer: async () => ({ auth: { signInWithPassword: mocks.signIn, signOut: mocks.signOut }, from: () => ({ select: () => ({ eq: () => ({ single: mocks.profile }) }) }) }),
}));
import { loginAction } from '@/app/actions';

function form(username: string, password = 'TestPassword9') {
  const data = new FormData(); data.set('username', username); data.set('password', password); return data;
}
beforeEach(() => {
  vi.clearAllMocks();
  mocks.rpc.mockResolvedValue({ data: false, error: null });
  mocks.lookup.mockResolvedValue({ data: { email: 'private@example.com' }, error: null });
  mocks.matchUsername.mockImplementation(() => ({ maybeSingle: mocks.lookup }));
  mocks.signIn.mockResolvedValue({ data: { user: { id: 'test-id' } }, error: null });
  mocks.profile.mockResolvedValue({ data: { status: 'active' }, error: null });
});

describe('username authentication on the server', () => {
  it('privately resolves the username after rate limiting and authenticates with its email', async () => {
    await expect(loginAction({}, form('student_1'))).rejects.toThrow('REDIRECT:/dashboard');
    expect(mocks.rpc).toHaveBeenNthCalledWith(1, 'login_is_locked', { p_email: 'student_1', p_ip: null });
    expect(mocks.matchUsername).toHaveBeenCalledWith('username', 'student_1');
    expect(mocks.rpc.mock.invocationCallOrder[0]).toBeLessThan(mocks.lookup.mock.invocationCallOrder[0]);
    expect(mocks.signIn).toHaveBeenCalledWith({ email: 'private@example.com', password: 'TestPassword9' });
    expect(mocks.rpc).toHaveBeenCalledWith('record_auth_event', { p_action: 'LOGIN_SUCCESS', p_email: 'student_1', p_user_id: 'test-id', p_ip: null });
    expect(mocks.startActivity).toHaveBeenCalledWith('test-id');
  });
  it('rejects email as a login identifier', async () => {
    const result = await loginAction({}, form('student@example.com'));
    expect(result.error).toBeTruthy();
    expect(mocks.lookup).not.toHaveBeenCalled();
    expect(mocks.signIn).not.toHaveBeenCalled();
  });
  it('blocks locked usernames before resolving the private email', async () => {
    mocks.rpc.mockResolvedValueOnce({ data: true, error: null });
    expect((await loginAction({}, form('student_1'))).error).toContain('15 นาที');
    expect(mocks.lookup).not.toHaveBeenCalled(); expect(mocks.signIn).not.toHaveBeenCalled();
  });
  it('logs unknown usernames and uses the same error as incorrect passwords without disclosing emails', async () => {
    mocks.lookup.mockResolvedValueOnce({ data: null, error: null });
    const unknown = await loginAction({}, form('unknown_user'));
    expect(mocks.signIn).not.toHaveBeenCalled();
    expect(mocks.rpc).toHaveBeenCalledWith('record_auth_event', { p_action: 'LOGIN_FAILED', p_email: 'unknown_user', p_user_id: null, p_ip: null });
    mocks.signIn.mockResolvedValueOnce({ data: { user: null }, error: { code: 'invalid_credentials' } });
    const incorrect = await loginAction({}, form('student_1'));
    expect(unknown).toEqual(incorrect); expect(JSON.stringify(incorrect)).not.toContain('private@example.com');
  });
  it('keeps email confirmation required and does not start an unconfirmed session', async () => {
    mocks.signIn.mockResolvedValueOnce({ data: { user: null }, error: { code: 'email_not_confirmed' } });
    expect((await loginAction({}, form('student_1'))).error).toContain('ยืนยันอีเมล');
    expect(mocks.startActivity).not.toHaveBeenCalled();
  });
  it('revokes login if the profile is suspended', async () => {
    mocks.profile.mockResolvedValueOnce({ data: { status: 'suspended' }, error: null });
    expect((await loginAction({}, form('student_1'))).error).toContain('บัญชียังไม่พร้อมใช้งาน');
    expect(mocks.signOut).toHaveBeenCalledWith({ scope: 'local' });
    expect(mocks.startActivity).not.toHaveBeenCalled();
  });
});
