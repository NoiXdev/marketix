import { useTranslation } from '@/lib/i18n';
import { Loader2, RefreshCw, Settings2, X } from 'lucide-react';
import { ReactNode } from 'react';

interface Props {
  title?: string;
  loading?: boolean;
  error?: boolean;
  onReload?: () => void;
  editing?: boolean;
  onConfigure?: () => void;
  onRemove?: () => void;
  /** Render the content without the card frame (title bar + border/background). */
  frameless?: boolean;
  children: ReactNode;
}

export default function WidgetFrame({ title, loading, error, onReload, editing, onConfigure, onRemove, frameless, children }: Props) {
  const { t } = useTranslation();

  const body = loading ? (
    <div className="flex h-full items-center justify-center text-subtle">
      <Loader2 className="h-5 w-5 animate-spin" />
    </div>
  ) : error ? (
    <div className="flex h-full flex-col items-center justify-center gap-2 text-xs text-subtle">
      <span>{t('widgets.load_error')}</span>
      <button type="button" onClick={onReload} className="widget-no-drag inline-flex items-center gap-1 rounded-md border border-line px-2 py-1 hover:bg-elevated">
        <RefreshCw className="h-3 w-3" /> {t('widgets.retry')}
      </button>
    </div>
  ) : (
    children
  );

  const controls = (
    <div className="widget-no-drag flex items-center gap-1">
      <button type="button" onClick={onConfigure} aria-label={t('widgets.configure')} className="rounded p-1 text-subtle hover:bg-elevated hover:text-foreground">
        <Settings2 className="h-3.5 w-3.5" />
      </button>
      <button type="button" onClick={onRemove} aria-label={t('widgets.remove')} className="rounded p-1 text-subtle hover:bg-danger-soft hover:text-danger-foreground">
        <X className="h-3.5 w-3.5" />
      </button>
    </div>
  );

  // Frameless: just the content. In edit mode, show a dashed outline + floating
  // controls so the widget stays configurable/removable and draggable.
  if (frameless) {
    return (
      <div className={`relative h-full overflow-auto ${editing ? 'rounded-[var(--radius)] border border-dashed border-line' : ''}`}>
        {editing && (
          <div className="absolute right-1 top-1 z-10 rounded-md border border-line bg-surface/90 p-0.5 shadow-[var(--shadow-sm)] backdrop-blur-sm">
            {controls}
          </div>
        )}
        <div className="h-full">{body}</div>
      </div>
    );
  }

  return (
    <div className="flex h-full flex-col overflow-hidden rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
      <div className="flex items-center justify-between border-b border-line px-3 py-2">
        <h3 className="truncate text-xs font-semibold text-foreground">{title}</h3>
        {editing && controls}
      </div>
      <div className="relative min-h-0 flex-1 overflow-auto p-3">{body}</div>
    </div>
  );
}
