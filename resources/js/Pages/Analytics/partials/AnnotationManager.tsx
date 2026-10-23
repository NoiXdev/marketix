import { Button, Field, IconButton, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import { AnnotationDetails } from '@/Pages/Analytics/partials/ChartAnnotations';
import { toIsoDate } from '@/Pages/Analytics/partials/period';
import { Annotation, AnnotationTarget } from '@/Pages/Analytics/types';
import { Description, Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { router, useForm } from '@inertiajs/react';
import { Flag, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useId, useState } from 'react';

const TITLE_MAX = 120;
const DESCRIPTION_MAX = 2000;

const textareaCls =
  'w-full resize-none rounded-[var(--radius-sm)] border border-line-strong bg-surface px-3 py-2 text-sm text-foreground transition-colors placeholder:text-subtle focus-visible:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';

const footerCls = 'border-line bg-elevated/60 flex flex-col-reverse gap-2 border-t px-5 py-3 sm:flex-row sm:items-center sm:justify-end';

function Heading({ title, text }: { title: string; text: string }) {
  return (
    <div className="flex gap-4">
      <span className="bg-accent-soft text-accent-soft-foreground grid h-10 w-10 shrink-0 place-items-center rounded-full">
        <Flag className="h-5 w-5" />
      </span>
      <div className="min-w-0 flex-1 pt-0.5">
        <DialogTitle className="text-foreground text-base font-semibold break-words">{title}</DialogTitle>
        <Description className="text-muted mt-1.5 text-sm leading-relaxed">{text}</Description>
      </div>
    </div>
  );
}

function AnnotationList({ onCreate, onEdit, onClose }: { onCreate: () => void; onEdit: (annotation: Annotation) => void; onClose: () => void }) {
  const { t } = useTranslation();
  const { annotations } = useAnalytics();
  const sorted = [...annotations].sort((a, b) => b.date.localeCompare(a.date));

  return (
    <>
      <div className="p-5">
        <Heading title={t('analytics.dashboard.annotations.title')} text={t('analytics.dashboard.annotations.intro')} />
      </div>
      <div className="border-line max-h-[55vh] overflow-y-auto border-t">
        {sorted.length === 0 ? (
          <p className="text-muted px-5 py-8 text-center text-sm">{t('analytics.dashboard.annotations.empty')}</p>
        ) : (
          <ul className="divide-line divide-y">
            {sorted.map((annotation) => (
              <li key={annotation.id} className="px-5 py-3">
                <AnnotationDetails
                  annotation={annotation}
                  onNavigate={onClose}
                  actions={<IconButton icon={Pencil} label={t('common.actions.edit')} onClick={() => onEdit(annotation)} />}
                />
              </li>
            ))}
          </ul>
        )}
      </div>
      <div className={footerCls}>
        <Button variant="secondary" onClick={onClose}>
          {t('common.actions.close')}
        </Button>
        <Button onClick={onCreate}>
          <Plus className="h-4 w-4" />
          {t('analytics.dashboard.annotations.create')}
        </Button>
      </div>
    </>
  );
}

function AnnotationForm({ annotation, onDone, onCancel }: { annotation: Annotation | null; onDone: () => void; onCancel: () => void }) {
  const { t } = useTranslation();
  const { projectId, site } = useAnalytics();
  const form = useForm({ date: annotation?.date ?? toIsoDate(new Date()), title: annotation?.title ?? '', description: annotation?.description ?? '' });
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const dateId = useId();
  const titleId = useId();
  const descriptionId = useId();
  const params = { project: projectId, site: site.id };

  function submit(event: FormEvent) {
    event.preventDefault();
    const options = { preserveScroll: true, preserveState: true, onSuccess: onDone };
    if (annotation) form.put(route('app.project.analytics.annotations.update', { ...params, annotation: annotation.id }), options);
    else form.post(route('app.project.analytics.annotations.store', params), options);
  }

  function remove() {
    if (!annotation) return;
    if (!confirmingDelete) {
      setConfirmingDelete(true);
      return;
    }
    router.delete(route('app.project.analytics.annotations.destroy', { ...params, annotation: annotation.id }), {
      preserveScroll: true,
      preserveState: true,
      onStart: () => setDeleting(true),
      onFinish: () => setDeleting(false),
      onSuccess: onDone,
    });
  }

  return (
    <form onSubmit={submit}>
      <div className="space-y-4 p-5">
        <Heading
          title={t(annotation ? 'analytics.dashboard.annotations.edit_title' : 'analytics.dashboard.annotations.create_title')}
          text={t('analytics.dashboard.annotations.intro')}
        />
        <div className="space-y-4 sm:pl-14">
          <Field label={t('analytics.dashboard.annotations.date')} htmlFor={dateId} error={form.errors.date}>
            <Input id={dateId} type="date" required value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} className="sm:w-48" />
          </Field>
          <Field label={t('analytics.dashboard.annotations.name')} htmlFor={titleId} error={form.errors.title}>
            <Input
              id={titleId}
              autoFocus
              required
              maxLength={TITLE_MAX}
              value={form.data.title}
              placeholder={t('analytics.dashboard.annotations.name_placeholder')}
              onChange={(event) => form.setData('title', event.target.value)}
            />
          </Field>
          <Field label={t('analytics.dashboard.annotations.description')} htmlFor={descriptionId} error={form.errors.description}>
            <textarea
              id={descriptionId}
              rows={3}
              maxLength={DESCRIPTION_MAX}
              value={form.data.description}
              placeholder={t('analytics.dashboard.annotations.description_placeholder')}
              onChange={(event) => form.setData('description', event.target.value)}
              className={textareaCls}
            />
          </Field>
        </div>
      </div>
      <div className={footerCls}>
        {annotation && (
          <Button variant={confirmingDelete ? 'destructive' : 'danger'} onClick={remove} loading={deleting} className="sm:mr-auto">
            {!deleting && <Trash2 className="h-4 w-4" />}
            {confirmingDelete ? t('analytics.dashboard.annotations.delete_confirm') : t('common.actions.delete')}
          </Button>
        )}
        <Button variant="secondary" onClick={onCancel}>
          {t('common.actions.cancel')}
        </Button>
        <Button type="submit" loading={form.processing} disabled={deleting}>
          {t('common.actions.save')}
        </Button>
      </div>
    </form>
  );
}

export default function AnnotationManager({ target, onClose }: { target: AnnotationTarget; onClose: () => void }) {
  const [open, setOpen] = useState(true);
  const [view, setView] = useState<AnnotationTarget>(target);
  const fromList = target === 'list';

  function close() {
    if (!open) return;
    setOpen(false);
    window.setTimeout(onClose, 200);
  }

  function back() {
    if (fromList) setView('list');
    else close();
  }

  return (
    <Dialog open={open} onClose={close} className="relative z-[100]">
      <DialogBackdrop transition className="fixed inset-0 bg-[rgb(10_14_22/0.45)] backdrop-blur-[2px] transition-opacity duration-150 ease-out data-[closed]:opacity-0" />
      <div className="fixed inset-0 flex items-end justify-center overflow-y-auto p-4 sm:items-center">
        <DialogPanel
          transition
          className="border-line bg-surface w-full max-w-lg overflow-hidden rounded-[var(--radius)] border shadow-[var(--shadow)] transition duration-150 ease-out data-[closed]:translate-y-2 data-[closed]:scale-[0.98] data-[closed]:opacity-0"
        >
          {view === 'list' ? (
            <AnnotationList onCreate={() => setView('new')} onEdit={setView} onClose={close} />
          ) : (
            <AnnotationForm key={view === 'new' ? 'new' : view.id} annotation={view === 'new' ? null : view} onDone={back} onCancel={back} />
          )}
        </DialogPanel>
      </div>
    </Dialog>
  );
}
