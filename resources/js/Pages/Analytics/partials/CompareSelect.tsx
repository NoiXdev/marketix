import { useTranslation } from '@/lib/i18n';
import { COMPARISONS, Comparison } from '@/Pages/Analytics/partials/period';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/react';
import { Check, ChevronDown, GitCompareArrows } from 'lucide-react';

export default function CompareSelect({ value, onChange }: { value: Comparison; onChange: (value: Comparison) => void }) {
  const { t } = useTranslation();

  return (
    <Listbox value={value} onChange={onChange}>
      <ListboxButton
        aria-label={t('analytics.dashboard.compare.label')}
        className="border-line bg-surface text-foreground hover:bg-elevated inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold shadow-[var(--shadow-sm)] focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
      >
        <GitCompareArrows className="text-subtle h-4 w-4" />
        {t(`analytics.dashboard.compare.${value}`)}
        <ChevronDown className="text-subtle h-4 w-4" />
      </ListboxButton>
      <ListboxOptions anchor="bottom end" className="border-line bg-surface z-30 w-48 rounded-lg border py-1 shadow-[var(--shadow)] [--anchor-gap:6px] focus:outline-none">
        {COMPARISONS.map((option) => (
          <ListboxOption
            key={option}
            value={option}
            className="text-foreground data-[focus]:bg-elevated flex cursor-pointer items-center justify-between px-3 py-2 text-sm select-none"
          >
            {t(`analytics.dashboard.compare.${option}`)}
            {option === value && <Check className="text-accent-soft-foreground h-4 w-4" strokeWidth={3} />}
          </ListboxOption>
        ))}
      </ListboxOptions>
    </Listbox>
  );
}
