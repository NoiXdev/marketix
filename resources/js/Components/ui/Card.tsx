import { HTMLAttributes } from 'react';

export function Card({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={`rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)] ${className}`}
      {...props}
    />
  );
}
