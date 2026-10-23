import { Button, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import AnnotationSelect from '@/Pages/Analytics/partials/AnnotationSelect';
import { Comparison, COMPARISON_PRESETS, formatRange, Period, shiftDate, toIsoDate } from '@/Pages/Analytics/partials/period';
import { Annotation } from '@/Pages/Analytics/types';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Check, ChevronDown, GitCompareArrows } from 'lucide-react';
import { FormEvent, useState } from 'react';

type CustomComparison = { compare_from: string; compare_to: string };

function initialRange(period: Period): { from: string; to: string } {
  if (period.compare_from && period.compare_to) return { from: period.compare_from, to: period.compare_to };
  return { from: shiftDate(period.from, -period.days), to: shiftDate(period.from, -1) };
}

export default function CompareSelect({
  period,
  annotations,
  onChange,
}: {
  period: Period;
  annotations: Annotation[];
  onChange: (value: Comparison, custom?: CustomComparison) => void;
}) {
  const { t, locale } = useTranslation();
  const [from, setFrom] = useState(() => initialRange(period).from);
  const [to, setTo] = useState(() => initialRange(period).to);
  const today = toIsoDate(new Date());
  const past = annotations.filter((a) => a.date <= today);
  const matchedFrom = to ? shiftDate(to, -(period.days - 1)) : '';

  function reset() {
    const range = initialRange(period);
    setFrom(range.from);
    setTo(range.to);
  }

  function applyCustom(e: FormEvent, close: () => void) {
    e.preventDefault();
    if (!from || !to) return;
    onChange('custom', from <= to ? { compare_from: from, compare_to: to } : { compare_from: to, compare_to: from });
    close();
  }

  return (
    <Popover>
      <PopoverButton
        onClick={reset}
        aria-label={t('analytics.dashboard.compare.label')}
        className="border-line bg-surface text-foreground hover:bg-elevated inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold shadow-[var(--shadow-sm)] focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
      >
        <GitCompareArrows className="text-subtle h-4 w-4" />
        {t(`analytics.dashboard.compare.${period.compare}`)}
        {period.compare === 'custom' && period.compare_from && period.compare_to && (
          <span className="text-muted hidden font-normal sm:inline">{formatRange(period.compare_from, period.compare_to, locale)}</span>
        )}
        <ChevronDown className="text-subtle h-4 w-4" />
      </PopoverButton>

      <PopoverPanel
        anchor="bottom end"
        className={`border-line bg-surface z-30 rounded-lg border py-1 shadow-[var(--shadow)] [--anchor-gap:6px] focus:outline-none ${past.length > 0 ? 'w-80' : 'w-72'}`}
      >
        {({ close }) => (
          <>
            {COMPARISON_PRESETS.map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => {
                  if (option !== period.compare) onChange(option);
                  close();
                }}
                className="text-foreground hover:bg-elevated focus-visible:bg-elevated flex w-full items-center justify-between px-3 py-2 text-left text-sm focus-visible:outline-none"
              >
                {t(`analytics.dashboard.compare.${option}`)}
                {period.compare === option && <Check className="text-accent-soft-foreground h-4 w-4" strokeWidth={3} />}
              </button>
            ))}

            <form onSubmit={(e) => applyCustom(e, close)} className="border-line mt-1 border-t px-3 pt-3 pb-2">
              <p className="text-muted mb-2 flex items-center justify-between text-xs font-semibold">
                {t('analytics.dashboard.compare.custom')}
                {period.compare === 'custom' && <Check className="text-accent-soft-foreground h-4 w-4" strokeWidth={3} />}
              </p>
              <div className="grid grid-cols-2 gap-2">
                <label className="text-muted space-y-1 text-xs">
                  <span>{t('analytics.dashboard.range.from')}</span>
                  <Input type="date" value={from} max={to || today} onChange={(e) => setFrom(e.target.value)} className="px-2 py-1.5 text-xs" />
                </label>
                <label className="text-muted space-y-1 text-xs">
                  <span>{t('analytics.dashboard.range.to')}</span>
                  <Input type="date" value={to} min={from} max={today} onChange={(e) => setTo(e.target.value)} className="px-2 py-1.5 text-xs" />
                </label>
                {past.length > 0 && (
                  <>
                    <AnnotationSelect
                      label={t('analytics.dashboard.range.from_annotation')}
                      annotations={past}
                      selected={past.find((a) => a.date === from)}
                      onSelect={(annotation) => setFrom(annotation.date)}
                    />
                    <AnnotationSelect
                      label={t('analytics.dashboard.range.to_annotation')}
                      annotations={past.filter((a) => !from || a.date > from)}
                      selected={past.find((a) => shiftDate(a.date, -1) === to)}
                      onSelect={(annotation) => setTo(shiftDate(annotation.date, -1))}
                    />
                  </>
                )}
              </div>
              {to && from !== matchedFrom && (
                <button
                  type="button"
                  onClick={() => setFrom(matchedFrom)}
                  className="text-accent-soft-foreground mt-2 text-xs font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
                >
                  {t('analytics.dashboard.compare.match_length')}
                </button>
              )}
              {past.length > 0 && <p className="text-subtle mt-2 text-[11px] leading-snug">{t('analytics.dashboard.range.annotation_hint')}</p>}
              <Button type="submit" size="sm" className="mt-3 w-full" disabled={!from || !to}>
                {t('analytics.dashboard.compare.apply')}
              </Button>
            </form>
          </>
        )}
      </PopoverPanel>
    </Popover>
  );
}
