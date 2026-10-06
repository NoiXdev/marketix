import { useTranslation } from '@/lib/i18n';
import { Dialog, DialogBackdrop, DialogPanel } from '@headlessui/react';
import { ChevronLeft, X } from 'lucide-react';
import { MouseEvent, ReactNode, useState } from 'react';
import VersionLabel from './VersionLabel';

export type MobileNavProps = { mobileOpen: boolean; onMobileClose: () => void };

type SidebarShellProps = MobileNavProps & {
  brand: ReactNode;
  children: (collapsed: boolean) => ReactNode;
};

const iconButton =
  'inline-flex items-center justify-center rounded-md border border-line bg-surface text-muted hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';

/**
 * Frame shared by the app and admin sidebars: docked and collapsible from the
 * `lg` breakpoint, an off-canvas drawer below it. `children` renders the body
 * (switcher + navigation) for the given collapsed state.
 */
export default function SidebarShell({ brand, mobileOpen, onMobileClose, children }: SidebarShellProps) {
  const { t } = useTranslation();

  const [collapsed, setCollapsed] = useState<boolean>(() => {
    try {
      return localStorage.getItem('sidebar-collapsed') === 'true';
    } catch {
      return false;
    }
  });
  function toggle() {
    setCollapsed((c) => {
      const next = !c;
      try {
        localStorage.setItem('sidebar-collapsed', String(next));
      } catch {
        /* ignore */
      }
      return next;
    });
  }

  // Close the drawer as soon as a link is tapped, not only once the visit has
  // finished; tapping the current page's link would not change the URL at all.
  function closeOnLinkClick(e: MouseEvent) {
    if ((e.target as Element).closest('a')) onMobileClose();
  }

  const footer = (centered: boolean) => (
    <div className="border-t border-line px-3 py-2">
      <VersionLabel className={`text-[11px] text-subtle ${centered ? 'text-center' : ''}`} />
    </div>
  );

  return (
    <>
      <aside
        className={`hidden shrink-0 flex-col border-r border-line bg-elevated transition-[width] duration-200 motion-reduce:transition-none lg:flex ${
          collapsed ? 'w-[72px]' : 'w-64'
        }`}
      >
        {/* Header */}
        <div className="flex h-14 items-center border-b border-line px-3">
          {!collapsed && <div className="flex-1 overflow-hidden">{brand}</div>}
          <button
            onClick={toggle}
            aria-label={t('common.nav.toggle_sidebar')}
            className={`h-7 w-7 ${iconButton} ${collapsed ? 'mx-auto' : 'ml-auto'}`}
          >
            <ChevronLeft className={`h-[15px] w-[15px] transition-transform duration-200 motion-reduce:transition-none ${collapsed ? 'rotate-180' : ''}`} />
          </button>
        </div>

        {children(collapsed)}

        {/* Footer: version only */}
        {footer(collapsed)}
      </aside>

      <Dialog open={mobileOpen} onClose={onMobileClose} className="relative z-50 lg:hidden">
        <DialogBackdrop
          transition
          className="fixed inset-0 bg-[rgb(10_14_22/0.45)] backdrop-blur-[2px] transition-opacity duration-200 ease-out data-[closed]:opacity-0 motion-reduce:transition-none"
        />
        <DialogPanel
          transition
          onClick={closeOnLinkClick}
          className="fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col border-r border-line bg-elevated shadow-[var(--shadow)] transition duration-200 ease-out data-[closed]:-translate-x-full motion-reduce:transition-none"
        >
          <div className="flex h-14 items-center gap-3 border-b border-line px-3">
            <div className="flex-1 overflow-hidden">{brand}</div>
            <button onClick={onMobileClose} aria-label={t('common.nav.close_menu')} className={`h-9 w-9 ${iconButton}`}>
              <X className="h-[18px] w-[18px]" />
            </button>
          </div>

          {children(false)}

          {footer(false)}
        </DialogPanel>
      </Dialog>
    </>
  );
}
