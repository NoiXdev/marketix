import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Demo-mode helpers. The blocked-route list comes from the server
 * (DemoGuard::BLOCKED_ROUTES), so the UI never keeps its own copy.
 */
export function useDemo() {
  const demo = usePage<PageProps>().props.demo;

  return {
    enabled: Boolean(demo?.enabled),
    resetAt: demo?.resetAt ?? null,
    isBlocked: (routeName: string) => Boolean(demo?.blockedRoutes?.includes(routeName)),
  };
}
