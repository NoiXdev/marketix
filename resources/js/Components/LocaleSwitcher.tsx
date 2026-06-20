import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';

export default function LocaleSwitcher() {
  const { availableLocales, locale } = usePage<PageProps>().props;
  const { t } = useTranslation();

  function change(next: string) {
    if (next === locale) return;
    router.post(route('app.locale.update'), { locale: next }, { preserveScroll: true });
  }

  return (
    <label className="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-600 dark:text-slate-400">
      <Languages className="h-4 w-4 shrink-0 text-slate-400" />
      <span className="sr-only">{t('common.language.label')}</span>
      <select
        aria-label={t('common.language.label')}
        value={locale}
        onChange={(e) => change(e.target.value)}
        className="flex-1 bg-transparent text-sm focus:outline-none dark:bg-slate-900"
      >
        {availableLocales.map((l) => (
          <option key={l.code} value={l.code} className="text-slate-900">
            {l.label}
          </option>
        ))}
      </select>
    </label>
  );
}
