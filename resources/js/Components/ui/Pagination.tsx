import { Link } from '@inertiajs/react';

interface PageLink {
  url: string | null;
  label: string;
  active: boolean;
}

export function Pagination({ links }: { links: PageLink[] }) {
  return (
    <nav className="flex flex-wrap items-center gap-1 px-4 py-3">
      {links.map((link, i) =>
        link.url === null ? (
          <span
            key={i}
            className="pointer-events-none rounded-md px-3 py-1.5 text-sm text-subtle opacity-50"
            dangerouslySetInnerHTML={{ __html: link.label }}
          />
        ) : (
          <Link
            key={i}
            href={link.url}
            className={`rounded-md px-3 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
              link.active ? 'bg-accent text-accent-foreground' : 'text-muted hover:bg-elevated hover:text-foreground'
            }`}
            dangerouslySetInnerHTML={{ __html: link.label }}
          />
        ),
      )}
    </nav>
  );
}
