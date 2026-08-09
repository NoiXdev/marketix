import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ReactNode } from 'react';

export function BackLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link
      href={href}
      className="inline-flex items-center gap-1.5 rounded text-sm text-muted transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
    >
      <ArrowLeft className="h-4 w-4" />
      {children}
    </Link>
  );
}
