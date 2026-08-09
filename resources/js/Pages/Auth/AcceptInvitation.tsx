import { Button, Field, Input, LinkButton } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
  state: 'valid' | 'invalid' | 'wrong_user';
  token?: string;
  email?: string;
  projectName?: string;
  needsAccount?: boolean;
  authenticated?: boolean;
}

export default function AcceptInvitation({ state, token, email, projectName, needsAccount, authenticated }: Props) {
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    password: '',
    password_confirmation: '',
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    post(route('app.invitations.accept', { token }));
  }

  if (state === 'invalid') {
    return (
      <GuestLayout
        title={t('auth.invitation.invalid_title')}
        description={t('auth.invitation.invalid_description')}
      >
        <LinkButton href={route('app.auth.show-login')}>{t('auth.invitation.go_to_login')}</LinkButton>
      </GuestLayout>
    );
  }

  if (state === 'wrong_user') {
    return (
      <GuestLayout
        title={t('auth.invitation.wrong_account_title')}
        description={t('auth.invitation.wrong_account_description', { email: email ?? '' })}
      >
        <LinkButton href={route('app.auth.show-login')}>{t('auth.invitation.go_to_login')}</LinkButton>
      </GuestLayout>
    );
  }

  // Existing user, not logged in → prompt login first.
  if (!needsAccount && !authenticated) {
    return (
      <GuestLayout
        title={t('auth.invitation.title')}
        description={t('auth.invitation.login_prompt', { project: projectName ?? '', email: email ?? '' })}
      >
        <LinkButton href={route('app.auth.show-login')}>{t('auth.invitation.login_cta')}</LinkButton>
      </GuestLayout>
    );
  }

  // Logged-in existing user → one-click confirm.
  if (!needsAccount && authenticated) {
    return (
      <GuestLayout
        title={t('auth.invitation.title')}
        description={t('auth.invitation.confirm_prompt', { project: projectName ?? '', email: email ?? '' })}
      >
        <form onSubmit={submit}>
          <Button type="submit" loading={processing}>
            {t('auth.invitation.accept_cta')}
          </Button>
        </form>
      </GuestLayout>
    );
  }

  // New user → set name + password.
  return (
    <GuestLayout
      title={t('auth.invitation.title')}
      description={t('auth.invitation.signup_prompt', { project: projectName ?? '' })}
    >
      <form onSubmit={submit} className="space-y-4">
        <Field label={t('auth.invitation.email_label')} htmlFor="email">
          <Input id="email" value={email} disabled />
        </Field>
        <Field label={t('auth.invitation.name_label')} htmlFor="name" error={errors.name}>
          <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
        </Field>
        <Field label={t('auth.invitation.password_label')} htmlFor="password" error={errors.password}>
          <Input
            id="password"
            type="password"
            value={data.password}
            onChange={(e) => setData('password', e.target.value)}
          />
        </Field>
        <Field
          label={t('auth.invitation.confirm_password_label')}
          htmlFor="password_confirmation"
          error={errors.password_confirmation}
        >
          <Input
            id="password_confirmation"
            type="password"
            value={data.password_confirmation}
            onChange={(e) => setData('password_confirmation', e.target.value)}
          />
        </Field>
        <Button type="submit" loading={processing} className="w-full justify-center">
          {t('auth.invitation.submit')}
        </Button>
      </form>
    </GuestLayout>
  );
}
