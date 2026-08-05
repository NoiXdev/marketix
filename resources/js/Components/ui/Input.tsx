import { InputHTMLAttributes, forwardRef } from 'react';

const cls =
  'w-full rounded-[var(--radius-sm)] border border-line-strong bg-surface px-3 py-2 text-sm text-foreground transition-colors placeholder:text-subtle focus-visible:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:opacity-50';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input(
  { className = '', ...props },
  ref,
) {
  return <input ref={ref} className={`${cls} ${className}`} {...props} />;
});
