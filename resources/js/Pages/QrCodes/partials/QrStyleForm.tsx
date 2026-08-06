import { Checkbox, Input } from '@/Components/ui';
import { QrIcon, QR_ICONS, iconToDataUrl } from '@/data/qrIcons';
import {
  EyeBallMode,
  EyeFrameMode,
  FrameStyle,
  LogoType,
  ModuleMode,
  QrEcc,
  QrGradient,
  QrStyle,
} from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { Upload, X } from 'lucide-react';
import { useRef } from 'react';
import QrScannability from './QrScannability';

interface Props {
  style: QrStyle;
  onChange: (style: QrStyle) => void;
}

type Translate = (key: string, replacements?: Record<string, string | number>) => string;

// ── Option button ─────────────────────────────────────────────────────────────

function Opt({ label, active, onClick, children }: {
  label: string; active: boolean; onClick: () => void; children?: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={label}
      className={`flex flex-col items-center gap-1 rounded-lg border p-2 text-xs transition-colors ${
        active
          ? 'border-accent bg-accent-soft text-accent-soft-foreground'
          : 'border-line text-muted hover:border-line-strong'
      }`}
    >
      {children}
      <span>{label}</span>
    </button>
  );
}

// ── Labelled range slider ───────────────────────────────────────────────────────

function RangeRow({ label, value, onChange, min = 0, max = 100, step = 1, format, disabled }: {
  label: string;
  value: number;
  onChange: (val: number) => void;
  min?: number;
  max?: number;
  step?: number;
  format?: (val: number) => string;
  disabled?: boolean;
}) {
  return (
    <div className={disabled ? 'opacity-50' : undefined}>
      <div className="mb-1 flex items-center justify-between">
        <span className="text-xs text-subtle">{label}</span>
        <span className="text-xs font-semibold text-foreground">{format ? format(value) : value}</span>
      </div>
      <input
        type="range" min={min} max={max} step={step} value={value} disabled={disabled}
        onChange={(e) => onChange(Number(e.target.value))}
        className="w-full accent-[color:var(--accent)] disabled:cursor-not-allowed"
      />
    </div>
  );
}

// ── Gradient controls (fg/bg) ────────────────────────────────────────────────────

