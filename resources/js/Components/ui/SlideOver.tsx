import { ReactNode, useEffect } from 'react';

/**
 * A right-side slide-over panel with a dimmed backdrop. Closes on the X button,
 * the Escape key, or a backdrop click. Theme-aware via design tokens.
 */
export function SlideOver({
  open,
  onClose,
  title,
  closeLabel = 'Close',
  children,
}: {
  open: boolean;
  onClose: () => void;
  title?: ReactNode;
  closeLabel?: string;
  children: ReactNode;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} aria-hidden="true" />
      <div className="relative flex h-full w-full max-w-xl flex-col overflow-y-auto border-l border-line bg-surface shadow-xl">
        <div className="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
          <h2 className="min-w-0 truncate font-medium text-foreground">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label={closeLabel}
            className="shrink-0 rounded-md px-2 py-1 text-muted transition hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
          >
            ✕
          </button>
        </div>
        <div className="min-h-0 flex-1 p-5">{children}</div>
      </div>
    </div>
  );
}
