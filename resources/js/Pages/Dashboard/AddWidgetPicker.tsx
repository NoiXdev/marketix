import { Button } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { WIDGET_DEFS, type WidgetType } from '@/lib/widgets/schema';
import { X } from 'lucide-react';
import { useEffect } from 'react';

interface Props {
  onPick: (type: WidgetType) => void;
  onClose: () => void;
}

export default function AddWidgetPicker({ onPick, onClose }: Props) {
  const { t } = useTranslation();

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const types = Object.keys(WIDGET_DEFS) as WidgetType[];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        aria-label={t('dashboards.add_widget')}
        className="w-full max-w-sm rounded-[var(--radius)] border border-line bg-surface p-4 shadow-[var(--shadow)]"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-foreground">{t('dashboards.add_widget')}</h2>
          <button type="button" onClick={onClose} aria-label={t('common.actions.close')} className="rounded p-1 text-subtle hover:bg-elevated hover:text-foreground">
            <X className="h-4 w-4" />
          </button>
        </div>
        <div className="space-y-1">
          {types.map((type) => (
            <button
              key={type}
              type="button"
              onClick={() => onPick(type)}
              className="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
              {t(WIDGET_DEFS[type].labelKey)}
            </button>
          ))}
        </div>
        <div className="mt-3 flex justify-end">
          <Button variant="secondary" size="sm" onClick={onClose}>
            {t('common.actions.cancel')}
          </Button>
        </div>
      </div>
    </div>
  );
}
