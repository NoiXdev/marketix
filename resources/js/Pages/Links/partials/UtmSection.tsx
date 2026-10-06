import { Field, FormSection, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Megaphone } from 'lucide-react';

export type UtmKey = 'source' | 'medium' | 'campaign' | 'term' | 'content';
export type UtmParams = Partial<Record<UtmKey, string | null>>;

const KEYS: UtmKey[] = ['source', 'medium', 'campaign', 'term', 'content'];

/**
 * Mirrors App\Support\UtmTagger::apply() for the preview: parameters the
 * target already carries win, query string and fragment stay as they are.
 */
export function applyUtm(target: string, utm: UtmParams): string | null {
  if (!/^https?:\/\//i.test(target.trim())) return null;

  const [base, fragment] = target.trim().split(/#(.*)/s);
  const existing = new URLSearchParams(base.includes('?') ? base.slice(base.indexOf('?') + 1) : '');
  const add = KEYS.flatMap((key) => {
    const value = utm[key]?.trim();
    return value && !existing.has(`utm_${key}`) ? [`utm_${key}=${encodeURIComponent(value)}`] : [];
  });
  if (add.length === 0) return null;

  const separator = !base.includes('?') ? '?' : base.endsWith('?') || base.endsWith('&') ? '' : '&';
  return `${base}${separator}${add.join('&')}${fragment !== undefined ? `#${fragment}` : ''}`;
}

interface UtmSectionProps {
  utm: UtmParams;
  onChange: (utm: UtmParams) => void;
  target: string;
  errors: Record<string, string | undefined>;
}

export default function UtmSection({ utm, onChange, target, errors }: UtmSectionProps) {
  const { t } = useTranslation();
  const preview = applyUtm(target, utm);

  return (
    <FormSection
      title={
        <span className="flex items-center gap-2">
          <Megaphone className="text-muted h-4 w-4" />
          {t('links.utm.title')}
        </span>
      }
      description={t('links.utm.description')}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        {KEYS.map((key) => (
          <div key={key} className={key === 'campaign' ? 'sm:col-span-2' : undefined}>
            <Field label={t(`links.utm.fields.${key}`)} htmlFor={`utm-${key}`} error={errors[`utm.${key}`]}>
              <Input
                id={`utm-${key}`}
                type="text"
                value={utm[key] ?? ''}
                onChange={(e) => onChange({ ...utm, [key]: e.target.value })}
                placeholder={t(`links.utm.placeholders.${key}`)}
              />
            </Field>
          </div>
        ))}
      </div>

      {preview && (
        <p className="text-muted mt-3 text-xs">
          {t('links.utm.preview')} <code className="text-foreground font-mono break-all">{preview}</code>
        </p>
      )}
    </FormSection>
  );
}
