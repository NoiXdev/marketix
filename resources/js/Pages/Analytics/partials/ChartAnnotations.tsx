import { IconButton } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import { bucketOf, formatDate, Interval, laterDate, shiftDate, toIsoDate } from '@/Pages/Analytics/partials/period';
import { Annotation } from '@/Pages/Analytics/types';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Flag, GitCompareArrows, MoveRight, Pencil } from 'lucide-react';
import { ReactNode } from 'react';

export type AnnotationGroups = Map<number, Annotation[]>;

const PIN_SIZE = 20;
const TOOLTIP_LIMIT = 3;
const ANNOTATION_COLOR = 'var(--chart-annotation)';

const linkCls =
  'text-accent-soft-foreground inline-flex items-center gap-1 text-xs font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none';

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

export function useAnnotationGroups(dates: string[], interval: Interval): AnnotationGroups {
  const { annotations, period } = useAnalytics();
  const groups: AnnotationGroups = new Map();

  annotations.forEach((annotation) => {
    if (annotation.date < period.from || annotation.date > period.to) return;
    const index = dates.indexOf(bucketOf(annotation.date, interval));
    if (index >= 0) groups.set(index, [...(groups.get(index) ?? []), annotation]);
  });

  return groups;
}

export function useAnnotationActions() {
  const { period, go } = useAnalytics();
  const today = laterDate(toIsoDate(new Date()), period.to);

  return {
    reachable: (annotation: Annotation) => annotation.date <= today,
    since: (annotation: Annotation) => go({ range: 'custom', from: annotation.date, to: today, ...(period.compare === 'custom' ? { compare: 'previous' as const } : {}) }),
    beforeAfter: (annotation: Annotation) =>
      go({ range: 'custom', from: annotation.date, to: today, compare: 'custom', compare_from: null, compare_to: shiftDate(annotation.date, -1) }),
  };
}

export function AnnotationDetails({ annotation, actions, onNavigate }: { annotation: Annotation; actions?: ReactNode; onNavigate?: () => void }) {
  const { t, locale } = useTranslation();
  const { reachable, since, beforeAfter } = useAnnotationActions();
  const past = reachable(annotation);

  function run(action: (annotation: Annotation) => void) {
    onNavigate?.();
    action(annotation);
  }

  return (
    <div className="min-w-0">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-muted text-xs font-semibold">
            {formatDate(annotation.date, locale)}
            {!past && <span className="bg-elevated text-muted ml-2 rounded px-1.5 py-0.5 text-[11px]">{t('analytics.dashboard.annotations.future')}</span>}
          </p>
          <p className="text-foreground mt-0.5 text-sm font-semibold break-words">{annotation.title}</p>
        </div>
        {actions && <div className="-mt-1 -mr-1.5 flex shrink-0">{actions}</div>}
      </div>
      {annotation.description && <p className="text-muted mt-1 text-xs leading-relaxed break-words whitespace-pre-line">{annotation.description}</p>}
      {annotation.author && <p className="text-subtle mt-1 text-[11px]">{t('analytics.dashboard.annotations.by', { name: annotation.author })}</p>}
      {past && (
        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
          <button type="button" className={linkCls} onClick={() => run(since)}>
            <MoveRight className="h-3.5 w-3.5" />
            {t('analytics.dashboard.annotations.since')}
          </button>
          <button type="button" className={linkCls} title={t('analytics.dashboard.annotations.before_after_hint')} onClick={() => run(beforeAfter)}>
            <GitCompareArrows className="h-3.5 w-3.5" />
            {t('analytics.dashboard.annotations.before_after')}
          </button>
        </div>
      )}
    </div>
  );
}

export function AnnotationsButton() {
  const { t } = useTranslation();
  const { annotations, manageAnnotations } = useAnalytics();

  return (
    <button
      type="button"
      onClick={() => manageAnnotations(annotations.length > 0 ? 'list' : 'new')}
      className="text-accent-soft-foreground inline-flex items-center gap-1 text-xs font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
    >
      <Flag className="h-3.5 w-3.5" />
      {t('analytics.dashboard.annotations.button')}
      {annotations.length > 0 && <span className="text-muted font-medium">{annotations.length}</span>}
    </button>
  );
}

