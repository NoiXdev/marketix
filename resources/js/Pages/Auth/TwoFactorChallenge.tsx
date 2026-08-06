import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge({ hasPasskeys }: { hasPasskeys: boolean }) {
  const { t } = useTranslation();
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

  return (
    <GuestLayout
      title={t('auth.two_factor_challenge.title')}
      description={t('auth.two_factor_challenge.description')}
    >
      <Head title={t('auth.two_factor_challenge.head')} />
      <form onSubmit={submit} className="space-y-4">
        {!useRecovery ? (
          <Field label={t('auth.two_factor_challenge.code_label')} htmlFor="code" error={errors.code}>
            <Input
              id="code"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              autoFocus
              value={data.code}
              onChange={(e) => setData('code', e.target.value)}
            />
          </Field>
        ) : (
          <Field
            label={t('auth.two_factor_challenge.recovery_code_label')}
            htmlFor="recovery_code"
            error={errors.recovery_code}
          >
            <Input
              id="recovery_code"
              type="text"
              value={data.recovery_code}
              onChange={(e) => setData('recovery_code', e.target.value)}
            />
          </Field>
        )}

        <Button type="submit" loading={processing} className="w-full justify-center">
          {t('auth.two_factor_challenge.submit')}
        </Button>

        <button
          type="button"
          onClick={() => setUseRecovery((v) => !v)}
          className="w-full text-center text-xs text-accent-soft-foreground hover:underline"
        >
          {useRecovery
            ? t('auth.two_factor_challenge.use_code')
            : t('auth.two_factor_challenge.use_recovery')}
        </button>

        {hasPasskeys && passkey.isSupported && (
          <Button
            type="button"
            variant="secondary"
            onClick={() => void passkey.verify()}
            disabled={passkey.isLoading}
            className="w-full justify-center"
          >
            {t('auth.two_factor_challenge.use_passkey')}
          </Button>
        )}
        {hasPasskeys && passkey.error && <p className="text-xs text-danger-foreground">{passkey.error}</p>}
      </form>
    </GuestLayout>
  );
}
