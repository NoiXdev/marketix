import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Loader2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge({ hasPasskeys }: { hasPasskeys: boolean }) {
  const [useRecovery, setUseRecovery] = useState(false);
  const { data, setData, post, processing, errors } = useForm({ code: '', recovery_code: '' });

  const passkey = usePasskeyVerify({
    routes: {
      options: route('app.auth.two-factor.passkey-options'),
      submit: route('app.auth.two-factor.passkey'),
    },
    onSuccess: (response) => router.visit(response.redirect ?? '/'),
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route('app.auth.two-factor.store'));
  };

  const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

  return (
    <GuestLayout title="Two-factor authentication" description="Confirm access to your account">
      <Head title="Two-factor authentication" />
      <form onSubmit={submit} className="space-y-4">
        {!useRecovery ? (
          <div>
            <label htmlFor="code" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              Authentication code
            </label>
            <input
              id="code"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              autoFocus
              value={data.code}
              onChange={(e) => setData('code', e.target.value)}
              className={inputClass}
            />
            {errors.code && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.code}</p>}
          </div>
        ) : (
          <div>
            <label htmlFor="recovery_code" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              Recovery code
            </label>
            <input id="recovery_code" type="text" value={data.recovery_code} onChange={(e) => setData('recovery_code', e.target.value)} className={inputClass} />
            {errors.recovery_code && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.recovery_code}</p>}
          </div>
        )}

        <button
          type="submit"
          disabled={processing}
          className="flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
        >
          {processing && <Loader2 className="h-4 w-4 animate-spin" />}
          Verify
        </button>

        <button type="button" onClick={() => setUseRecovery((v) => !v)} className="w-full text-center text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
          {useRecovery ? 'Use an authentication code' : 'Use a recovery code'}
        </button>

        {hasPasskeys && passkey.isSupported && (
          <button
            type="button"
            onClick={() => void passkey.verify()}
            disabled={passkey.isLoading}
            className="w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            Use a passkey
          </button>
        )}
        {hasPasskeys && passkey.error && <p className="text-xs text-red-600 dark:text-red-400">{passkey.error}</p>}
      </form>
    </GuestLayout>
  );
}
