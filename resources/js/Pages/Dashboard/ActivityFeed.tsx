import { Activity as ActIcon } from 'lucide-react';

export interface FeedItem {
  id: string; log_name: string; description: string; event: string;
  subject_type: string | null; causer: { id: string; name: string } | null; created_at: string;
}

function ago(iso: string): string {
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return 'gerade eben';
  if (s < 3600) return `vor ${Math.floor(s / 60)} Min.`;
  if (s < 86400) return `vor ${Math.floor(s / 3600)} Std.`;
  return `vor ${Math.floor(s / 86400)} Tg.`;
}

export default function ActivityFeed({ items, emptyLabel }: { items: FeedItem[]; emptyLabel: string }) {
  if (items.length === 0) return <p className="px-4 py-6 text-center text-sm text-subtle">{emptyLabel}</p>;
  return (
    <div className="p-2">
      {items.map((it) => (
        <div key={it.id} className="flex gap-3 px-2 py-2">
          <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-elevated text-muted">
            <ActIcon className="h-[15px] w-[15px]" />
          </span>
          <div className="min-w-0 text-[13px] text-foreground">
            <p className="truncate">
              {it.causer && <span className="font-semibold">{it.causer.name} </span>}
              {it.description}
              {it.subject_type && <span className="text-muted"> · {it.subject_type}</span>}
            </p>
            <p className="mt-0.5 text-[11.5px] text-subtle">{ago(it.created_at)}</p>
          </div>
        </div>
      ))}
    </div>
  );
}
