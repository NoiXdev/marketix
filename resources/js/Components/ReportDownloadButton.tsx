import { Download } from 'lucide-react';
import { useState } from 'react';

type Props = { projectId: string; urlId?: string };

export default function ReportDownloadButton({ projectId, urlId }: Props) {
  const [open, setOpen] = useState(false);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const build = (params: Record<string, string | number>) => {
    const base = { project: projectId, ...(urlId ? { url: urlId } : {}), ...params };
    return urlId
      ? route('app.project.links.reports.download', base)
      : route('app.project.reports.download', base);
  };

  const go = (params: Record<string, string | number>) => {
    window.open(build(params), '_blank');
    setOpen(false);
  };

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
      >
        <Download className="h-4 w-4" /> Download PDF
      </button>
      {open && (
        <div className="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900">
          <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Preset</div>
          <div className="flex gap-2">
            {[7, 30, 90].map((d) => (
              <button key={d} type="button" onClick={() => go({ range: d })}
                className="flex-1 rounded-lg bg-slate-100 px-2 py-1.5 text-sm hover:bg-indigo-100 dark:bg-slate-800">
                {d}d
              </button>
            ))}
          </div>
          <div className="my-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Custom</div>
          <div className="flex flex-col gap-2">
            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)}
              className="rounded-lg border border-slate-200 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-800" />
            <input type="date" value={to} onChange={(e) => setTo(e.target.value)}
              className="rounded-lg border border-slate-200 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-800" />
            <button type="button" disabled={!from || !to} onClick={() => go({ from, to })}
              className="rounded-lg bg-indigo-600 px-2 py-1.5 text-sm font-medium text-white disabled:opacity-50">
              Download range
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
