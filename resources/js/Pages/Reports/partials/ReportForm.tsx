import { Button, Checkbox, ErrorSummary, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Link, SetDataAction } from '@inertiajs/react';
import { X } from 'lucide-react';
import { FormEvent, KeyboardEvent, useState } from 'react';

export interface ReportFormData {
  name: string;
  type: string;
  subject_id: string | null;
  frequency: string;
  weekday: number | null;
  day_of_month: number | null;
  period: string;
  formats: { csv: boolean; pdf: boolean };
  recipients: string[];
  active: boolean;
}

type LinkOption = { id: string; slug: string };
type SiteOption = { id: string; name: string };

interface ReportFormErrors {
  name?: string;
  type?: string;
  subject_id?: string;
  frequency?: string;
  weekday?: string;
  day_of_month?: string;
  period?: string;
  formats?: string;
  recipients?: string;
  active?: string;
  [key: string]: string | undefined;
}

interface ReportFormProps {
  data: ReportFormData;
  setData: SetDataAction<ReportFormData>;
  errors: ReportFormErrors;
  processing: boolean;
  submitLabel: string;
  cancelHref: string;
  links: LinkOption[];
  sites: SiteOption[];
  types: string[];
  locale: string;
  onSubmit: (e: FormEvent) => void;
}

const PERIOD_OPTIONS = [
  { value: 'last_7_days', labelKey: 'period_last_7' },
  { value: 'last_30_days', labelKey: 'period_last_30' },
  { value: 'last_90_days', labelKey: 'period_last_90' },
  { value: 'previous_month', labelKey: 'period_previous_month' },
];

const FREQUENCY_OPTIONS = ['daily', 'weekly', 'monthly'];

const DAYS_OF_MONTH = Array.from({ length: 28 }, (_, i) => i + 1);

/** 0=Sunday..6=Saturday, matching the backend's `weekday` column. */
function weekdayLabel(index: number, locale: string): string {
  // 2024-01-07 is a Sunday — used purely as a stable reference date.
  const date = new Date(Date.UTC(2024, 0, 7 + index));
  return new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(date);
}

