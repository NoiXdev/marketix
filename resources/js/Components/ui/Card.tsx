import { HTMLAttributes } from 'react';

export function Card({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={`border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)] ${className}`} {...props} />;
}
