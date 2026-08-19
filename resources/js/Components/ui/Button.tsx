import { ButtonHTMLAttributes, forwardRef, MouseEvent, useId } from 'react';
import { Loader2 } from 'lucide-react';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md';

const base =
  'inline-flex items-center justify-center gap-2 rounded-[var(--radius-sm)] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:pointer-events-none disabled:opacity-50 aria-disabled:opacity-50 aria-disabled:cursor-not-allowed';

const sizes: Record<ButtonSize, string> = {
  sm: 'px-2.5 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
};

const variants: Record<ButtonVariant, string> = {
  primary: 'bg-accent text-accent-foreground hover:bg-accent-hover',
  secondary: 'bg-surface text-foreground border border-line-strong hover:bg-elevated',
  ghost: 'text-muted hover:bg-elevated hover:text-foreground',
  danger:
    'text-danger-foreground border border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] hover:bg-danger-soft',
};

export function buttonClasses(variant: ButtonVariant = 'primary', size: ButtonSize = 'md', className = ''): string {
  return `${base} ${sizes[size]} ${variants[variant]} ${className}`;
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  /**
   * Renders the button as "soft-disabled" with an explanation, instead of
   * natively `disabled`. A natively `disabled` button drops out of the tab
   * order and (in Chromium browsers) stops firing hover events, so its
   * `title` tooltip never appears — the explanation becomes imperceptible to
   * keyboard users and to most mouse users. `lockedHint` keeps the button
   * focusable and hoverable (so the tooltip still fires) and exposes the
   * same text to assistive tech via `aria-describedby`, while the click
   * handler still no-ops so the action can't actually happen.
   */
  lockedHint?: string;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', type = 'button', className = '', loading = false, disabled, lockedHint, onClick, children, ...props },
  ref,
) {
  const hintId = useId();
  const softLocked = Boolean(lockedHint) && !loading;
  const nativeDisabled = !softLocked && (disabled || loading);

  const handleClick = (e: MouseEvent<HTMLButtonElement>) => {
    if (softLocked) {
      e.preventDefault();
      return;
    }
    onClick?.(e);
  };

  return (
    <>
      <button
        {...props}
        ref={ref}
        type={type}
        disabled={nativeDisabled}
        aria-disabled={softLocked || undefined}
        aria-describedby={softLocked ? hintId : undefined}
        aria-busy={loading || undefined}
        title={softLocked ? lockedHint : props.title}
        onClick={handleClick}
        className={buttonClasses(variant, size, className)}
      >
        {loading && <Loader2 className="h-4 w-4 animate-spin" />}
        {children}
      </button>
      {softLocked && (
        <span id={hintId} className="sr-only">
          {lockedHint}
        </span>
      )}
    </>
  );
});
