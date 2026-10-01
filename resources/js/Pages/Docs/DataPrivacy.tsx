import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import { Check, Cookie, Copy, Database, FileText, Info, Scale, ShieldOff, UserCheck } from 'lucide-react';
import { ReactNode, useState } from 'react';

interface Props {
  appName: string;
  statsMonths: number;
  analyticsMonths: number;
}

function Section({ icon: Icon, title, children }: { icon: typeof Database; title: string; children: ReactNode }) {
  return (
    <section className="rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
      <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
        <Icon className="h-4 w-4 text-accent-soft-foreground" />
        {title}
      </h2>
      <div className="space-y-3 text-sm leading-relaxed text-muted">{children}</div>
    </section>
  );
}

function Bullets({ items }: { items: string[] }) {
  return (
    <ul className="space-y-2">
      {items.map((text, i) => (
        <li key={i} className="flex gap-2">
          <span className="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-accent" />
          <span>{text}</span>
        </li>
      ))}
    </ul>
  );
}

export default function DataPrivacy({ appName, statsMonths, analyticsMonths }: Props) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const snippet = t('docs.privacy.notice.snippet', { app: appName });

  function copySnippet() {
    navigator.clipboard.writeText(snippet).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <AppLayout title={t('docs.privacy.title')}>
      <div className="mx-auto max-w-3xl px-8 py-8">
        <PageHeader title={t('docs.privacy.title')} subtitle={t('docs.privacy.subtitle', { app: appName })} />

        {/* Disclaimer */}
        <div className="mb-6 flex items-start gap-2 rounded-[var(--radius)] border border-line bg-elevated px-4 py-3 text-xs text-muted">
          <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-subtle" />
          <span>{t('docs.privacy.disclaimer')}</span>
        </div>

        <div className="space-y-4">
          <Section icon={Database} title={t('docs.privacy.collect.heading', { app: appName })}>
            <p>{t('docs.privacy.collect.intro', { app: appName })}</p>
            <Bullets
              items={[
                t('docs.privacy.collect.ip'),
                t('docs.privacy.collect.geo'),
                t('docs.privacy.collect.device'),
                t('docs.privacy.collect.referrer'),
                t('docs.privacy.collect.utm'),
                t('docs.privacy.collect.timestamp'),
              ]}
            />
          </Section>

          <Section icon={Cookie} title={t('docs.privacy.modes.heading')}>
            <Bullets
              items={[
                t('docs.privacy.modes.cookieless'),
                t('docs.privacy.modes.cookie'),
                t('docs.privacy.modes.dnt'),
                t('docs.privacy.modes.links_note', { app: appName }),
              ]}
            />
          </Section>

          <Section icon={ShieldOff} title={t('docs.privacy.nocollect.heading', { app: appName })}>
            <Bullets
              items={[
                t('docs.privacy.nocollect.cookies'),
                t('docs.privacy.nocollect.raw_ip'),
                t('docs.privacy.nocollect.pii'),
                t('docs.privacy.nocollect.cross'),
              ]}
            />
          </Section>

          <Section icon={Scale} title={t('docs.privacy.retention.heading')}>
            <p>{t('docs.privacy.retention.basis')}</p>
            <Bullets
              items={[
                t('docs.privacy.retention.stats', { stats: statsMonths }),
                t('docs.privacy.retention.analytics', { analytics: analyticsMonths }),
                t('docs.privacy.retention.aggregates'),
              ]}
            />
          </Section>

          <Section icon={FileText} title={t('docs.privacy.notice.heading')}>
            <p>{t('docs.privacy.notice.intro', { app: appName })}</p>
            <div className="relative rounded-[var(--radius-sm)] border border-line bg-elevated p-4 pr-12 text-[13px] leading-relaxed text-foreground">
              {snippet}
              <button
                type="button"
                onClick={copySnippet}
                title={copied ? t('docs.privacy.notice.copied') : t('docs.privacy.notice.copy')}
                aria-label={copied ? t('docs.privacy.notice.copied') : t('docs.privacy.notice.copy')}
                className={`absolute right-2 top-2 rounded-md p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                  copied ? 'text-success-foreground' : 'text-subtle hover:bg-surface hover:text-foreground'
                }`}
              >
                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              </button>
            </div>
          </Section>

          <Section icon={UserCheck} title={t('docs.privacy.responsibilities.heading')}>
            <p>{t('docs.privacy.responsibilities.intro', { app: appName })}</p>
            <Bullets
              items={[
                t('docs.privacy.responsibilities.policy'),
                t('docs.privacy.responsibilities.basis'),
                t('docs.privacy.responsibilities.consent'),
                t('docs.privacy.responsibilities.dpa', { app: appName }),
                t('docs.privacy.responsibilities.rights'),
              ]}
            />
          </Section>
        </div>
      </div>
    </AppLayout>
  );
}