function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function RecipientsInput({
  value,
  onChange,
  error,
}: {
  value: string[];
  onChange: (next: string[]) => void;
  error?: string;
}) {
  const [draft, setDraft] = useState('');

  function commit() {
    const candidate = draft.trim().replace(/,$/, '');
    setDraft('');
    if (!candidate) return;
    if (value.includes(candidate)) return;
    onChange([...value, candidate]);
  }

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      commit();
    } else if (e.key === 'Backspace' && draft === '' && value.length > 0) {
      onChange(value.slice(0, -1));
    }
  }

  function remove(email: string) {
    onChange(value.filter((r) => r !== email));
  }

  return (
    <div>
      <div className="flex flex-wrap gap-2 rounded-[var(--radius-sm)] border border-line-strong bg-surface p-2">
        {value.map((email) => (
          <span
            key={email}
            className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ${
              isValidEmail(email) ? 'bg-neutral-soft text-neutral-foreground' : 'bg-danger-soft text-danger-foreground'
            }`}
          >
            {email}
            <button
              type="button"
              onClick={() => remove(email)}
              className="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              aria-label={`Remove ${email}`}
            >
              <X className="h-3 w-3" />
            </button>
          </span>
        ))}
        <input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={onKeyDown}
          onBlur={commit}
          className="min-w-[10ch] flex-1 border-0 bg-transparent p-1 text-sm text-foreground outline-none placeholder:text-subtle"
        />
      </div>
      {error && <p className="mt-1.5 text-xs text-danger-foreground">{error}</p>}
    </div>
  );
}

export default function ReportForm({
  data,
  setData,
  errors,
  processing,
  submitLabel,
  cancelHref,
  links,
  sites,
  types,
  locale,
  onSubmit,
}: ReportFormProps) {
  const { t } = useTranslation();

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  function handleTypeChange(type: string) {
    setData((prev) => ({ ...prev, type, subject_id: type === 'project_summary' ? null : '' }));
  }

  function handleFrequencyChange(frequency: string) {
    setData((prev) => ({
      ...prev,
      frequency,
      weekday: frequency === 'weekly' ? (prev.weekday ?? 1) : null,
      day_of_month: frequency === 'monthly' ? (prev.day_of_month ?? 1) : null,
    }));
  }

  return (
    <form onSubmit={onSubmit} className="space-y-5">
      <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

      <FormSection>
        <Field label={t('reports.form.name')} htmlFor="name" error={errors.name}>
          <Input
            id="name"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            placeholder={t('reports.form.name_placeholder')}
          />
        </Field>

        <Field label={t('reports.form.type')} htmlFor="type">
          <Select id="type" value={data.type} onChange={(e) => handleTypeChange(e.target.value)}>
            {types.map((type) => (
              <option key={type} value={type}>
                {t(`reports.types.${type}`)}
              </option>
            ))}
          </Select>
        </Field>

        {data.type === 'link' && (
          <Field label={t('reports.form.subject')} htmlFor="subject_id" error={errors.subject_id}>
            <Select id="subject_id" value={data.subject_id ?? ''} onChange={(e) => setData('subject_id', e.target.value)}>
              <option value="">{t('reports.form.subject_link')}</option>
              {links.map((link) => (
                <option key={link.id} value={link.id}>
                  /{link.slug}
                </option>
              ))}
            </Select>
          </Field>
        )}

        {data.type === 'site_analytics' && (
          <Field label={t('reports.form.subject')} htmlFor="subject_id" error={errors.subject_id}>
            <Select id="subject_id" value={data.subject_id ?? ''} onChange={(e) => setData('subject_id', e.target.value)}>
              <option value="">{t('reports.form.subject_site')}</option>
              {sites.map((site) => (
                <option key={site.id} value={site.id}>
                  {site.name}
                </option>
              ))}
            </Select>
          </Field>
        )}
      </FormSection>

      <FormSection>
        <Field label={t('reports.form.frequency')} htmlFor="frequency">
          <Select id="frequency" value={data.frequency} onChange={(e) => handleFrequencyChange(e.target.value)}>
            {FREQUENCY_OPTIONS.map((freq) => (
              <option key={freq} value={freq}>
                {t(`reports.frequencies.${freq}`)}
              </option>
            ))}
          </Select>
        </Field>

        {data.frequency === 'weekly' && (
          <Field label={t('reports.form.weekday')} htmlFor="weekday" error={errors.weekday}>
            <Select
              id="weekday"
              value={data.weekday ?? 1}
              onChange={(e) => setData('weekday', Number(e.target.value))}
            >
              {Array.from({ length: 7 }, (_, i) => i).map((day) => (
                <option key={day} value={day}>
                  {weekdayLabel(day, locale)}
                </option>
              ))}
            </Select>
          </Field>
        )}

        {data.frequency === 'monthly' && (
          <Field label={t('reports.form.day_of_month')} htmlFor="day_of_month" error={errors.day_of_month}>
            <Select
              id="day_of_month"
              value={data.day_of_month ?? 1}
              onChange={(e) => setData('day_of_month', Number(e.target.value))}
            >
              {DAYS_OF_MONTH.map((day) => (
                <option key={day} value={day}>
                  {day}
                </option>
              ))}
            </Select>
          </Field>
        )}

        <Field label={t('reports.form.period')} htmlFor="period">
          <Select id="period" value={data.period} onChange={(e) => setData('period', e.target.value)}>
            {PERIOD_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {t(`reports.form.${opt.labelKey}`)}
              </option>
            ))}
          </Select>
        </Field>
      </FormSection>

      <FormSection title={t('reports.form.formats')}>
        <label className="flex items-center gap-2 text-sm text-foreground">
          <Checkbox
            checked={data.formats.csv}
            onChange={(e) => setData('formats', { ...data.formats, csv: e.target.checked })}
          />
          {t('reports.form.format_csv')}
        </label>
        <label className="flex items-center gap-2 text-sm text-foreground">
          <Checkbox
            checked={data.formats.pdf}
            onChange={(e) => setData('formats', { ...data.formats, pdf: e.target.checked })}
          />
          {t('reports.form.format_pdf')}
        </label>
        {(errors['formats.csv'] || errors['formats.pdf'] || errors.formats) && (
          <p className="text-xs text-danger-foreground">
            {errors['formats.csv'] || errors['formats.pdf'] || errors.formats}
          </p>
        )}
      </FormSection>

      <FormSection>
        <Field label={t('reports.form.recipients')} hint={data.recipients.length === 0 ? t('reports.form.recipients_hint') : undefined}>
          <RecipientsInput
            value={data.recipients}
            onChange={(next) => setData('recipients', next)}
            error={errors.recipients}
          />
        </Field>

        <label className="flex items-center gap-2 text-sm text-foreground">
          <Checkbox checked={data.active} onChange={(e) => setData('active', e.target.checked)} />
          {t('reports.form.active')}
        </label>
      </FormSection>

      <div className="flex items-center gap-3">
        <Button type="submit" loading={processing}>
          {submitLabel}
        </Button>
        <Link href={cancelHref} className="text-sm text-muted transition-colors hover:text-foreground">
          {t('common.actions.cancel')}
        </Link>
      </div>
    </form>
  );
}
