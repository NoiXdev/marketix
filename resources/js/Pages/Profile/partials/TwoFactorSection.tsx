import { router, useForm } from '@inertiajs/react';

interface TwoFactorSetup {
  secretKey: string;
  qrCode: string;
}

interface Props {
  enabled: boolean;
  pending: boolean;
  setup: TwoFactorSetup | null;
  recoveryCodes: string[] | null;
}

const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

export default function TwoFactorSection({ enabled, pending, setup, recoveryCodes }: Props) {
  const confirmForm = useForm({ code: '' });
  const passwordForm = useForm({ current_password: '' });

  const enable = () => router.post(route('app.profile.two-factor.enable'), {}, { preserveScroll: true });

  const confirm = (e: React.FormEvent) => {
    e.preventDefault();
    confirmForm.post(route('app.profile.two-factor.confirm'), {
      preserveScroll: true,
      onSuccess: () => confirmForm.reset('code'),
    });
  };

  const disable = (e: React.FormEvent) => {
    e.preventDefault();
    passwordForm.delete(route('app.profile.two-factor.disable'), {
      preserveScroll: true,
      onSuccess: () => passwordForm.reset(),
    });
  };

  const regenerate = (e: React.FormEvent) => {
    e.preventDefault();
    passwordForm.post(route('app.profile.two-factor.recovery-codes'), {
      preserveScroll: true,
      onSuccess: () => passwordForm.reset(),
    });
  };

  return (
    <section className="space-y-4 rounded-md border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
      <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Two-factor authentication</h2>

      {recoveryCodes && (
        <div className="rounded-md bg-amber-50 p-3 dark:bg-amber-900/20">
          <p className="text-xs font-medium text-amber-800 dark:text-amber-300">Store these recovery codes somewhere safe. They are shown only once.</p>
          <ul className="mt-2 grid grid-cols-2 gap-1 font-mono text-xs text-amber-900 dark:text-amber-200">
            {recoveryCodes.map((c) => (
              <li key={c}>{c}</li>
            ))}
          </ul>
        </div>
      )}

      {!enabled && !pending && (
        <button onClick={enable} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
          Enable two-factor authentication
        </button>
      )}

      {pending && setup && (
        <form onSubmit={confirm} className="space-y-3">
          <p className="text-sm text-slate-600 dark:text-slate-400">Scan this QR code with your authenticator app, then enter the generated code to finish.</p>
          <img src={setup.qrCode} alt="Two-factor QR code" className="h-44 w-44" />
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Or enter this key manually: <span className="font-mono">{setup.secretKey}</span>
          </p>
          <input
            type="text"
            inputMode="numeric"
            placeholder="123456"
            value={confirmForm.data.code}
            onChange={(e) => confirmForm.setData('code', e.target.value)}
            className={inputClass}
          />
          {confirmForm.errors.code && <p className="text-xs text-red-600">{confirmForm.errors.code}</p>}
          <button disabled={confirmForm.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
            Confirm
          </button>
        </form>
      )}

      {enabled && (
        <div className="space-y-3">
          <p className="text-sm text-green-700 dark:text-green-400">Two-factor authentication is enabled.</p>
          <form onSubmit={disable} className="flex flex-wrap items-end gap-2">
            <div className="flex-1">
              <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Current password</label>
              <input type="password" value={passwordForm.data.current_password} onChange={(e) => passwordForm.setData('current_password', e.target.value)} className={inputClass} />
              {passwordForm.errors.current_password && <p className="mt-1 text-xs text-red-600">{passwordForm.errors.current_password}</p>}
            </div>
            <button
              type="submit"
              disabled={passwordForm.processing}
              className="rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:text-red-400"
            >
              Disable
            </button>
            <button
              type="button"
              onClick={regenerate}
              disabled={passwordForm.processing}
              className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300"
            >
              Regenerate recovery codes
            </button>
          </form>
        </div>
      )}
    </section>
  );
}
