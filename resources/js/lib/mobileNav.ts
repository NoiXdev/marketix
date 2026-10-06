import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

// Tailwind's `lg` breakpoint: from here on the sidebar is docked instead of a drawer.
const DESKTOP_QUERY = '(min-width: 64rem)';

/**
 * Open state of the mobile navigation drawer. The drawer is bound to the URL
 * it was opened on, so any page visit closes it while partial reloads such as
 * polling keep it open.
 */
export function useMobileNav() {
  const { url } = usePage();
  const [openedAt, setOpenedAt] = useState<string | null>(null);
  const open = openedAt === url;

  useEffect(() => {
    if (!open) return;
    const desktop = window.matchMedia(DESKTOP_QUERY);
    const onChange = (e: MediaQueryListEvent) => {
      if (e.matches) setOpenedAt(null);
    };
    desktop.addEventListener('change', onChange);
    return () => desktop.removeEventListener('change', onChange);
  }, [open]);

  return {
    open,
    show: () => setOpenedAt(url),
    close: () => setOpenedAt(null),
  };
}
