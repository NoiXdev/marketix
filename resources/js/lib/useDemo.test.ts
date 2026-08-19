import { describe, expect, it, vi } from 'vitest';
import type { PageProps } from '@/types';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  usePage: usePageMock,
}));

import { useDemo } from './useDemo';

type Demo = PageProps['demo'];

function setDemo(demo: Demo): void {
  usePageMock.mockReturnValue({ props: { demo } });
}

describe('useDemo', () => {
  it('reports disabled, no reset time, and nothing blocked when the demo prop is absent', () => {
    setDemo(null);

    const demo = useDemo();

    expect(demo.enabled).toBe(false);
    expect(demo.resetAt).toBeNull();
    expect(demo.isBlocked('app.profile.update')).toBe(false);
  });

  it('reports enabled and the reset time when the demo prop is present', () => {
    setDemo({ enabled: true, blockedRoutes: ['app.profile.update'], resetAt: '04:00' });

    const demo = useDemo();

    expect(demo.enabled).toBe(true);
    expect(demo.resetAt).toBe('04:00');
  });

  it('flags a route present in blockedRoutes as blocked', () => {
    setDemo({ enabled: true, blockedRoutes: ['app.profile.update'], resetAt: '04:00' });

    expect(useDemo().isBlocked('app.profile.update')).toBe(true);
  });

  it('does not flag a route absent from blockedRoutes as blocked', () => {
    setDemo({ enabled: true, blockedRoutes: ['app.profile.update'], resetAt: '04:00' });

    expect(useDemo().isBlocked('app.project.domains.store')).toBe(false);
  });
});
