import { Button, Field, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { WIDGET_DEFS, type Widget as W, type WidgetConfig } from '@/lib/widgets/schema';
import { X } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const DAYS_OPTIONS = [7, 30, 90, 365];
const METRIC_OPTIONS = ['clicks', 'unique_visitors', 'active_links', 'avg_per_link'];
const DIMENSION_OPTIONS = ['links', 'countries', 'cities', 'browsers', 'os', 'referrers'];

type FieldName = 'title' | 'days' | 'metric' | 'dimension' | 'limit';

const FIELDS_BY_TYPE: Record<W['type'], FieldName[]> = {
  kpi: ['title', 'metric', 'days'],
  timeseries: ['title', 'days'],
  top_list: ['title', 'dimension', 'limit', 'days'],
  geo_map: ['title', 'days'],
  activity: ['title', 'limit'],
  quick_actions: ['title'],
};

interface Props {
  widget: W;
  onSave: (config: WidgetConfig) => void;
  onClose: () => void;
}

export default function WidgetConfigForm({ widget, onSave, onClose }: Props) {
  const { t } = useTranslation();
  const def = WIDGET_DEFS[widget.type].defaultConfig;
  const fields = FIELDS_BY_TYPE[widget.type];

  const [title, setTitle] = useState(widget.config.title ?? '');
  const [days, setDays] = useState(widget.config.days ?? def.days ?? 30);
  const [metric, setMetric] = useState(widget.config.metric ?? def.metric ?? 'clicks');
  const [dimension, setDimension] = useState(widget.config.dimension ?? def.dimension ?? 'links');
  const [limit, setLimit] = useState(widget.config.limit ?? def.limit ?? 5);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const config: WidgetConfig = { title: title.trim() ? title.trim() : null };
    if (fields.includes('days')) config.days = Number(days);
    if (fields.includes('metric')) config.metric = metric;
    if (fields.includes('dimension')) config.dimension = dimension;
    if (fields.includes('limit')) config.limit = Math.min(20, Math.max(1, Number(limit)));
    onSave(config);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        aria-label={t('widgets.configure')}
        className="w-full max-w-sm rounded-[var(--radius)] border border-line bg-surface p-4 shadow-[var(--shadow)]"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-foreground">{t('widgets.configure')}</h2>
          <button type="button" onClick={onClose} aria-label={t('common.actions.close')} className="rounded p-1 text-subtle hover:bg-elevated hover:text-foreground">
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3">
          <Field label={t('widgets.config.title')} htmlFor="widget-title">
            <Input id="widget-title" value={title} onChange={(e) => setTitle(e.target.value)} maxLength={60} placeholder={t(WIDGET_DEFS[widget.type].labelKey)} />
          </Field>

          {fields.includes('metric') && (
            <Field label={t('widgets.config.metric')} htmlFor="widget-metric">
              <Select id="widget-metric" value={metric} onChange={(e) => setMetric(e.target.value)}>
                {METRIC_OPTIONS.map((m) => (
                  <option key={m} value={m}>
                    {t(`widgets.metric.${m}`)}
                  </option>
                ))}
              </Select>
            </Field>
          )}

          {fields.includes('dimension') && (
            <Field label={t('widgets.config.dimension')} htmlFor="widget-dimension">
              <Select id="widget-dimension" value={dimension} onChange={(e) => setDimension(e.target.value)}>
                {DIMENSION_OPTIONS.map((d) => (
                  <option key={d} value={d}>
                    {t(`widgets.dimension.${d}`)}
                  </option>
                ))}
              </Select>
            </Field>
          )}

          {fields.includes('limit') && (
            <Field label={t('widgets.config.limit')} htmlFor="widget-limit">
              <Input id="widget-limit" type="number" min={1} max={20} value={limit} onChange={(e) => setLimit(Number(e.target.value))} />
            </Field>
          )}

          {fields.includes('days') && (
            <Field label={t('widgets.config.days')} htmlFor="widget-days">
              <Select id="widget-days" value={days} onChange={(e) => setDays(Number(e.target.value))}>
                {DAYS_OPTIONS.map((d) => (
                  <option key={d} value={d}>
                    {t('widgets.config.days_value', { days: d })}
                  </option>
                ))}
              </Select>
            </Field>
          )}

          <div className="mt-4 flex justify-end gap-2">
            <Button type="button" variant="secondary" size="sm" onClick={onClose}>
              {t('common.actions.cancel')}
            </Button>
            <Button type="submit" size="sm">
              {t('common.actions.save')}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
