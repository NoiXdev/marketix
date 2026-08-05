import { Input, Select } from '@/Components/ui';
import { COUNTRIES, SUBDIVISIONS } from '@/data/countries';
import { LANGUAGES } from '@/data/languages';
import { useTranslation } from '@/lib/i18n';
import { FlaskConical, Globe, Monitor, Plus, Trash2, Type } from 'lucide-react';

// ─── Types ──────────────────────────────────────────────────────────────────

export interface GeoRule      { country: string; state: string; url: string }
export interface DeviceRule   { device: string;  url: string }
export interface LanguageRule { language: string; url: string }
export interface AbVariant    { url: string; weight: string }

const DEVICES = ['Windows', 'macOS', 'Linux', 'Android', 'iOS'];

// ─── Section wrapper ─────────────────────────────────────────────────────────

interface SectionProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  onAdd: () => void;
  addLabel: string;
  children: React.ReactNode;
}

function Section({ icon, title, description, onAdd, addLabel, children }: SectionProps) {
  return (
    <div className="rounded-[var(--radius)] border border-line bg-surface">
      <div className="flex items-center justify-between border-b border-line px-5 py-4">
        <div className="flex items-center gap-2.5">
          <span className="text-muted">{icon}</span>
          <span className="text-sm font-semibold text-foreground">{title}</span>
        </div>
        <button
          type="button"
          onClick={onAdd}
          className="inline-flex items-center gap-1.5 rounded-[var(--radius-sm)] bg-accent px-3 py-1.5 text-xs font-semibold text-accent-foreground hover:bg-accent-hover"
        >
          <Plus className="h-3.5 w-3.5" />
          {addLabel}
        </button>
      </div>
      <p className="px-5 py-3 text-xs text-muted">{description}</p>
      {children}
    </div>
  );
}

// ─── Remove button ───────────────────────────────────────────────────────────

function RemoveBtn({ onClick }: { onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="shrink-0 rounded p-1.5 text-subtle hover:bg-danger-soft hover:text-danger-foreground"
    >
      <Trash2 className="h-4 w-4" />
    </button>
  );
}

// ─── Geo Targeting ───────────────────────────────────────────────────────────

interface GeoProps {
  rules: GeoRule[];
  onChange: (rules: GeoRule[]) => void;
}

