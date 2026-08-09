import { Button, Checkbox, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Loader2 } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function Login({ status }: { status?: string }) {
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false as boolean,
  });

  const passkey = usePasskeyVerify({
    onSuccess: (response) => router.visit(response.redirect ?? '/'),
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route('app.auth.login'));
  };

  return (
    <GuestLayout title={t('auth.login.title')} description={t('auth.login.description')}>
      <Head title={t('auth.login.head')} />

      {status && <div className="mb-4 rounded-[var(--radius-sm)] bg-success-soft px-4 py-3 text-sm text-success-foreground">{status}</div>}

      <form onSubmit={submit} className="space-y-5">
        <Field label={t('auth.login.email')} htmlFor="email" error={errors.email}>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            value={data.email}
            onChange={(e) => setData('email', e.target.value)}
            placeholder="you@example.com"
          />
        </Field>

        <Field label={t('auth.login.password')} htmlFor="password" error={errors.password}>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            value={data.password}
            onChange={(e) => setData('password', e.target.value)}
          />
        </Field>

        <div className="flex items-center justify-between">
          <label className="flex items-center gap-2 text-sm text-muted">
            <Checkbox checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
            {t('auth.login.remember')}
          </label>
          <Link href={route('app.auth.show-forgot')} className="text-sm font-semibold text-accent-soft-foreground hover:underline">
            {t('auth.login.forgot')}
          </Link>
        </div>

        <Button type="submit" disabled={processing} className="w-full justify-center py-2.5">
          {processing && <Loader2 className="h-4 w-4 animate-spin" />}
          {t('auth.login.submit')}
        </Button>
      </form>

      {passkey.isSupported && (
        <div className="mt-4">
          <Button type="button" variant="secondary" onClick={() => void passkey.verify()} disabled={passkey.isLoading} className="w-full justify-center">
            {t('auth.login.passkey')}
          </Button>
          {passkey.error && <p className="mt-1 text-xs text-danger-foreground">{passkey.error}</p>}
        </div>
      )}
    </GuestLayout>
  );
}
