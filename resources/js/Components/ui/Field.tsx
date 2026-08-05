import { PropsWithChildren, ReactNode } from 'react';

interface FieldProps {
  label?: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  htmlFor?: string;
}

export function Field({ label, hint, error, htmlFor, children }: PropsWithChildren<FieldProps>) {
  return (
    <div className="space-y-1.5">
      {label && (
        <label htmlFor={htmlFor} className="block text-sm font-semibold text-foreground">
          {label}
        </label>
      )}
      {children}
      {error ? (
        <p className="text-xs text-danger-foreground">{error}</p>
      ) : hint ? (
        <p className="text-xs text-muted">{hint}</p>
      ) : null}
    </div>
  );
}
