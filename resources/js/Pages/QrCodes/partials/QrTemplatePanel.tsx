import { Button, IconButton, Input } from '@/Components/ui';
import { QrStyle } from '@/data/qrTypes';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Check, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface QrTemplate {
  id: string;
  name: string;
  style: QrStyle;
}

interface Props {
  style: QrStyle;
  onApply: (style: QrStyle) => void;
}

type Status = { kind: 'success' | 'error'; message: string };

export default function QrTemplatePanel({ style, onApply }: Props) {
  const { t } = useTranslation();
  const currentProject = usePage<PageProps>().props.project;
  const [templates, setTemplates] = useState<QrTemplate[]>([]);
  const [saving, setSaving] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [status, setStatus] = useState<Status | null>(null);

  useEffect(() => {
    if (!currentProject) return;

    window.axios
      .get<{ templates: QrTemplate[] }>(route('app.project.qr-templates.index', { project: currentProject.id }))
      .then(res => setTemplates(res.data.templates))
      .catch(() => setStatus({ kind: 'error', message: t('qr.template.load_error') }));
  }, [currentProject]);

  const handleSave: FormEventHandler = e => {
    e.preventDefault();
    if (!currentProject || !name.trim() || saving) return;

    setSaving(true);
    setStatus(null);
    window.axios
      .post<{ template: QrTemplate }>(route('app.project.qr-templates.store', { project: currentProject.id }), {
        name: name.trim(),
        style,
      })
      .then(res => {
        setTemplates(prev => [res.data.template, ...prev]);
        setName('');
        setStatus({ kind: 'success', message: t('qr.template.saved') });
      })
      .catch(() => setStatus({ kind: 'error', message: t('qr.template.save_error') }))
      .finally(() => setSaving(false));
  };

  async function handleDelete(template: QrTemplate) {
    if (!currentProject) return;

    const confirmed = await confirmDelete({
      title: t('qr.template.delete_confirm_title'),
      text: t('qr.template.delete_confirm_text', { name: template.name }),
      confirmText: t('qr.template.delete_confirm_button'),
    });
    if (!confirmed) return;

    setDeletingId(template.id);
    setStatus(null);
    window.axios
      .delete(route('app.project.qr-templates.destroy', { project: currentProject.id, qrTemplate: template.id }))
      .then(() => {
        setTemplates(prev => prev.filter(tpl => tpl.id !== template.id));
        setStatus({ kind: 'success', message: t('qr.template.deleted') });
      })
      .catch(() => setStatus({ kind: 'error', message: t('qr.template.delete_error') }))
      .finally(() => setDeletingId(null));
  }

  return (
    <div className="space-y-3 border-t border-line pt-4">
      <h3 className="text-sm font-semibold text-foreground">{t('qr.template.title')}</h3>

      {status && (
        <p className={`text-xs ${status.kind === 'error' ? 'text-danger-foreground' : 'text-success-foreground'}`}>
          {status.message}
        </p>
      )}

      {templates.length === 0 ? (
        <p className="text-xs text-muted">{t('qr.template.empty')}</p>
      ) : (
        <ul className="space-y-1.5">
          {templates.map(template => (
            <li key={template.id} className="flex items-center justify-between gap-2 rounded-[var(--radius-sm)] border border-line bg-surface px-3 py-2">
              <span className="truncate text-sm text-foreground">{template.name}</span>
              <div className="flex items-center gap-1">
                <IconButton icon={Check} label={t('qr.template.apply')} onClick={() => onApply(template.style)} />
                <IconButton
                  icon={Trash2}
                  label={t('qr.template.delete')}
                  variant="danger"
                  spinning={deletingId === template.id}
                  disabled={deletingId === template.id}
                  onClick={() => handleDelete(template)}
                />
              </div>
            </li>
          ))}
        </ul>
      )}

      <form onSubmit={handleSave} className="flex items-center gap-2">
        <Input
          type="text"
          value={name}
          onChange={e => setName(e.target.value)}
          placeholder={t('qr.template.save_prompt')}
          aria-label={t('qr.template.save_prompt')}
        />
        <Button type="submit" variant="secondary" size="sm" loading={saving} disabled={!name.trim()}>
          {t('qr.template.save')}
        </Button>
      </form>
    </div>
  );
}
