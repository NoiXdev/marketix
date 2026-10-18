import { useTranslation } from '@/lib/i18n';
import { buildNoticeQuery, controllerFor, GeneratorState, initialGeneratorState, noticeQueryString } from '@/lib/privacyNotice';
import NoticeForm from '@/Pages/Docs/partials/NoticeForm';
import NoticePreview from '@/Pages/Docs/partials/NoticePreview';
import type { ControllerRole, GeneratedNotice, NoticeDefaults, NoticeParties, PageProps, ProcessingSnapshot } from '@/types';
import { usePage } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useEffect, useState } from 'react';

const DEBOUNCE_MS = 300;

interface NoticeGeneratorProps {
  snapshot: ProcessingSnapshot;
  parties: NoticeParties;
  canEditClient: boolean;
  defaults: NoticeDefaults;
}

export default function NoticeGenerator({ snapshot, parties, canEditClient, defaults }: NoticeGeneratorProps) {
  const { t } = useTranslation();
  const { project, availableLocales } = usePage<PageProps>().props;
  const [state, setState] = useState<GeneratorState>(() => initialGeneratorState(defaults, parties, snapshot));
  const [notice, setNotice] = useState<GeneratedNotice | null>(null);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);

  const projectId = project!.id;

  useEffect(() => {
    // The previous request is aborted on every change, so a slow response
    // can never overwrite the notice for newer answers.
    const aborter = new AbortController();

    const timer = setTimeout(() => {
      setLoading(true);

      const url = `${route('app.project.docs.privacy.notice', { project: projectId })}?${noticeQueryString(buildNoticeQuery(state))}`;

      fetch(url, { signal: aborter.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`Unexpected status ${response.status}`);
          }

          return response.json() as Promise<GeneratedNotice>;
        })
        .then((body) => {
          setNotice(body);
          setFailed(false);
          setLoading(false);
        })
        .catch(() => {
          if (aborter.signal.aborted) {
            return;
          }

          setFailed(true);
          setLoading(false);
        });
    }, DEBOUNCE_MS);

    return () => {
      clearTimeout(timer);
      aborter.abort();
    };
  }, [state, projectId]);

  function change(patch: Partial<GeneratorState>) {
    setState((current) => ({ ...current, ...patch }));
  }

  /** The role decides who the controller is, so its details follow it. */
  function changeRole(role: ControllerRole) {
    const controller = controllerFor(role, parties);

    change({ role, controllerName: controller.name, controllerAddress: controller.address });
  }

  return (
    <section className="mt-10">
      <div className="mb-5">
        <h2 className="text-foreground flex items-center gap-2 text-lg font-semibold">
          <Wand2 className="text-accent-soft-foreground h-4 w-4" />
          {t('docs.privacy.generator.heading')}
        </h2>
        <p className="text-muted mt-1 text-sm">{t('docs.privacy.generator.intro')}</p>
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-2">
        <NoticeForm state={state} snapshot={snapshot} canEditClient={canEditClient} locales={availableLocales} onChange={change} onRoleChange={changeRole} />
        <div className="min-w-0 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto">
          <NoticePreview notice={notice} loading={loading} failed={failed} />
        </div>
      </div>
    </section>
  );
}
