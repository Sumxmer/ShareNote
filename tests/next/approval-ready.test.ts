import { beforeEach, describe, expect, it, vi } from 'vitest';
const mocks = vi.hoisted(() => ({ stats: vi.fn(), signUpload: vi.fn() }));
vi.mock('server-only', () => ({}));
vi.mock('@/lib/auth', () => ({ requireUser: async () => ({ user_id: 'test-owner' }) }));
vi.mock('@/lib/supabase/server', () => ({
  supabaseServer: async () => ({ rpc: mocks.stats }),
  supabaseService: () => ({ storage: { from: () => ({ createSignedUploadUrl: mocks.signUpload }) } }),
}));
import { approvalReady } from '@/lib/data';
import { prepareUploadAction } from '@/app/upload-actions';
beforeEach(() => { vi.clearAllMocks(); });

describe('approval rollout safety', () => {
  it('does not issue any upload capability while the live database still has the old schema', async () => {
    mocks.stats.mockResolvedValue({ data: { total: 0, active: 0 }, error: null });
    const result = await prepareUploadAction('sheet.pdf', 50);
    expect(result).toHaveProperty('error');
    expect(mocks.signUpload).not.toHaveBeenCalled();
  });
  it('recognizes the upgraded database even when no submissions are waiting', async () => {
    mocks.stats.mockResolvedValue({ data: { total: 0, active: 0, pending: 0 }, error: null });
    expect(await approvalReady()).toBe(true);
  });
  it('does not treat an unavailable statistics response as an upgraded database', async () => {
    mocks.stats.mockResolvedValue({ data: null, error: null });
    expect(await approvalReady()).toBe(false);
  });
});
