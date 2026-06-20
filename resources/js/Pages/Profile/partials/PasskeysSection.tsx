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

  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  return (
    <section className="space-y-4 rounded-md border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{t('profile.passkeys.heading')}</h2>

      {!isSupported && <p className="text-xs text-slate-500 dark:text-slate-400">{t('profile.passkeys.not_supported')}</p>}

      {passkeys.length > 0 && (
        <ul className="divide-y divide-slate-200 dark:divide-slate-800">
          {passkeys.map((p) => (
            <li key={p.id} className="flex items-center justify-between py-2">
              <div>
                <p className="text-sm text-slate-900 dark:text-white">{p.name}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  {p.authenticator ?? t('profile.passkeys.security_key')}
                  {p.last_used_at ? ` · ${t('profile.passkeys.last_used')} ${p.last_used_at}` : ''}
                </p>
              </div>
              <button onClick={() => remove(p.id)} className="text-xs font-semibold text-red-600 hover:text-red-500">
                {t('profile.passkeys.remove')}
              </button>
            </li>
          ))}
        </ul>
      )}

      {isSupported && (
        <form onSubmit={add} className="flex items-end gap-2">
          <div className="flex-1">
            <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{t('profile.passkeys.name_label')}</label>
            <input type="text" placeholder="e.g. MacBook Touch ID" value={name} onChange={(e) => setName(e.target.value)} className={inputClass} />
          </div>
          <button
            type="submit"
            disabled={isLoading || !name.trim()}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            {t('profile.passkeys.add')}
          </button>
        </form>
      )}

      {error && <p className="text-xs text-red-600">{error}</p>}
    </section>
  );
}
