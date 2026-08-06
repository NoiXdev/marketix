import { Input } from '@/Components/ui';
import { QrIcon, QR_ICONS, iconToDataUrl } from '@/data/qrIcons';
import { CornerDotStyle, CornerSquareStyle, DotStyle, LogoType, QrStyle } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { Upload, X } from 'lucide-react';
import { useRef } from 'react';

interface Props {
  style: QrStyle;
  onChange: (style: QrStyle) => void;
}

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

// ── Dot style previews ────────────────────────────────────────────────────────

const DOT_SHAPES: { value: DotStyle; label: string; preview: React.ReactNode }[] = [
  { value: 'square',         label: 'Square',     preview: <rect x="3" y="3" width="18" height="18" fill="currentColor" /> },
  { value: 'dots',           label: 'Dots',        preview: <circle cx="12" cy="12" r="9" fill="currentColor" /> },
  { value: 'rounded',        label: 'Rounded',     preview: <rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="currentColor" /> },
  { value: 'classy',         label: 'Classy',      preview: <polygon points="12,3 21,12 12,21 3,12" fill="currentColor" /> },
  { value: 'classy-rounded', label: 'Classy Rnd',  preview: <polygon points="12,4 20,12 12,20 4,12" fill="currentColor" stroke="currentColor" strokeLinejoin="round" strokeWidth="2" /> },
  { value: 'extra-rounded',  label: 'Extra Rnd',   preview: <rect x="3" y="3" width="18" height="18" rx="9" ry="9" fill="currentColor" /> },
];

const CORNER_SQUARE_SHAPES: { value: CornerSquareStyle; label: string; preview: React.ReactNode }[] = [
  { value: 'square',        label: 'Square',  preview: <><rect x="2" y="2" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="3" /><rect x="6" y="6" width="12" height="12" fill="currentColor" /></> },
  { value: 'dot',           label: 'Dot',     preview: <><rect x="2" y="2" width="20" height="20" rx="5" fill="none" stroke="currentColor" strokeWidth="3" /><circle cx="12" cy="12" r="5" fill="currentColor" /></> },
  { value: 'extra-rounded', label: 'Rounded', preview: <><rect x="2" y="2" width="20" height="20" rx="9" fill="none" stroke="currentColor" strokeWidth="3" /><rect x="6" y="6" width="12" height="12" rx="4" fill="currentColor" /></> },
];

const CORNER_DOT_SHAPES: { value: CornerDotStyle; label: string; preview: React.ReactNode }[] = [
  { value: 'square', label: 'Square', preview: <rect x="6" y="6" width="12" height="12" fill="currentColor" /> },
  { value: 'dot',    label: 'Dot',    preview: <circle cx="12" cy="12" r="6" fill="currentColor" /> },
];

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

      {/* ── Colors ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.colors')}</h3>
        <div className="flex gap-4">
          {(['foreground', 'background'] as const).map((key) => (
            <label key={key} className="flex-1">
              <span className="block text-xs capitalize text-muted mb-1">{t(`qr.style.${key}`)}</span>
              <div className="flex items-center gap-2">
                <input type="color" value={style[key]}
                  onChange={e => set(key, e.target.value)}
                  className="h-9 w-14 cursor-pointer rounded border border-line-strong p-0.5" />
                <Input type="text" value={style[key]}
                  onChange={e => set(key, e.target.value)}
                  className="flex-1" />
              </div>
            </label>
          ))}
        </div>
      </div>

      {/* ── Matrix / Dot style ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.matrix')}</h3>
        <div className="grid grid-cols-3 gap-2">
          {DOT_SHAPES.map(({ value, label, preview }) => {
            const isActive = style.dot_style === value;
            return (
              <Opt key={value} label={label} active={isActive} onClick={() => set('dot_style', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{preview}</svg>
              </Opt>
            );
          })}
        </div>
      </div>

      {/* ── Eye frame (corner square) ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.eye_frame')}</h3>
        <div className="grid grid-cols-3 gap-2">
          {CORNER_SQUARE_SHAPES.map(({ value, label, preview }) => {
            const isActive = style.corner_square_style === value;
            return (
              <Opt key={value} label={label} active={isActive} onClick={() => set('corner_square_style', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{preview}</svg>
              </Opt>
            );
          })}
        </div>
      </div>

      {/* ── Eye ball (corner dot) ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{t('qr.style.eye_ball')}</h3>
        <div className="grid grid-cols-2 gap-2">
          {CORNER_DOT_SHAPES.map(({ value, label, preview }) => {
            const isActive = style.corner_dot_style === value;
            return (
              <Opt key={value} label={label} active={isActive} onClick={() => set('corner_dot_style', value)}>
                <svg viewBox="0 0 24 24" className={`h-7 w-7 ${isActive ? 'text-accent-soft-foreground' : 'text-foreground'}`}>{preview}</svg>
              </Opt>
            );
          })}
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
          </div>
        )}
      </div>
    </div>
  );
}
