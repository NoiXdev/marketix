import { COUNTRIES, SUBDIVISIONS } from '@/data/countries';
import { LANGUAGES } from '@/data/languages';
import { FlaskConical, Globe, Monitor, Plus, Trash2, Type } from 'lucide-react';

// ─── Types ──────────────────────────────────────────────────────────────────

export interface GeoRule      { country: string; state: string; url: string }
export interface DeviceRule   { device: string;  url: string }
export interface LanguageRule { language: string; url: string }
export interface AbVariant    { url: string; weight: string }

const DEVICES = ['Windows', 'macOS', 'Linux', 'Android', 'iOS'];

// ─── Shared input styles ─────────────────────────────────────────────────────

const sel = 'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const inp = sel;

// ─── Section wrapper ─────────────────────────────────────────────────────────

interface SectionProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  onAdd: () => void;
  children: React.ReactNode;
}

function Section({ icon, title, description, onAdd, children }: SectionProps) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
        <div className="flex items-center gap-2.5">
          <span className="text-slate-500 dark:text-slate-400">{icon}</span>
          <span className="text-sm font-semibold text-slate-800 dark:text-slate-200">{title}</span>
        </div>
        <button
          type="button"
          onClick={onAdd}
          className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
        >
          <Plus className="h-3.5 w-3.5" />
          Add
        </button>
      </div>
      <p className="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">{description}</p>
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
      className="shrink-0 rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
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
  const add = () => onChange([...rules, { country: 'US', state: '', url: '' }]);

  const update = (i: number, patch: Partial<GeoRule>) =>
    onChange(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const remove = (i: number) => onChange(rules.filter((_, idx) => idx !== i));

  return (
    <Section
      icon={<Globe className="h-4 w-4" />}
      title="Geo Targeting"
      description="Redirect visitors to a different URL based on their country or state/region."
      onAdd={add}
    >
      {rules.map((rule, i) => {
        const subdivisions = SUBDIVISIONS[rule.country] ?? [];
        return (
          <div key={i} className="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
            <div className="flex gap-2">
              {/* Country */}
              <select
                value={rule.country}
                onChange={(e) => update(i, { country: e.target.value, state: '' })}
                className={sel + ' flex-1'}
              >
                {COUNTRIES.map((c) => (
                  <option key={c.code} value={c.code}>{c.name}</option>
                ))}
              </select>

              {/* State/Region */}
              <select
                value={rule.state}
                onChange={(e) => update(i, { state: e.target.value })}
                disabled={subdivisions.length === 0}
                className={sel + ' flex-1 disabled:opacity-50'}
              >
                <option value="">All regions</option>
                {subdivisions.map((s) => (
                  <option key={s.code} value={s.code}>{s.name}</option>
                ))}
              </select>

              <RemoveBtn onClick={() => remove(i)} />
            </div>

            <input
              type="url"
              value={rule.url}
              onChange={(e) => update(i, { url: e.target.value })}
              placeholder="https://example.com/redirect-url"
              className={inp + ' mt-2'}
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
  const add = () => onChange([...rules, { device: 'Windows', url: '' }]);

  const update = (i: number, patch: Partial<DeviceRule>) =>
    onChange(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const remove = (i: number) => onChange(rules.filter((_, idx) => idx !== i));

  return (
    <Section
      icon={<Monitor className="h-4 w-4" />}
      title="Device Targeting"
      description="Redirect visitors to a different URL based on their operating system or device."
      onAdd={add}
    >
      {rules.map((rule, i) => (
        <div key={i} className="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
          <div className="flex gap-2">
            <select
              value={rule.device}
              onChange={(e) => update(i, { device: e.target.value })}
              className={sel + ' flex-1'}
            >
              {DEVICES.map((d) => (
                <option key={d} value={d}>{d}</option>
              ))}
            </select>
            <RemoveBtn onClick={() => remove(i)} />
          </div>
          <input
            type="url"
            value={rule.url}
            onChange={(e) => update(i, { url: e.target.value })}
            placeholder="https://example.com/redirect-url"
            className={inp + ' mt-2'}
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
      title="A/B Testing"
      description="Rotate traffic across multiple destination URLs. The default link is always included. Geo, device, and language targeting take priority over A/B rotation."
      onAdd={add}
    >
      {/* Default URL row — non-editable */}
      <div className="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
        <div className="flex items-center gap-2">
          <span className="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            Default
          </span>
          <span className="flex-1 truncate text-sm text-slate-500 dark:text-slate-400">
            {defaultUrl || <span className="italic">Enter destination URL above</span>}
          </span>
          <span className="shrink-0 rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400">
            {displayWeight('')}
          </span>
        </div>
      </div>

      {variants.map((variant, i) => (
        <div key={i} className="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
          <div className="flex gap-2">
            <input
              type="url"
              value={variant.url}
              onChange={(e) => update(i, { url: e.target.value })}
              placeholder="https://example.com/variant-b"
              className={inp + ' flex-1'}
            />
            {/* Weight input */}
            <div className="relative w-24 shrink-0">
              <input
                type="number"
                min="0"
                max="100"
                step="1"
                value={variant.weight}
                onChange={(e) => update(i, { weight: e.target.value })}
                placeholder="Auto"
                className={inp + ' pr-6 text-right'}
              />
              <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">
                %
              </span>
            </div>
            <RemoveBtn onClick={() => remove(i)} />
          </div>
          {variant.weight === '' && (
            <p className="mt-1 text-xs text-slate-400">
              Auto weight: {displayWeight(variant.weight)}
            </p>
          )}
        </div>
      ))}

      {n > 1 && (
        <div className="border-t border-slate-100 px-5 py-2.5 dark:border-slate-800">
          <p className="text-xs text-slate-400">
            {n} variants · total explicit weight: {explicitSum.toFixed(0)}%
            {autoCount > 0 && ` · ${autoCount} auto-weighted`}
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
  const add = () => onChange([...rules, { language: 'en', url: '' }]);

  const update = (i: number, patch: Partial<LanguageRule>) =>
    onChange(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const remove = (i: number) => onChange(rules.filter((_, idx) => idx !== i));

  return (
    <Section
      icon={<Type className="h-4 w-4" />}
      title="Language Targeting"
      description="Redirect visitors to a different URL based on their browser language."
      onAdd={add}
    >
      {rules.map((rule, i) => (
        <div key={i} className="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
          <div className="flex gap-2">
            <select
              value={rule.language}
              onChange={(e) => update(i, { language: e.target.value })}
              className={sel + ' flex-1'}
            >
              {LANGUAGES.map((l) => (
                <option key={l.code} value={l.code}>{l.name}</option>
              ))}
            </select>
            <RemoveBtn onClick={() => remove(i)} />
          </div>
          <input
            type="url"
            value={rule.url}
            onChange={(e) => update(i, { url: e.target.value })}
            placeholder="https://example.com/redirect-url"
            className={inp + ' mt-2'}
          />
        </div>
      ))}
    </Section>
  );
}
