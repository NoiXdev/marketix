import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ReactNode } from 'react';

export function BackLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link
      href={href}
      className="text-muted hover:text-foreground inline-flex items-center gap-1.5 rounded text-sm transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
    >
      <ArrowLeft className="h-4 w-4" />
      {children}
    </Link>
  );
}
