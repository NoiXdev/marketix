import { InputHTMLAttributes, forwardRef } from 'react';

export const Checkbox = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Checkbox({ className = '', ...props }, ref) {
  return (
    <input
      ref={ref}
      type="checkbox"
      className={`border-line-strong text-accent h-4 w-4 rounded focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${className}`}
      {...props}
    />
  );
});
