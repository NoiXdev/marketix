import { Check, Copy, Info } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';

export default function DnsInfoBox({ appDomain }: { appDomain: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    await navigator.clipboard.writeText(appDomain);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="mb-6 rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--accent)_30%,transparent)] bg-accent-soft p-4">
      <div className="flex items-start gap-3">
        <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-accent-soft-foreground" />
        <div className="text-sm text-foreground">
          <p className="font-semibold text-foreground">{t('domains.form.dns.title')}</p>
          <p className="mt-1">{t('domains.form.dns.instruction')}</p>
          <div className="mt-2 flex flex-wrap items-center gap-2 rounded-[var(--radius-sm)] bg-surface px-3 py-2 font-mono text-xs">
            <span className="text-muted">CNAME →</span>
            <span className="font-semibold text-foreground">{appDomain}</span>
            <button
              type="button"
              onClick={copy}
              className="ml-auto inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-subtle transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
              {copied ? <Check className="h-3.5 w-3.5 text-success-foreground" /> : <Copy className="h-3.5 w-3.5" />}
              {copied ? t('domains.form.dns.copied') : t('domains.form.dns.copy')}
            </button>
          </div>
          <p className="mt-2 text-xs text-muted">{t('domains.form.dns.ssl_note')}</p>
        </div>
      </div>
    </div>
  );
}
