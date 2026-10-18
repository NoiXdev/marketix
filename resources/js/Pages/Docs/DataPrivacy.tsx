import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import NoticeGenerator from '@/Pages/Docs/partials/NoticeGenerator';
import type { NoticeDefaults, NoticeParties, ProcessingSnapshot } from '@/types';
import { Cookie, Database, FileText, Info, Scale, ShieldOff, UserCheck } from 'lucide-react';
import { ReactNode } from 'react';

interface Props {
  appName: string;
  statsMonths: number;
  analyticsMonths: number;
  snapshot: ProcessingSnapshot;
  parties: NoticeParties;
  canEditClient: boolean;
  defaults: NoticeDefaults;
}

function Section({ icon: Icon, title, children }: { icon: typeof Database; title: string; children: ReactNode }) {
  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border p-6 shadow-[var(--shadow-sm)]">
      <h2 className="text-foreground mb-3 flex items-center gap-2 text-sm font-semibold">
        <Icon className="text-accent-soft-foreground h-4 w-4" />
        {title}
      </h2>
      <div className="text-muted space-y-3 text-sm leading-relaxed">{children}</div>
    </section>
  );
}

function Bullets({ items }: { items: string[] }) {
  return (
    <ul className="space-y-2">
      {items.map((text, i) => (
        <li key={i} className="flex gap-2">
          <span className="bg-accent mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full" />
          <span>{text}</span>
        </li>
      ))}
    </ul>
  );
}

export default function DataPrivacy({ appName, statsMonths, analyticsMonths, snapshot, parties, canEditClient, defaults }: Props) {
  const { t } = useTranslation();

  return (
    <AppLayout title={t('docs.privacy.title')}>
      <div className="mx-auto max-w-6xl px-8 py-8">
        <div className="mx-auto max-w-3xl">
          <PageHeader title={t('docs.privacy.title')} subtitle={t('docs.privacy.subtitle', { app: appName })} />

          {/* Disclaimer */}
          <div className="border-line bg-elevated text-muted mb-6 flex items-start gap-2 rounded-[var(--radius)] border px-4 py-3 text-xs">
            <Info className="text-subtle mt-0.5 h-3.5 w-3.5 shrink-0" />
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
                  t('docs.privacy.collect.title_hostname'),
                  t('docs.privacy.collect.engagement'),
                  t('docs.privacy.collect.search'),
                ]}
              />
            </Section>

            <Section icon={Cookie} title={t('docs.privacy.modes.heading')}>
              <Bullets
                items={[
                  t('docs.privacy.modes.cookieless'),
                  t('docs.privacy.modes.hybrid'),
                  t('docs.privacy.modes.cookie'),
                  t('docs.privacy.modes.dnt'),
                  t('docs.privacy.modes.links_note', { app: appName }),
                ]}
              />
            </Section>

            <Section icon={ShieldOff} title={t('docs.privacy.nocollect.heading', { app: appName })}>
              <Bullets items={[t('docs.privacy.nocollect.cookies'), t('docs.privacy.nocollect.raw_ip'), t('docs.privacy.nocollect.pii'), t('docs.privacy.nocollect.cross')]} />
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

            {/* The notice text itself is no longer boilerplate on this page.
                A single sentence cannot describe every project's processing,
                so the generator below composes it from this project's real
                configuration instead. */}
            <div className="border-line bg-elevated text-muted flex items-start gap-2 rounded-[var(--radius)] border px-4 py-3 text-xs">
              <FileText className="text-subtle mt-0.5 h-3.5 w-3.5 shrink-0" />
              <span>{t('docs.privacy.generator.pointer')}</span>
            </div>

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

        <NoticeGenerator snapshot={snapshot} parties={parties} canEditClient={canEditClient} defaults={defaults} />
      </div>
    </AppLayout>
  );
}
