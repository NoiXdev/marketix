import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Languages } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function LocaleSwitcher() {
  const { availableLocales, locale } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') setOpen(false);
    }
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  function change(next: string) {
    setOpen(false);
    if (next === locale) return;
    router.post(route('app.locale.update'), { locale: next }, { preserveScroll: true });
  }

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="true"
        aria-label={t('common.language.label')}
        className="inline-flex h-9 items-center gap-2 rounded-lg border border-line bg-surface px-2.5 text-sm font-semibold text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
      >
        <Languages className="h-[17px] w-[17px] text-muted" />
        <span className="uppercase">{locale}</span>
        <ChevronDown className="h-[14px] w-[14px] text-subtle" />
      </button>
      {open && (
        <div className="absolute right-0 top-full z-30 mt-2 max-h-64 w-48 overflow-auto rounded-xl border border-line bg-surface p-1.5 shadow-[var(--shadow)]">
          <p className="px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wider text-subtle">{t('common.language.label')}</p>
          {availableLocales.map((l) => (
            <button
              key={l.code}
              onClick={() => change(l.code)}
              className={`flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                l.code === locale ? 'font-semibold text-accent-soft-foreground' : 'text-foreground'
              }`}
            >
              <span>{l.label}</span>
              {l.code === locale && <Check className="h-[15px] w-[15px] text-accent-soft-foreground" />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
