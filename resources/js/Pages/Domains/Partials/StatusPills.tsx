import { Domain } from '@/types';

function Pill({ label, value }: { label: string; value: boolean | null }) {
  const style =
    value === true
      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
      : value === false
        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500';

  const mark = value === true ? '✓' : value === false ? '✗' : '–';

  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${style}`}>
      <span aria-hidden>{mark}</span>
      {label}
    </span>
  );
}

export default function StatusPills({ domain }: { domain: Domain }) {
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <Pill label="DNS" value={domain.dns_ok} />
      <Pill label="Reachable" value={domain.reachable_ok} />
      <Pill label="SSL" value={domain.ssl_ok} />
    </div>
  );
}
