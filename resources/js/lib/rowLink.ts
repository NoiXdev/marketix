import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';

// Clicks that originate inside one of these never trigger row navigation —
// the element handles its own behavior (action buttons, dropdowns, links).
const INTERACTIVE_SELECTOR = 'a, button, select, input, label, [role="button"]';

/**
 * Tailwind classes giving a row the clickable affordance.
 * Merge with the row's existing classes (e.g. `group ${ROW_LINK_CLASS}`).
 */
export const ROW_LINK_CLASS =
  'cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50';

/**
 * Returns a `<tr>` onClick handler that navigates to `href`.
 * Bails out when the click lands on an interactive control, or when the user
 * is selecting text, so existing buttons/links/dropdowns keep working.
 */
export function rowLink(href: string) {
  return (e: MouseEvent<HTMLTableRowElement>) => {
    if ((e.target as HTMLElement).closest(INTERACTIVE_SELECTOR)) return;
    if (window.getSelection()?.toString()) return;
    router.visit(href);
  };
}
