import { useTranslation } from '@/lib/i18n';
import { Check, Copy, Info } from 'lucide-react';
import { useState } from 'react';

export default function DnsInfoBox({ appDomain }: { appDomain: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    await navigator.clipboard.writeText(appDomain);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="bg-accent-soft mb-6 rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--accent)_30%,transparent)] p-4">
      <div className="flex items-start gap-3">
        <Info className="text-accent-soft-foreground mt-0.5 h-5 w-5 flex-shrink-0" />
        <div className="text-foreground text-sm">
          <p className="text-foreground font-semibold">{t('domains.form.dns.title')}</p>
          <p className="mt-1">{t('domains.form.dns.instruction')}</p>
          <div className="bg-surface mt-2 flex flex-wrap items-center gap-2 rounded-[var(--radius-sm)] px-3 py-2 font-mono text-xs">
            <span className="text-muted">CNAME →</span>
            <span className="text-foreground font-semibold">{appDomain}</span>
            <button
              type="button"
              onClick={copy}
              className="text-subtle hover:bg-elevated hover:text-foreground ml-auto inline-flex items-center gap-1 rounded px-1.5 py-0.5 transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            >
              {copied ? <Check className="text-success-foreground h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
              {copied ? t('domains.form.dns.copied') : t('domains.form.dns.copy')}
            </button>
          </div>
          <p className="text-muted mt-2 text-xs">{t('domains.form.dns.ssl_note')}</p>
        </div>
      </div>
    </div>
  );
}
