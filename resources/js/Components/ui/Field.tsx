import { cloneElement, isValidElement, PropsWithChildren, ReactElement, ReactNode } from 'react';

interface FieldProps {
  label?: ReactNode;
  hint?: ReactNode;
  error?: ReactNode;
  htmlFor?: string;
}

export function Field({ label, hint, error, htmlFor, children }: PropsWithChildren<FieldProps>) {
  const describedById = htmlFor && (error || hint) ? `${htmlFor}-desc` : undefined;

  const control = isValidElement(children)
    ? cloneElement(children as ReactElement<Record<string, unknown>>, {
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedById,
      })
    : children;

  return (
    <div className="space-y-1.5">
      {label && (
        <label htmlFor={htmlFor} className="block text-sm font-semibold text-foreground">
          {label}
        </label>
      )}
      {control}
      {error ? (
        <p id={describedById} className="text-xs text-danger-foreground">
          {error}
        </p>
      ) : hint ? (
        <p id={describedById} className="text-xs text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  );
}
