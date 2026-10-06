import { BackLink, Button, Card, Field, IconButton, Input, PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { DEFAULT_STYLE, QrStyle } from '@/data/qrTypes';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { renderQr } from '@/lib/qr/render';
import { QrTemplate, createQrTemplate, deleteQrTemplate, listQrTemplates, updateQrTemplate } from '@/lib/qrTemplates';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';
import QrStyleForm from '../partials/QrStyleForm';

// Presets carry no content of their own, so the preview encodes a fixed sample.
const SAMPLE_DATA = 'https://example.com';

type Status = { kind: 'success' | 'error'; message: string };

export default function QrTemplatesIndex() {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const [templates, setTemplates] = useState<QrTemplate[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [style, setStyle] = useState<QrStyle>(DEFAULT_STYLE);
  const [saving, setSaving] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [status, setStatus] = useState<Status | null>(null);

  useEffect(() => {
    if (!project) return;

    listQrTemplates(project.id)
      .then(setTemplates)
      .catch(() => setStatus({ kind: 'error', message: t('qr.template.load_error') }));
  }, [project, t]);

  const preview = useMemo(() => renderQr(SAMPLE_DATA, style), [style]);

  function startNew() {
    setSelectedId(null);
    setName('');
    setStyle(DEFAULT_STYLE);
    setStatus(null);
  }

  function select(template: QrTemplate) {
    setSelectedId(template.id);
    setName(template.name);
    setStyle({ ...DEFAULT_STYLE, ...template.style });
    setStatus(null);
  }

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault();
    if (!project || !name.trim() || saving) return;

    setSaving(true);
    setStatus(null);

    const request = selectedId ? updateQrTemplate(project.id, selectedId, { name: name.trim(), style }) : createQrTemplate(project.id, name.trim(), style);

    request
      .then((template) => {
        setTemplates((prev) => (selectedId ? prev.map((tpl) => (tpl.id === template.id ? template : tpl)) : [template, ...prev]));
        setSelectedId(template.id);
        setStatus({ kind: 'success', message: t(selectedId ? 'qr.template.updated' : 'qr.template.saved') });
      })
      .catch(() => setStatus({ kind: 'error', message: t('qr.template.save_error') }))
      .finally(() => setSaving(false));
  };

  async function destroy(template: QrTemplate) {
    if (!project) return;

    const confirmed = await confirmDelete({
      title: t('qr.template.delete_confirm_title'),
      text: t('qr.template.delete_confirm_text', { name: template.name }),
      confirmText: t('qr.template.delete_confirm_button'),
    });
    if (!confirmed) return;

    setDeletingId(template.id);
    setStatus(null);
    deleteQrTemplate(project.id, template.id)
      .then(() => {
        setTemplates((prev) => prev.filter((tpl) => tpl.id !== template.id));
        if (selectedId === template.id) startNew();
        setStatus({ kind: 'success', message: t('qr.template.deleted') });
      })
      .catch(() => setStatus({ kind: 'error', message: t('qr.template.delete_error') }))
      .finally(() => setDeletingId(null));
  }

  return (
    <AppLayout title={t('qr.template.page_title')}>
      <div className="px-8 py-8">
        <BackLink href={route('app.project.qrcodes.index', { project: project!.id })}>{t('qr.template.back')}</BackLink>
        <div className="mt-3">
          <PageHeader
            title={t('qr.template.page_title')}
            subtitle={t('qr.template.page_subtitle')}
            action={
              <Button type="button" variant="secondary" onClick={startNew}>
                <Plus className="h-4 w-4" /> {t('qr.template.new')}
              </Button>
            }
          />
        </div>

        {status && <p className={`mb-4 text-sm ${status.kind === 'error' ? 'text-danger-foreground' : 'text-success-foreground'}`}>{status.message}</p>}

        <div className="grid gap-6 lg:grid-cols-[18rem_1fr]">
          <Card className="h-fit p-3">
            {templates.length === 0 ? (
              <p className="text-muted px-2 py-3 text-sm">{t('qr.template.empty')}</p>
            ) : (
              <ul className="space-y-1">
                {templates.map((template) => (
                  <li key={template.id}>
                    <div
                      className={`flex items-center justify-between gap-2 rounded-[var(--radius-sm)] border px-3 py-2 ${
                        selectedId === template.id ? 'border-accent bg-accent-soft' : 'hover:bg-elevated border-transparent'
                      }`}
                    >
                      <button type="button" onClick={() => select(template)} className="text-foreground min-w-0 flex-1 truncate text-left text-sm">
                        {template.name}
                      </button>
                      <IconButton
                        icon={Trash2}
                        label={t('qr.template.delete')}
                        variant="danger"
                        spinning={deletingId === template.id}
                        disabled={deletingId === template.id}
                        onClick={() => destroy(template)}
                      />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card className="p-5">
            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="grid gap-6 md:grid-cols-[1fr_auto]">
                <Field label={t('qr.template.name_label')} htmlFor="preset-name">
                  <Input id="preset-name" type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder={t('qr.template.save_prompt')} />
                </Field>
                <div
                  className="border-line w-[160px] shrink-0 overflow-hidden rounded-[12px] border [&>svg]:block [&>svg]:h-auto [&>svg]:w-full"
                  style={{ background: style.background }}
                  dangerouslySetInnerHTML={{ __html: preview.svg }}
                />
              </div>

              <QrStyleForm style={style} onChange={setStyle} />

              <div className="border-line flex items-center gap-3 border-t pt-4">
                <Button type="submit" loading={saving} disabled={!name.trim()}>
                  {selectedId ? t('qr.template.update') : t('qr.template.save')}
                </Button>
                <p className="text-muted text-xs">{t('qr.template.update_hint')}</p>
              </div>
            </form>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
