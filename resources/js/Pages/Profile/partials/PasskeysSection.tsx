import { Button, Card, Field, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';

interface Passkey {
  id: string;
  name: string;
  authenticator: string | null;
  last_used_at: string | null;
  created_at: string | null;
}

export default function PasskeysSection({ passkeys }: { passkeys: Passkey[] }) {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
      setName('');
      router.reload({ only: ['passkeys'] });
    },
  });

  const add = (e: React.FormEvent) => {
    e.preventDefault();
    if (name.trim()) {
      void register(name.trim());
    }
  };

  const remove = (id: string) => {
    router.delete(route('passkey.destroy', { passkey: id }), { preserveScroll: true });
  };

  return (
    <Card className="space-y-4 p-4">
      <h2 className="text-sm font-semibold text-foreground">{t('profile.passkeys.heading')}</h2>

      {!isSupported && <p className="text-xs text-subtle">{t('profile.passkeys.not_supported')}</p>}

      {passkeys.length > 0 && (
        <ul className="divide-y divide-line">
          {passkeys.map((p) => (
            <li key={p.id} className="flex items-center justify-between py-2">
              <div>
                <p className="text-sm text-foreground">{p.name}</p>
                <p className="text-xs text-subtle">
                  {p.authenticator ?? t('profile.passkeys.security_key')}
                  {p.last_used_at ? ` · ${t('profile.passkeys.last_used')} ${p.last_used_at}` : ''}
                </p>
              </div>
              <Button size="sm" variant="danger" onClick={() => remove(p.id)}>
                {t('profile.passkeys.remove')}
              </Button>
            </li>
          ))}
        </ul>
      )}

      {isSupported && (
        <form onSubmit={add} className="flex items-end gap-2">
          <div className="flex-1">
            <Field label={t('profile.passkeys.name_label')} htmlFor="passkey_name">
              <Input
                id="passkey_name"
                type="text"
                placeholder="e.g. MacBook Touch ID"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </Field>
          </div>
          <Button type="submit" loading={isLoading} disabled={!name.trim()}>
            {t('profile.passkeys.add')}
          </Button>
        </form>
      )}

      {error && <p className="text-xs text-danger-foreground">{error}</p>}
    </Card>
  );
}
