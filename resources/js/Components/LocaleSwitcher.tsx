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
        className="border-line bg-surface text-foreground hover:bg-elevated inline-flex h-9 items-center gap-2 rounded-lg border px-2.5 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
      >
        <Languages className="text-muted h-[17px] w-[17px]" />
        <span className="uppercase">{locale}</span>
        <ChevronDown className="text-subtle h-[14px] w-[14px]" />
      </button>
      {open && (
        <div className="border-line bg-surface absolute top-full right-0 z-30 mt-2 max-h-64 w-48 overflow-auto rounded-xl border p-1.5 shadow-[var(--shadow)]">
          <p className="text-subtle px-2.5 py-1 text-[10.5px] font-bold tracking-wider uppercase">{t('common.language.label')}</p>
          {availableLocales.map((l) => (
            <button
              key={l.code}
              onClick={() => change(l.code)}
              className={`hover:bg-elevated flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
                l.code === locale ? 'text-accent-soft-foreground font-semibold' : 'text-foreground'
              }`}
            >
              <span>{l.label}</span>
              {l.code === locale && <Check className="text-accent-soft-foreground h-[15px] w-[15px]" />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
