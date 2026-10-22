import { ReactNode } from 'react';

export default function CardColumns({ left, right, className = '' }: { left: ReactNode; right: ReactNode; className?: string }) {
  return (
    <div className={`grid grid-cols-1 items-start gap-3.5 md:grid-cols-2 ${className}`}>
      <div className="flex min-w-0 flex-col gap-3.5">{left}</div>
      <div className="flex min-w-0 flex-col gap-3.5">{right}</div>
    </div>
  );
}
