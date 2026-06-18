import { Check, Copy, Info } from 'lucide-react';
import { useState } from 'react';

export default function DnsInfoBox({ appDomain }: { appDomain: string }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    await navigator.clipboard.writeText(appDomain);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900/50 dark:bg-indigo-900/20">
      <div className="flex items-start gap-3">
        <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-indigo-600 dark:text-indigo-400" />
        <div className="text-sm text-slate-700 dark:text-slate-300">
          <p className="font-semibold text-slate-900 dark:text-white">Connect your domain</p>
          <p className="mt-1">
            At your DNS provider, point your domain to us with a <strong>CNAME</strong> record:
          </p>
          <div className="mt-2 flex flex-wrap items-center gap-2 rounded-md bg-white px-3 py-2 font-mono text-xs dark:bg-slate-800">
            <span className="text-slate-500 dark:text-slate-400">CNAME →</span>
            <span className="font-semibold text-slate-900 dark:text-white">{appDomain}</span>
            <button
              type="button"
              onClick={copy}
              className="ml-auto inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200"
            >
              {copied ? <Check className="h-3.5 w-3.5 text-green-600" /> : <Copy className="h-3.5 w-3.5" />}
              {copied ? 'Copied' : 'Copy'}
            </button>
          </div>
          <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Once DNS propagates we automatically issue an SSL certificate — this can take a few minutes.
          </p>
        </div>
      </div>
    </div>
  );
}
