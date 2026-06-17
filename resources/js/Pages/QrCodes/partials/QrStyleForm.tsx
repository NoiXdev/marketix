import { QrIcon, QR_ICONS, iconToDataUrl } from '@/data/qrIcons';
import { CornerDotStyle, CornerSquareStyle, DotStyle, LogoType, QrStyle } from '@/data/qrTypes';
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
          ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/20 dark:text-indigo-300'
          : 'border-slate-200 text-slate-500 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'
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
        <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Colors</h3>
        <div className="flex gap-4">
          {(['foreground', 'background'] as const).map((key) => (
            <label key={key} className="flex-1">
              <span className="block text-xs capitalize text-slate-500 dark:text-slate-400 mb-1">{key}</span>
              <div className="flex items-center gap-2">
                <input type="color" value={style[key]}
                  onChange={e => set(key, e.target.value)}
                  className="h-9 w-14 cursor-pointer rounded border border-slate-300 p-0.5 dark:border-slate-600" />
                <input type="text" value={style[key]}
                  onChange={e => set(key, e.target.value)}
                  className="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
              </div>
            </label>
          ))}
        </div>
      </div>

      {/* ── Matrix / Dot style ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Matrix style</h3>
        <div className="grid grid-cols-3 gap-2">
          {DOT_SHAPES.map(({ value, label, preview }) => (
            <Opt key={value} label={label} active={style.dot_style === value} onClick={() => set('dot_style', value)}>
              <svg viewBox="0 0 24 24" className="h-7 w-7 text-slate-700 dark:text-slate-300">{preview}</svg>
            </Opt>
          ))}
        </div>
      </div>

      {/* ── Eye frame (corner square) ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Eye frame</h3>
        <div className="grid grid-cols-3 gap-2">
          {CORNER_SQUARE_SHAPES.map(({ value, label, preview }) => (
            <Opt key={value} label={label} active={style.corner_square_style === value} onClick={() => set('corner_square_style', value)}>
              <svg viewBox="0 0 24 24" className="h-7 w-7 text-slate-700 dark:text-slate-300">{preview}</svg>
            </Opt>
          ))}
        </div>
      </div>

      {/* ── Eye ball (corner dot) ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Eye ball</h3>
        <div className="grid grid-cols-2 gap-2">
          {CORNER_DOT_SHAPES.map(({ value, label, preview }) => (
            <Opt key={value} label={label} active={style.corner_dot_style === value} onClick={() => set('corner_dot_style', value)}>
              <svg viewBox="0 0 24 24" className="h-7 w-7 text-slate-700 dark:text-slate-300">{preview}</svg>
            </Opt>
          ))}
        </div>
      </div>

      {/* ── Logo / Icon ── */}
      <div>
        <h3 className="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Logo / Icon</h3>

        {/* Logo type tabs */}
        <div className="mb-3 flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs dark:border-slate-700 dark:bg-slate-800">
          {(['none', 'predefined', 'custom'] as LogoType[]).map(t => (
            <button key={t} type="button" onClick={() => selectLogoType(t)}
              className={`flex-1 rounded-md py-1.5 capitalize transition-colors ${
                style.logo_type === t
                  ? 'bg-white font-semibold text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                  : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
              }`}>
              {t}
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
                      ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:border-indigo-400 dark:bg-indigo-900/20 dark:ring-indigo-400'
                      : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-500'
                  }`}
                >
                  <img src={iconToDataUrl(icon)} alt={icon.label} className="h-6 w-6" />
                  <span className={`truncate w-full text-center ${isActive ? 'font-semibold text-indigo-700 dark:text-indigo-300' : 'text-slate-500 dark:text-slate-400'}`}>
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
                  className="h-12 w-12 rounded border border-slate-200 object-contain p-1 dark:border-slate-700" />
                <button type="button" onClick={removeLogo}
                  className="flex items-center gap-1 text-xs text-red-500 hover:text-red-700">
                  <X className="h-3.5 w-3.5" /> Remove
                </button>
              </div>
            ) : (
              <button type="button" onClick={() => fileRef.current?.click()}
                className="flex w-full flex-col items-center gap-2 rounded-lg border-2 border-dashed border-slate-300 py-6 text-slate-400 hover:border-indigo-400 hover:text-indigo-500 dark:border-slate-700">
                <Upload className="h-5 w-5" />
                <span className="text-xs">Click to upload image</span>
              </button>
            )}
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleCustomLogo} />
          </div>
        )}

        {/* Size slider — shown when any logo is active */}
        {style.logo_type !== 'none' && (
          <div className="mt-4">
            <div className="mb-1 flex items-center justify-between">
              <span className="text-xs text-slate-500 dark:text-slate-400">Icon size</span>
              <span className="text-xs font-semibold text-slate-700 dark:text-slate-300">{style.logo_size}%</span>
            </div>
            <input type="range" min="10" max="60" step="5" value={style.logo_size}
              onChange={e => set('logo_size', Number(e.target.value))}
              className="w-full accent-indigo-600" />
            <div className="mt-0.5 flex justify-between text-xs text-slate-400">
              <span>Small</span><span>Large</span>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
