import { Button, Input } from '@/Components/ui';
import { formatRange, Period, RANGE_PRESETS, toIsoDate } from '@/Pages/Analytics/partials/period';
import { useTranslation } from '@/lib/i18n';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { CalendarDays, Check, ChevronDown } from 'lucide-react';
import { FormEvent, useState } from 'react';

export default function DateRangePicker({
  period,
  onSelect,
}: {
  period: Period;
  onSelect: (range: string, custom?: { from: string; to: string }) => void;
}) {
  const { t, locale } = useTranslation();
  const [from, setFrom] = useState(period.from);
  const [to, setTo] = useState(period.to);
  const today = toIsoDate(new Date());

  function applyCustom(e: FormEvent, close: () => void) {
    e.preventDefault();
    if (!from || !to) return;
    onSelect('custom', from <= to ? { from, to } : { from: to, to: from });
    close();
  }

  return (
    <Popover>
      <PopoverButton
        onClick={() => {
          setFrom(period.from);
          setTo(period.to);
        }}
        className="inline-flex items-center gap-2 rounded-lg border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-foreground shadow-[var(--shadow-sm)] hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
      >
        <CalendarDays className="h-4 w-4 text-subtle" />
        {t(`analytics.dashboard.range.${period.range}`)}
        <span className="hidden font-normal text-muted sm:inline">{formatRange(period.from, period.to, locale)}</span>
        <ChevronDown className="h-4 w-4 text-subtle" />
      </PopoverButton>

      <PopoverPanel
        anchor="bottom end"
        className="z-30 w-72 rounded-lg border border-line bg-surface py-1 shadow-[var(--shadow)] [--anchor-gap:6px] focus:outline-none"
      >
        {({ close }) => (
          <>
            {RANGE_PRESETS.map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => {
                  onSelect(key);
                  close();
                }}
                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-foreground hover:bg-elevated focus-visible:bg-elevated focus-visible:outline-none"
              >
                {t(`analytics.dashboard.range.${key}`)}
                {period.range === key && <Check className="h-4 w-4 text-accent-soft-foreground" strokeWidth={3} />}
              </button>
            ))}

            <form onSubmit={(e) => applyCustom(e, close)} className="mt-1 border-t border-line px-3 pb-2 pt-3">
              <p className="mb-2 flex items-center justify-between text-xs font-semibold text-muted">
                {t('analytics.dashboard.range.custom')}
                {period.range === 'custom' && <Check className="h-4 w-4 text-accent-soft-foreground" strokeWidth={3} />}
              </p>
              <div className="grid grid-cols-2 gap-2">
                <label className="space-y-1 text-xs text-muted">
                  <span>{t('analytics.dashboard.range.from')}</span>
                  <Input type="date" value={from} max={to || today} onChange={(e) => setFrom(e.target.value)} className="px-2 py-1.5 text-xs" />
                </label>
                <label className="space-y-1 text-xs text-muted">
                  <span>{t('analytics.dashboard.range.to')}</span>
                  <Input type="date" value={to} min={from} max={today} onChange={(e) => setTo(e.target.value)} className="px-2 py-1.5 text-xs" />
                </label>
              </div>
              <Button type="submit" size="sm" className="mt-3 w-full" disabled={!from || !to}>
                {t('analytics.dashboard.range.apply')}
              </Button>
            </form>
          </>
        )}
      </PopoverPanel>
    </Popover>
  );
}