function GradientControl({ style, onChange, gradientKey, solidKey, t }: {
  style: QrStyle;
  onChange: (style: QrStyle) => void;
  gradientKey: 'fg_gradient' | 'bg_gradient';
  solidKey: 'foreground' | 'background';
  t: Translate;
}) {
  const gradient = style[gradientKey];
  const mode: 'none' | QrGradient['type'] = gradient?.type ?? 'none';

  function setMode(next: 'none' | QrGradient['type']) {
    if (next === 'none') {
      onChange({ ...style, [gradientKey]: undefined });
      return;
    }
    const seed = style[solidKey] === 'transparent' ? '#ffffff' : style[solidKey];
    const nextGradient: QrGradient = gradient
      ? { ...gradient, type: next }
      : { type: next, rotation: 90, stops: [{ offset: 0, color: seed }, { offset: 1, color: seed }] };
    onChange({ ...style, [gradientKey]: nextGradient });
  }

  function setStop(index: 0 | 1, color: string) {
    if (!gradient) return;
    const stops = gradient.stops.map((s, i) => (i === index ? { ...s, color } : s));
    onChange({ ...style, [gradientKey]: { ...gradient, stops } });
  }

  function setRotation(rotation: number) {
    if (!gradient) return;
    onChange({ ...style, [gradientKey]: { ...gradient, rotation } });
  }

  return (
    <div className="mt-2">
      <div className="flex rounded-lg border border-line bg-elevated p-0.5 text-xs">
        {(['none', 'linear', 'radial'] as const).map((m) => (
          <button
            key={m}
            type="button"
            onClick={() => setMode(m)}
            className={`flex-1 rounded-md py-1 transition-colors ${
              mode === m
                ? 'bg-surface font-semibold text-foreground shadow-[var(--shadow-sm)]'
                : 'text-muted hover:text-foreground'
            }`}
          >
            {t(`qr.style.gradient_${m}`)}
          </button>
        ))}
      </div>
      {gradient && (
        <div className="mt-2 space-y-2">
          {gradient.type === 'linear' && (
            <RangeRow
              label={t('qr.style.gradient_rotation')}
              value={gradient.rotation}
              onChange={setRotation}
              min={0}
              max={360}
              step={15}
              format={(v) => `${v}°`}
            />
          )}
          <div className="flex gap-3">
            <label className="flex-1">
              <span className="mb-1 block text-xs text-muted">{t('qr.style.gradient_from')}</span>
              <input
                type="color"
                value={gradient.stops[0]?.color ?? '#000000'}
                onChange={(e) => setStop(0, e.target.value)}
                className="h-9 w-full cursor-pointer rounded border border-line-strong p-0.5"
              />
            </label>
            <label className="flex-1">
              <span className="mb-1 block text-xs text-muted">{t('qr.style.gradient_to')}</span>
              <input
                type="color"
                value={gradient.stops[1]?.color ?? '#000000'}
                onChange={(e) => setStop(1, e.target.value)}
                className="h-9 w-full cursor-pointer rounded border border-line-strong p-0.5"
              />
            </label>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Shape previews ───────────────────────────────────────────────────────────────

const MODE_LABEL_KEY: Record<string, string> = {
  square: 'qr.style.mode_square',
  dots: 'qr.style.mode_dots',
  rounded: 'qr.style.mode_rounded',
  classy: 'qr.style.mode_classy',
  dot: 'qr.style.mode_dots',
};

const MODULE_MODES: ModuleMode[] = ['square', 'dots', 'rounded', 'classy'];
const MODULE_PREVIEWS: Record<ModuleMode, React.ReactNode> = {
  square:  <rect x="3" y="3" width="18" height="18" fill="currentColor" />,
  dots:    <circle cx="12" cy="12" r="9" fill="currentColor" />,
  rounded: <rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="currentColor" />,
  classy:  <polygon points="12,3 21,12 12,21 3,12" fill="currentColor" />,
};

const EYE_FRAME_MODES: EyeFrameMode[] = ['square', 'rounded', 'dots'];
const EYE_FRAME_PREVIEWS: Record<EyeFrameMode, React.ReactNode> = {
  square:  <rect x="2" y="2" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="3" />,
  rounded: <rect x="2" y="2" width="20" height="20" rx="6" fill="none" stroke="currentColor" strokeWidth="3" />,
  dots:    <rect x="2" y="2" width="20" height="20" rx="4" fill="none" stroke="currentColor" strokeWidth="3" strokeDasharray="4 3" />,
};

const EYE_BALL_MODES: EyeBallMode[] = ['square', 'rounded', 'dot'];
const EYE_BALL_PREVIEWS: Record<EyeBallMode, React.ReactNode> = {
  square:  <rect x="6" y="6" width="12" height="12" fill="currentColor" />,
  rounded: <rect x="6" y="6" width="12" height="12" rx="4" fill="currentColor" />,
  dot:     <circle cx="12" cy="12" r="6" fill="currentColor" />,
};

const FRAME_STYLES: FrameStyle[] = ['none', 'simple', 'rounded', 'badge-bottom'];
const FRAME_LABEL_KEY: Record<FrameStyle, string> = {
  none: 'qr.frame.none',
  simple: 'qr.frame.simple',
  rounded: 'qr.frame.rounded',
  'badge-bottom': 'qr.frame.badge_bottom',
};
const FRAME_PREVIEWS: Record<FrameStyle, React.ReactNode> = {
  none:           <rect x="4" y="4" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeDasharray="2 2" />,
  simple:         <rect x="3" y="3" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="3" />,
  rounded:        <rect x="3" y="3" width="18" height="18" rx="6" fill="none" stroke="currentColor" strokeWidth="3" />,
  'badge-bottom': <><rect x="3" y="3" width="18" height="12" fill="none" stroke="currentColor" strokeWidth="2.5" /><rect x="3" y="16" width="18" height="5" fill="currentColor" /></>,
};

// ── Main component ────────────────────────────────────────────────────────────

export default function QrStyleForm({ style, onChange }: Props) {
  const { t } = useTranslation();
  const fileRef = useRef<HTMLInputElement>(null);

  // Single-key update — safe because it merges into a fresh object each time
  const set = <K extends keyof QrStyle>(key: K, val: QrStyle[K]) =>
    onChange({ ...style, [key]: val });

  function handleCustomLogo(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      // Batch both properties in one call so neither overwrites the other
      onChange({ ...style, logo_type: 'custom', logo_data: reader.result as string });
    };
    reader.readAsDataURL(file);
  }

  function selectLogoType(t: LogoType) {
    // Batch: clear dependent fields when switching tabs
    onChange({
      ...style,
      logo_type: t,
      logo_name: t === 'none' ? '' : style.logo_name,
      logo_data: t !== 'custom' ? '' : style.logo_data,
    });
  }

  function selectPredefinedIcon(id: string) {
    // Batch: set name, clear custom data, ensure type is correct — all at once
    onChange({ ...style, logo_type: 'predefined', logo_name: id, logo_data: '' });
  }

  function removeLogo() {
    onChange({ ...style, logo_type: 'none', logo_name: '', logo_data: '' });
  }

  return (
    <div className="space-y-6">

      <QrScannability style={style} />

      {/* ── Colors ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.colors')}</h3>
        <div className="flex flex-col gap-4 sm:flex-row">
          <div className="flex-1">
            <span className="mb-1 block text-xs text-muted">{t('qr.style.foreground')}</span>
            <div className="flex items-center gap-2">
              <input type="color" value={style.foreground}
                onChange={e => set('foreground', e.target.value)}
                className="h-9 w-14 cursor-pointer rounded border border-line-strong p-0.5" />
              <Input type="text" value={style.foreground}
                onChange={e => set('foreground', e.target.value)}
                className="flex-1" />
            </div>
            <GradientControl style={style} onChange={onChange} gradientKey="fg_gradient" solidKey="foreground" t={t} />
          </div>

          <div className="flex-1">
            <span className="mb-1 block text-xs text-muted">{t('qr.style.background')}</span>
            <div className="flex items-center gap-2">
              <input type="color"
                value={style.background === 'transparent' ? '#ffffff' : style.background}
                disabled={style.background === 'transparent'}
                onChange={e => set('background', e.target.value)}
                className="h-9 w-14 cursor-pointer rounded border border-line-strong p-0.5 disabled:cursor-not-allowed disabled:opacity-50" />
              <Input type="text" value={style.background}
                disabled={style.background === 'transparent'}
                onChange={e => set('background', e.target.value)}
                className="flex-1" />
            </div>
            <label className="mt-2 flex items-center gap-2 text-xs text-muted">
              <Checkbox
                checked={style.background === 'transparent'}
                onChange={e => set('background', e.target.checked ? 'transparent' : '#ffffff')}
              />
              {t('qr.style.transparent_bg')}
            </label>
            <GradientControl style={style} onChange={onChange} gradientKey="bg_gradient" solidKey="background" t={t} />
          </div>
        </div>
      </div>

      {/* ── Modules ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.module_shape')}</h3>
        <div className="grid grid-cols-4 gap-2">
          {MODULE_MODES.map((value) => {
            const isActive = style.module_mode === value;
            return (
              <Opt key={value} label={t(MODE_LABEL_KEY[value])} active={isActive} onClick={() => set('module_mode', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{MODULE_PREVIEWS[value]}</svg>
              </Opt>
            );
          })}
        </div>
        <div className="mt-3">
          <RangeRow
            label={t('qr.style.rounding')}
            value={Math.round(style.module_rounding * 100)}
            onChange={(v) => set('module_rounding', v / 100)}
            max={100}
            format={(v) => `${v}%`}
            disabled={style.module_mode === 'square'}
          />
        </div>
      </div>

      {/* ── Eye frame ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.eye_frame')}</h3>
        <div className="grid grid-cols-3 gap-2">
          {EYE_FRAME_MODES.map((value) => {
            const isActive = style.eye_frame_mode === value;
            return (
              <Opt key={value} label={t(MODE_LABEL_KEY[value])} active={isActive} onClick={() => set('eye_frame_mode', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{EYE_FRAME_PREVIEWS[value]}</svg>
              </Opt>
            );
          })}
        </div>
        <div className="mt-3">
          <RangeRow
            label={t('qr.style.rounding')}
            value={Math.round(style.eye_frame_rounding * 100)}
            onChange={(v) => set('eye_frame_rounding', v / 100)}
            max={100}
            format={(v) => `${v}%`}
          />
        </div>
      </div>

      {/* ── Eye ball ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.eye_ball')}</h3>
        <div className="grid grid-cols-3 gap-2">
          {EYE_BALL_MODES.map((value) => {
            const isActive = style.eye_ball_mode === value;
            return (
              <Opt key={value} label={t(MODE_LABEL_KEY[value])} active={isActive} onClick={() => set('eye_ball_mode', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{EYE_BALL_PREVIEWS[value]}</svg>
              </Opt>
            );
          })}
        </div>
        <div className="mt-3">
          <RangeRow
            label={t('qr.style.rounding')}
            value={Math.round(style.eye_ball_rounding * 100)}
            onChange={(v) => set('eye_ball_rounding', v / 100)}
            max={100}
            format={(v) => `${v}%`}
          />
        </div>

        <div className="mt-4">
          <span className="mb-1 block text-xs text-muted">{t('qr.style.eye_color')}</span>
          <label className="mb-2 flex items-center gap-2 text-xs text-muted">
            <Checkbox
              checked={style.eye_color === undefined}
              onChange={e => set('eye_color', e.target.checked ? undefined : style.foreground)}
            />
            {t('qr.style.eye_color_inherit')}
          </label>
          {style.eye_color !== undefined && (
            <div className="flex items-center gap-2">
              <input type="color" value={style.eye_color}
                onChange={e => set('eye_color', e.target.value)}
                className="h-9 w-14 cursor-pointer rounded border border-line-strong p-0.5" />
              <Input type="text" value={style.eye_color}
                onChange={e => set('eye_color', e.target.value)}
                className="flex-1" />
            </div>
          )}
        </div>
      </div>

      {/* ── Logo / Icon ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.logo')}</h3>

        {/* Logo type tabs */}
        <div className="mb-3 flex rounded-lg border border-line bg-elevated p-0.5 text-xs">
          {(['none', 'predefined', 'custom'] as LogoType[]).map(lt => (
            <button key={lt} type="button" onClick={() => selectLogoType(lt)}
              className={`flex-1 rounded-md py-1.5 transition-colors ${
                style.logo_type === lt
                  ? 'bg-surface font-semibold text-foreground shadow-[var(--shadow-sm)]'
                  : 'text-muted hover:text-foreground'
              }`}>
              {t(`qr.style.logo_${lt}`)}
            </button>
          ))}
        </div>

        {/* Predefined icons */}
        {style.logo_type === 'predefined' && (
          <div className="grid grid-cols-5 gap-2">
            {QR_ICONS.map((icon: QrIcon) => {
              const isActive = style.logo_name === icon.id;
              return (
                <button
                  key={icon.id}
                  type="button"
                  onClick={() => selectPredefinedIcon(icon.id)}
                  title={icon.label}
                  className={`flex flex-col items-center gap-1 rounded-lg border p-2 text-xs transition-colors ${
                    isActive
                      ? 'border-accent bg-accent-soft ring-1 ring-[color:var(--accent-ring)]'
                      : 'border-line hover:border-line-strong'
                  }`}
                >
                  <img src={iconToDataUrl(icon)} alt={icon.label} className="h-6 w-6" />
                  <span className={`truncate w-full text-center ${isActive ? 'font-semibold text-accent-soft-foreground' : 'text-muted'}`}>
                    {icon.label}
                  </span>
                </button>
              );
            })}
          </div>
        )}

        {/* Custom upload */}
        {style.logo_type === 'custom' && (
          <div>
            {style.logo_data ? (
              <div className="flex items-center gap-3">
                <img src={style.logo_data} alt="Logo"
                  className="h-12 w-12 rounded border border-line object-contain p-1" />
                <button type="button" onClick={removeLogo}
                  className="flex items-center gap-1 text-xs text-danger-foreground hover:text-danger-foreground">
                  <X className="h-3.5 w-3.5" /> {t('qr.style.remove')}
                </button>
              </div>
            ) : (
              <button type="button" onClick={() => fileRef.current?.click()}
                className="flex w-full flex-col items-center gap-2 rounded-lg border-2 border-dashed border-line-strong py-6 text-subtle hover:border-accent hover:text-accent-soft-foreground">
                <Upload className="h-5 w-5" />
                <span className="text-xs">{t('qr.style.upload')}</span>
              </button>
            )}
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleCustomLogo} />
          </div>
        )}

        {/* Size slider — shown when any logo is active */}
        {style.logo_type !== 'none' && (
          <div className="mt-4">
            <div className="mb-1 flex items-center justify-between">
              <span className="text-xs text-subtle">{t('qr.style.icon_size')}</span>
              <span className="text-xs font-semibold text-foreground">{style.logo_size}%</span>
            </div>
            <input type="range" min="10" max="60" step="5" value={style.logo_size}
              onChange={e => set('logo_size', Number(e.target.value))}
              className="w-full accent-[color:var(--accent)]" />
            <div className="mt-0.5 flex justify-between text-xs text-subtle">
              <span>{t('qr.style.small')}</span><span>{t('qr.style.large')}</span>
            </div>

            <div className="mt-4">
              <RangeRow
                label={t('qr.style.logo_margin')}
                value={style.logo_margin}
                onChange={(v) => set('logo_margin', v)}
                min={0}
                max={10}
                step={1}
              />
            </div>

            <label className="mt-3 flex items-center gap-2 text-xs text-muted">
              <Checkbox
                checked={style.logo_clear_modules}
                onChange={e => set('logo_clear_modules', e.target.checked)}
              />
              {t('qr.style.logo_clear')}
            </label>
          </div>
        )}
      </div>

      {/* ── Frame ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.frame.title')}</h3>
        <div className="grid grid-cols-4 gap-2">
          {FRAME_STYLES.map((value) => {
            const isActive = style.frame_style === value;
            return (
              <Opt key={value} label={t(FRAME_LABEL_KEY[value])} active={isActive} onClick={() => set('frame_style', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{FRAME_PREVIEWS[value]}</svg>
              </Opt>
            );
          })}
        </div>

        {style.frame_style !== 'none' && (
          <div className="mt-4 space-y-3">
            <label className="block">
              <span className="mb-1 block text-xs text-muted">{t('qr.frame.text')}</span>
              <Input type="text" value={style.frame_text}
                onChange={e => set('frame_text', e.target.value)}
                placeholder={t('qr.frame.text_placeholder')} />
            </label>
            <div className="flex gap-3">
              <label className="flex-1">
                <span className="mb-1 block text-xs text-muted">{t('qr.frame.text_color')}</span>
                <input type="color" value={style.frame_text_color}
                  onChange={e => set('frame_text_color', e.target.value)}
                  className="h-9 w-full cursor-pointer rounded border border-line-strong p-0.5" />
              </label>
              <label className="flex-1">
                <span className="mb-1 block text-xs text-muted">{t('qr.frame.color')}</span>
                <input type="color" value={style.frame_color}
                  onChange={e => set('frame_color', e.target.value)}
                  className="h-9 w-full cursor-pointer rounded border border-line-strong p-0.5" />
              </label>
              <label className="flex-1">
                <span className="mb-1 block text-xs text-muted">{t('qr.frame.background')}</span>
                <input type="color" value={style.frame_background}
                  onChange={e => set('frame_background', e.target.value)}
                  className="h-9 w-full cursor-pointer rounded border border-line-strong p-0.5" />
              </label>
            </div>
          </div>
        )}
      </div>

      {/* ── Robustness ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.error_correction')}</h3>
        <div className="flex rounded-lg border border-line bg-elevated p-0.5 text-sm">
          {(['L', 'M', 'Q', 'H'] as QrEcc[]).map((level) => (
            <button key={level} type="button" onClick={() => set('error_correction', level)}
              className={`flex-1 rounded-md py-1.5 font-semibold transition-colors ${
                style.error_correction === level
                  ? 'bg-surface text-foreground shadow-[var(--shadow-sm)]'
                  : 'text-muted hover:text-foreground'
              }`}>
              {level}
            </button>
          ))}
        </div>
        <p className="mt-1.5 text-xs text-subtle">{t('qr.style.ecc_hint')}</p>

        <div className="mt-4">
          <RangeRow
            label={t('qr.style.quiet_zone')}
            value={style.quiet_zone}
            onChange={(v) => set('quiet_zone', v)}
            min={0}
            max={10}
            step={1}
          />
        </div>
      </div>
    </div>
  );
}
