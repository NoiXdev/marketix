import { Button, Field, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Description, Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { FolderInput } from 'lucide-react';
import { FormEvent, useId, useRef, useState } from 'react';

type DomainOption = { id: string; name: string };

export function useMoveTargets() {
  const { projects, project } = usePage<PageProps>().props;
  return projects.filter((p) => p.id !== project?.id);
}

type Props = {
  name: string;
  action: string;
  hint: string;
  withDomain?: boolean;
  onClose: () => void;
};

export function MoveToProjectSection(props: Omit<Props, 'onClose'>) {
  const { t } = useTranslation();
  const targets = useMoveTargets();
  const [open, setOpen] = useState(false);

  if (targets.length === 0) return null;

  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border p-5">
      <h2 className="text-foreground text-sm font-semibold">{t('move.action')}</h2>
      <p className="text-muted mt-1 text-xs leading-relaxed">{props.hint}</p>
      <Button variant="secondary" size="sm" className="mt-3" onClick={() => setOpen(true)}>
        <FolderInput className="h-3.5 w-3.5" />
        {t('move.open')}
      </Button>
      {open && <MoveToProjectDialog {...props} onClose={() => setOpen(false)} />}
    </section>
  );
}

export default function MoveToProjectDialog({ name, action, hint, withDomain = false, onClose }: Props) {
  const { t } = useTranslation();
  const targets = useMoveTargets();
  const form = useForm({ project_id: '', domain_id: '' });
  const [open, setOpen] = useState(true);
  const [confirmation, setConfirmation] = useState('');
  const [domains, setDomains] = useState<DomainOption[] | null>(null);
  const requestedProject = useRef('');
  const projectId = useId();
  const domainId = useId();
  const confirmId = useId();
  const [promptBefore, promptAfter = ''] = t('common.dialog.type_to_confirm').split(':value');

  const ready = form.data.project_id !== '' && (!withDomain || form.data.domain_id !== '') && confirmation === name;

  function close() {
    if (!open || form.processing) return;
    setOpen(false);
    window.setTimeout(onClose, 200);
  }

  function selectProject(project: string) {
    form.setData({ project_id: project, domain_id: '' });
    form.clearErrors();
    if (!withDomain) return;

    requestedProject.current = project;
    setDomains(null);
    if (project === '') return;

    window.axios.get<{ domains: DomainOption[] }>(route('app.project.domains.options', { project })).then(({ data }) => {
      if (requestedProject.current !== project) return;
      setDomains(data.domains);
      form.setData((previous) => ({ ...previous, domain_id: data.domains[0]?.id ?? '' }));
    });
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    if (!ready) return;
    form.post(action, { preserveScroll: true, preserveState: 'errors' });
  }

  return (
    <Dialog open={open} onClose={close} className="relative z-[100]">
      <DialogBackdrop transition className="fixed inset-0 bg-[rgb(10_14_22/0.45)] backdrop-blur-[2px] transition-opacity duration-150 ease-out data-[closed]:opacity-0" />
      <div className="fixed inset-0 flex items-end justify-center overflow-y-auto p-4 sm:items-center">
        <DialogPanel
          as="form"
          onSubmit={submit}
          transition
          className="border-line bg-surface w-full max-w-md overflow-hidden rounded-[var(--radius)] border shadow-[var(--shadow)] transition duration-150 ease-out data-[closed]:translate-y-2 data-[closed]:scale-[0.98] data-[closed]:opacity-0"
        >
          <div className="flex gap-4 p-5">
            <span className="bg-accent-soft text-accent-soft-foreground grid h-10 w-10 shrink-0 place-items-center rounded-full">
              <FolderInput className="h-5 w-5" />
            </span>
            <div className="min-w-0 flex-1 space-y-4 pt-0.5">
              <div>
                <DialogTitle className="text-foreground text-base font-semibold break-words">{t('move.title', { name })}</DialogTitle>
                <Description className="text-muted mt-1.5 text-sm leading-relaxed">{hint}</Description>
              </div>

              <Field label={t('move.project')} htmlFor={projectId} error={form.errors.project_id}>
                <Select id={projectId} autoFocus value={form.data.project_id} onChange={(event) => selectProject(event.target.value)}>
                  <option value="">{t('move.project_placeholder')}</option>
                  {targets.map((project) => (
                    <option key={project.id} value={project.id}>
                      {project.name}
                    </option>
                  ))}
                </Select>
              </Field>

              {withDomain && form.data.project_id !== '' && (
                <Field
                  label={t('move.domain')}
                  htmlFor={domainId}
                  error={form.errors.domain_id}
                  hint={domains === null ? t('move.domains_loading') : domains.length === 0 ? t('move.no_domains') : undefined}
                >
                  <Select id={domainId} value={form.data.domain_id} disabled={!domains?.length} onChange={(event) => form.setData('domain_id', event.target.value)}>
                    <option value="">{t('move.domain_placeholder')}</option>
                    {domains?.map((domain) => (
                      <option key={domain.id} value={domain.id}>
                        {domain.name}
                      </option>
                    ))}
                  </Select>
                </Field>
              )}

              <div className="space-y-1.5">
                <label htmlFor={confirmId} className="text-foreground block text-sm">
                  {promptBefore}
                  <span className="bg-elevated rounded px-1.5 py-0.5 font-mono text-[13px] font-semibold break-all">{name}</span>
                  {promptAfter}
                </label>
                <Input
                  id={confirmId}
                  autoComplete="off"
                  autoCapitalize="off"
                  autoCorrect="off"
                  spellCheck={false}
                  value={confirmation}
                  onChange={(event) => setConfirmation(event.target.value)}
                />
              </div>
            </div>
          </div>
          <div className="border-line bg-elevated/60 flex flex-col-reverse gap-2 border-t px-5 py-3 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={close}>
              {t('common.actions.cancel')}
            </Button>
            <Button type="submit" disabled={!ready} loading={form.processing}>
              {t('move.submit')}
            </Button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
