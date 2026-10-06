import { useTranslation } from '@/lib/i18n';
import { Activity as ActIcon } from 'lucide-react';

export interface FeedItem {
  id: string;
  log_name: string;
  description: string;
  event: string;
  subject_type: string | null;
  causer: { id: string; name: string } | null;
  created_at: string;
}

function ago(iso: string, rtf: Intl.RelativeTimeFormat): string {
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return rtf.format(-s, 'second');
  if (s < 3600) return rtf.format(-Math.floor(s / 60), 'minute');
  if (s < 86400) return rtf.format(-Math.floor(s / 3600), 'hour');
  return rtf.format(-Math.floor(s / 86400), 'day');
}

export default function ActivityFeed({ items, emptyLabel }: { items: FeedItem[]; emptyLabel: string }) {
  const { locale } = useTranslation();
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  if (items.length === 0) return <p className="text-subtle px-4 py-6 text-center text-sm">{emptyLabel}</p>;
  return (
    <div className="p-2">
      {items.map((it) => (
        <div key={it.id} className="flex gap-3 px-2 py-2">
          <span className="bg-elevated text-muted grid h-7 w-7 shrink-0 place-items-center rounded-lg">
            <ActIcon className="h-[15px] w-[15px]" />
          </span>
          <div className="text-foreground min-w-0 text-[13px]">
            <p className="truncate">
              {it.causer && <span className="font-semibold">{it.causer.name} </span>}
              {it.description}
              {it.subject_type && <span className="text-muted"> · {it.subject_type}</span>}
            </p>
            <p className="text-subtle mt-0.5 text-[11.5px]">{ago(it.created_at, rtf)}</p>
          </div>
        </div>
      ))}
    </div>
  );
}