export function GeoTargeting({ rules, onChange }: GeoProps) {
  const { t } = useTranslation();
  const add = () => onChange([...rules, { country: 'US', state: '', url: '' }]);

  const update = (i: number, patch: Partial<GeoRule>) =>
    onChange(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const remove = (i: number) => onChange(rules.filter((_, idx) => idx !== i));

  return (
    <Section
      icon={<Globe className="h-4 w-4" />}
      title={t('links.targeting.geo.title')}
      description={t('links.targeting.geo.description')}
      onAdd={add}
      addLabel={t('common.actions.add')}
    >
      {rules.map((rule, i) => {
        const subdivisions = SUBDIVISIONS[rule.country] ?? [];
        return (
          <div key={i} className="border-t border-line px-5 py-4">
            <div className="flex gap-2">
              {/* Country */}
              <Select
                value={rule.country}
                onChange={(e) => update(i, { country: e.target.value, state: '' })}
                className="flex-1"
              >
                {COUNTRIES.map((c) => (
                  <option key={c.code} value={c.code}>{c.name}</option>
                ))}
              </Select>

              {/* State/Region */}
              <Select
                value={rule.state}
                onChange={(e) => update(i, { state: e.target.value })}
                disabled={subdivisions.length === 0}
                className="flex-1"
              >
                <option value="">{t('links.targeting.geo.all_regions')}</option>
                {subdivisions.map((s) => (
                  <option key={s.code} value={s.code}>{s.name}</option>
                ))}
              </Select>

              <RemoveBtn onClick={() => remove(i)} />
            </div>

            <Input
              type="url"
              value={rule.url}
              onChange={(e) => update(i, { url: e.target.value })}
              placeholder="https://example.com/redirect-url"
              className="mt-2"
            />
          </div>
        );
      })}
    </Section>
  );
}

// ─── Device Targeting ────────────────────────────────────────────────────────

interface DeviceProps {
  rules: DeviceRule[];
  onChange: (rules: DeviceRule[]) => void;
}

export function DeviceTargeting({ rules, onChange }: DeviceProps) {
  const { t } = useTranslation();
  const add = () => onChange([...rules, { device: 'Windows', url: '' }]);

  const update = (i: number, patch: Partial<DeviceRule>) =>
    onChange(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const remove = (i: number) => onChange(rules.filter((_, idx) => idx !== i));

  return (
    <Section
      icon={<Monitor className="h-4 w-4" />}
      title={t('links.targeting.device.title')}
      description={t('links.targeting.device.description')}
      onAdd={add}
      addLabel={t('common.actions.add')}
    >
      {rules.map((rule, i) => (
        <div key={i} className="border-t border-line px-5 py-4">
          <div className="flex gap-2">
            <Select
              value={rule.device}
              onChange={(e) => update(i, { device: e.target.value })}
              className="flex-1"
            >
              {DEVICES.map((d) => (
                <option key={d} value={d}>{d}</option>
              ))}
            </Select>
            <RemoveBtn onClick={() => remove(i)} />
          </div>
          <Input
            type="url"
            value={rule.url}
            onChange={(e) => update(i, { url: e.target.value })}
            placeholder="https://example.com/redirect-url"
            className="mt-2"
          />
        </div>
      ))}
    </Section>
  );
}

// ─── A/B Testing ─────────────────────────────────────────────────────────────

interface AbProps {
  defaultUrl: string;
  variants: AbVariant[];
  onChange: (variants: AbVariant[]) => void;
}

export function AbTesting({ defaultUrl, variants, onChange }: AbProps) {
  const { t } = useTranslation();
  const add = () => onChange([...variants, { url: '', weight: '' }]);

  const update = (i: number, patch: Partial<AbVariant>) =>
    onChange(variants.map((v, idx) => (idx === i ? { ...v, ...patch } : v)));

  const remove = (i: number) => onChange(variants.filter((_, idx) => idx !== i));

  // Calculate displayed weight for each row
  const allVariants = [{ url: defaultUrl, weight: '' }, ...variants];
  const n = allVariants.length;
  const explicitSum = allVariants.reduce((s, v) => s + (v.weight !== '' ? parseFloat(v.weight) || 0 : 0), 0);
  const autoCount   = allVariants.filter((v) => v.weight === '').length;
  const autoWeight  = autoCount > 0 ? Math.max(0, 100 - explicitSum) / autoCount : 0;

  const displayWeight = (w: string) =>
    w !== '' ? `${w}%` : `~${autoWeight.toFixed(1)}%`;

  return (
    <Section
      icon={<FlaskConical className="h-4 w-4" />}
      title={t('links.targeting.ab.title')}
      description={t('links.targeting.ab.description')}
      onAdd={add}
      addLabel={t('common.actions.add')}
    >
      {/* Default URL row — non-editable */}
      <div className="border-t border-line px-5 py-3">
        <div className="flex items-center gap-2">
          <span className="inline-flex items-center rounded bg-elevated px-2 py-0.5 text-xs font-semibold text-muted">
            {t('links.targeting.ab.default')}
          </span>
          <span className="flex-1 truncate text-sm text-muted">
            {defaultUrl || <span className="italic">{t('links.targeting.ab.enter_url')}</span>}
          </span>
          <span className="shrink-0 rounded bg-accent-soft px-2 py-0.5 text-xs font-semibold text-accent-soft-foreground">
            {displayWeight('')}
          </span>
        </div>
      </div>

      {variants.map((variant, i) => (
        <div key={i} className="border-t border-line px-5 py-4">
          <div className="flex gap-2">
            <Input
              type="url"
              value={variant.url}
              onChange={(e) => update(i, { url: e.target.value })}
              placeholder="https://example.com/variant-b"
              className="flex-1"
            />
            {/* Weight input */}
            <div className="relative w-24 shrink-0">
              <Input
                type="number"
                min="0"
                max="100"
                step="1"
                value={variant.weight}
                onChange={(e) => update(i, { weight: e.target.value })}
                placeholder="Auto"
                className="pr-6 text-right"
              />
              <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-subtle">
                %
              </span>
            </div>
            <RemoveBtn onClick={() => remove(i)} />
          </div>
          {variant.weight === '' && (
            <p className="mt-1 text-xs text-subtle">
              {t('links.targeting.ab.auto_weight', { weight: displayWeight(variant.weight) })}
            </p>
          )}
        </div>
      ))}

      {n > 1 && (
        <div className="border-t border-line px-5 py-2.5">
          <p className="text-xs text-subtle">
            {autoCount > 0
              ? t('links.targeting.ab.summary_auto', {
                  variants: String(n),
                  total: explicitSum.toFixed(0),
                  auto: String(autoCount),
                })
              : t('links.targeting.ab.summary', {
                  variants: String(n),
                  total: explicitSum.toFixed(0),
                })
            }
          </p>
        </div>
      )}
    </Section>
  );
}

// ─── Language Targeting ──────────────────────────────────────────────────────

interface LanguageProps {
  rules: LanguageRule[];
  onChange: (rules: LanguageRule[]) => void;
}

export function LanguageTargeting({ rules, onChange }: LanguageProps) {
  const { t } = useTranslation();
  const add = () => onChange([...rules, { language: 'en', url: '' }]);

  const update = (i: number, patch: Partial<LanguageRule>) =>
    onChange(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const remove = (i: number) => onChange(rules.filter((_, idx) => idx !== i));

  return (
    <Section
      icon={<Type className="h-4 w-4" />}
      title={t('links.targeting.language.title')}
      description={t('links.targeting.language.description')}
      onAdd={add}
      addLabel={t('common.actions.add')}
    >
      {rules.map((rule, i) => (
        <div key={i} className="border-t border-line px-5 py-4">
          <div className="flex gap-2">
            <Select
              value={rule.language}
              onChange={(e) => update(i, { language: e.target.value })}
              className="flex-1"
            >
              {LANGUAGES.map((l) => (
                <option key={l.code} value={l.code}>{l.name}</option>
              ))}
            </Select>
            <RemoveBtn onClick={() => remove(i)} />
          </div>
          <Input
            type="url"
            value={rule.url}
            onChange={(e) => update(i, { url: e.target.value })}
            placeholder="https://example.com/redirect-url"
            className="mt-2"
          />
        </div>
      ))}
    </Section>
  );
}
