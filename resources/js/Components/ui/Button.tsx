import { ButtonHTMLAttributes, forwardRef } from 'react';
import { Loader2 } from 'lucide-react';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md';

const base =
  'inline-flex items-center justify-center gap-2 rounded-[var(--radius-sm)] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:pointer-events-none disabled:opacity-50';

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
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', type = 'button', className = '', loading = false, disabled, children, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={buttonClasses(variant, size, className)}
      {...props}
    >
      {loading && <Loader2 className="h-4 w-4 animate-spin" />}
      {children}
    </button>
  );
});