export function AnnotationRail({ groups, x, width }: { groups: AnnotationGroups; x: (index: number) => number; width: number }) {
  const { t } = useTranslation();
  const { manageAnnotations } = useAnalytics();

  if (groups.size === 0 || width <= 0) return null;

  const pins: { left: number; items: Annotation[] }[] = [];
  [...groups.entries()]
    .sort(([a], [b]) => a - b)
    .forEach(([index, items]) => {
      const left = x(index);
      const last = pins[pins.length - 1];
      if (last && left - last.left < PIN_SIZE) last.items.push(...items);
      else pins.push({ left, items: [...items] });
    });

  return (
    <div className="relative mb-1 h-5">
      {pins.map(({ left, items }) => {
        const titles = items.map((annotation) => annotation.title);
        return (
          <Popover key={items[0].id} className="absolute top-0 -translate-x-1/2" style={{ left: clamp(left, PIN_SIZE / 2, width - PIN_SIZE / 2) }}>
            <PopoverButton
              aria-label={t('analytics.dashboard.annotations.show', { title: titles.join(', ') })}
              title={titles.join('\n')}
              className="grid h-5 min-w-5 place-items-center rounded-full px-1 text-[10px] leading-none font-bold text-[color:var(--surface)] shadow-[var(--shadow-sm)] transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none data-[open]:scale-110"
              style={{ background: ANNOTATION_COLOR }}
            >
              {items.length > 1 ? items.length : <Flag className="h-3 w-3" strokeWidth={2.5} />}
            </PopoverButton>
            <PopoverPanel
              anchor="bottom"
              className="border-line bg-surface divide-line z-30 max-h-96 w-72 divide-y overflow-y-auto rounded-lg border shadow-[var(--shadow)] [--anchor-gap:6px] focus:outline-none"
            >
              {({ close }) => (
                <>
                  {items.map((annotation) => (
                    <div key={annotation.id} className="px-3 py-2.5">
                      <AnnotationDetails
                        annotation={annotation}
                        onNavigate={close}
                        actions={
                          <IconButton
                            icon={Pencil}
                            label={t('common.actions.edit')}
                            onClick={() => {
                              close();
                              manageAnnotations(annotation);
                            }}
                          />
                        }
                      />
                    </div>
                  ))}
                </>
              )}
            </PopoverPanel>
          </Popover>
        );
      })}
    </div>
  );
}

export function AnnotationLines({ groups, x }: { groups: AnnotationGroups; x: (index: number) => number }) {
  return (
    <>
      {[...groups.keys()].map((index) => (
        <span
          key={index}
          aria-hidden
          className="pointer-events-none absolute inset-y-0 z-10 w-0 border-l border-dashed opacity-70"
          style={{ left: x(index), borderColor: ANNOTATION_COLOR }}
        />
      ))}
    </>
  );
}

export function AnnotationTooltipNote({ items }: { items?: Annotation[] }) {
  const { t } = useTranslation();

  if (!items?.length) return null;

  return (
    <div className="border-line mt-1.5 max-w-64 space-y-0.5 border-t pt-1.5">
      {items.slice(0, TOOLTIP_LIMIT).map((annotation) => (
        <p key={annotation.id} className="text-foreground flex items-start gap-1.5 font-semibold">
          <Flag className="mt-px h-3 w-3 shrink-0" style={{ color: ANNOTATION_COLOR }} />
          <span className="break-words">{annotation.title}</span>
        </p>
      ))}
      {items.length > TOOLTIP_LIMIT && <p className="text-muted pl-[18px]">{t('analytics.dashboard.annotations.more', { count: items.length - TOOLTIP_LIMIT })}</p>}
    </div>
  );
}

export function AnnotationLabels({ items }: { items?: Annotation[] }) {
  return (
    <>
      {items?.map((annotation) => (
        <span key={annotation.id} className="text-foreground ml-2 inline-flex items-center gap-1 text-xs font-semibold">
          <Flag className="h-3 w-3" style={{ color: ANNOTATION_COLOR }} />
          {annotation.title}
        </span>
      ))}
    </>
  );
}
