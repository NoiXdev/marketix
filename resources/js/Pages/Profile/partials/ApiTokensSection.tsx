import { Button, Card, Field, IconButton, Input } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';
import { Check, Copy, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ApiToken {
  id: string;
  name: string;
  last_used_at: string | null;
  created_at: string | null;
}

function CopyButton({ text }: { text: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);
  function copy() {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }
  return (
    <button
      type="button"
      onClick={copy}
      title={copied ? t('profile.tokens.copy_copied') : t('profile.tokens.copy_idle')}
      className={`rounded-md p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
        copied ? 'text-success-foreground' : 'text-subtle hover:bg-elevated hover:text-foreground'
      }`}
    >
      {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
    </button>
  );
}

export default function ApiTokensSection({ tokens, newToken }: { tokens: ApiToken[]; newToken?: string | null }) {
  const { t } = useTranslation();
  const { data, setData, post, processing, errors, reset } = useForm({ name: '' });

  const endpoint = `${typeof window !== 'undefined' ? window.location.origin : ''}/mcp/marketix`;
  const configSnippet = `{
  "mcpServers": {
    "marketix": {
      "command": "npx",
      "args": [
        "mcp-remote",
        "${endpoint}",
        "--header",
        "Authorization: Bearer <token>"
      ]
    }
  }
}`;

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('app.profile.tokens.store'), {
      preserveScroll: true,
      onSuccess: () => reset(),
    });
  };

  const revoke = async (id: string) => {
    const confirmed = await confirmDelete({
      title: t('profile.tokens.confirm_title'),
      text: t('profile.tokens.confirm_text'),
      confirmText: t('profile.tokens.confirm_button'),
    });
    if (confirmed) {
      router.delete(route('app.profile.tokens.destroy', { token: id }), { preserveScroll: true });
    }
  };

  return (
    <Card className="space-y-4 p-4">
      <div>
        <h2 className="text-sm font-semibold text-foreground">{t('profile.tokens.heading')}</h2>
        <p className="mt-1 text-xs text-subtle">{t('profile.tokens.description')}</p>
      </div>

      <div className="space-y-2 rounded-[var(--radius-sm)] bg-elevated p-3 text-xs text-muted">
        <div className="flex items-start justify-between gap-2">
          <p className="min-w-0 break-all">
            {t('profile.tokens.endpoint_label')} <code className="text-foreground">{endpoint}</code>
          </p>
          <CopyButton text={endpoint} />
        </div>
        <p>
          {t('profile.tokens.auth_header_label')} <code className="text-foreground">Authorization: Bearer &lt;token&gt;</code>
        </p>
        <div>
          <div className="mb-1 flex items-center justify-between gap-2">
            <span>{t('profile.tokens.config_label')}</span>
            <CopyButton text={configSnippet} />
          </div>
          <pre className="overflow-x-auto rounded-[var(--radius-sm)] bg-foreground p-3 text-xs text-canvas">
            <code>{configSnippet}</code>
          </pre>
        </div>
        <p>{t('profile.tokens.mcp_remote_note')}</p>
      </div>

      {newToken && (
        <div className="flex items-center justify-between gap-3 rounded-[var(--radius-sm)] bg-warning-soft p-3">
          <div className="min-w-0">
            <p className="text-xs font-medium text-warning-foreground">{t('profile.tokens.one_time_warning')}</p>
            <code className="block break-all text-xs text-warning-foreground">{newToken}</code>
          </div>
          <CopyButton text={newToken} />
        </div>
      )}

      {tokens.length > 0 ? (
        <ul className="divide-y divide-line">
          {tokens.map((tok) => (
            <li key={tok.id} className="flex items-center justify-between py-2">
              <div>
                <p className="text-sm text-foreground">{tok.name}</p>
                <p className="text-xs text-subtle">
                  {t('profile.tokens.created')} {tok.created_at} ·{' '}
                  {tok.last_used_at ? `${t('profile.tokens.last_used')} ${tok.last_used_at}` : t('profile.tokens.never')}
                </p>
              </div>
              <IconButton icon={Trash2} label={t('profile.tokens.revoke')} variant="danger" onClick={() => void revoke(tok.id)} />
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-xs text-subtle">{t('profile.tokens.empty')}</p>
      )}

      <form onSubmit={submit} className="flex items-end gap-2">
        <div className="flex-1">
          <Field label={t('profile.tokens.name_label')} htmlFor="token_name" error={errors.name}>
            <Input
              id="token_name"
              type="text"
              placeholder={t('profile.tokens.name_placeholder')}
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
            />
          </Field>
        </div>
        <Button type="submit" loading={processing} disabled={!data.name.trim()}>
          {t('profile.tokens.create')}
        </Button>
      </form>
    </Card>
  );
}
