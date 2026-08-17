import { Select } from '@/Components/ui';

export type CrawlNavItem = {
  key: string;
  label: string;
  active: boolean;
  onSelect: () => void;
  count?: number;
  dotClass?: string;
  dimmed?: boolean;
};

export function CrawlSidebar({
  primary,
  sectionLabel,
  items = [],
}: {
  primary: CrawlNavItem[];
  sectionLabel?: string;
  items?: CrawlNavItem[];
}) {
  const all = [...primary, ...items];
  const item = (it: CrawlNavItem) => (
    <button
      key={it.key}
      type="button"
      aria-current={it.active ? 'page' : undefined}
      onClick={it.onSelect}
      className={`flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left text-sm transition ${
        it.active ? 'bg-accent-soft font-medium text-accent-soft-foreground' : 'text-muted hover:bg-elevated hover:text-foreground'
      } ${it.dimmed ? 'opacity-50' : ''}`}
    >
      <span className="flex min-w-0 items-center gap-2">
        {it.dotClass && <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${it.dotClass}`} />}
        <span className="truncate">{it.label}</span>
      </span>
      {it.count !== undefined && <span className="shrink-0 text-xs text-muted">{it.count}</span>}
    </button>
  );

  return (
    <>
      <nav className="hidden space-y-0.5 md:block md:w-56 md:flex-none">
        {primary.map(item)}
        {sectionLabel && items.length > 0 && (
          <>
            <div className="px-3 pb-1 pt-4 text-xs text-muted">{sectionLabel}</div>
            {items.map(item)}
          </>
        )}
      </nav>

      <div className="mb-4 space-y-2 md:hidden">
        <div className="flex flex-wrap gap-1">
          {primary.map((it) => (
            <button
              key={it.key}
              type="button"
              onClick={it.onSelect}
              className={`rounded-md px-3 py-1.5 text-sm ${it.active ? 'bg-accent-soft font-medium text-accent-soft-foreground' : 'text-muted hover:text-foreground'}`}
            >
              {it.label}
            </button>
          ))}
        </div>
        {items.length > 0 && (
          <Select
            value={items.find((i) => i.active)?.key ?? ''}
            onChange={(e) => all.find((i) => i.key === e.target.value)?.onSelect()}
            className="w-full"
          >
            <option value="" disabled>{sectionLabel}</option>
            {items.map((it) => (
              <option key={it.key} value={it.key}>
                {it.label}
                {it.count !== undefined ? ` (${it.count})` : ''}
              </option>
            ))}
          </Select>
        )}
      </div>
    </>
  );
}
