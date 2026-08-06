import { Button, Card, Field, Flash, FormSection, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import ProfileLayout from '@/Layouts/ProfileLayout';
import PasskeysSection from '@/Pages/Profile/partials/PasskeysSection';
import TwoFactorSection from '@/Pages/Profile/partials/TwoFactorSection';
import { useForm } from '@inertiajs/react';

interface ProfileUser {
  name: string;
  email: string;
}

interface Passkey {
  id: string;
  name: string;
  authenticator: string | null;
  last_used_at: string | null;
  created_at: string | null;
}

interface TwoFactorSetup {
  secretKey: string;
  qrCode: string;
}

interface Props {
  user: ProfileUser;
  twoFactorEnabled: boolean;
  twoFactorPending: boolean;
  twoFactorSetup: TwoFactorSetup | null;
  recoveryCodes: string[] | null;
  passkeys: Passkey[];
}

export default function ProfileEdit({
  user,
  twoFactorEnabled,
  twoFactorPending,
  twoFactorSetup,
  recoveryCodes,
  passkeys,
}: Props) {
  const { t } = useTranslation();
  const { data, setData, put, processing, errors, reset } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.profile.update'), {
      preserveScroll: true,
      onSuccess: () => reset('current_password', 'password', 'password_confirmation'),
    });
  }

  return (
    <ProfileLayout title={t('profile.title')}>
      <h1 className="mb-6 text-2xl font-bold text-foreground">{t('profile.title')}</h1>

      <Flash />

      <Card className="mb-8 space-y-3 p-4">
        <div>
          <p className="text-xs font-medium text-subtle">{t('profile.name')}</p>
          <p className="text-sm text-foreground">{user.name}</p>
        </div>
        <div>
          <p className="text-xs font-medium text-subtle">{t('profile.email')}</p>
          <p className="text-sm text-foreground">{user.email}</p>
        </div>
      </Card>

      <form onSubmit={submit}>
        <FormSection title={t('profile.password.heading')}>
          <Field label={t('profile.password.current')} htmlFor="current_password" error={errors.current_password}>
            <Input
              id="current_password"
              type="password"
              value={data.current_password}
              onChange={(e) => setData('current_password', e.target.value)}
            />
          </Field>
          <Field label={t('profile.password.new')} htmlFor="password" error={errors.password}>
            <Input
              id="password"
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
            />
          </Field>
          <Field label={t('profile.password.confirm')} htmlFor="password_confirmation">
            <Input
              id="password_confirmation"
              type="password"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
            />
          </Field>
          <Button type="submit" loading={processing}>
            {t('profile.password.submit')}
          </Button>
        </FormSection>
      </form>

      <div className="mt-8 space-y-6">
        <TwoFactorSection
          enabled={twoFactorEnabled}
          pending={twoFactorPending}
          setup={twoFactorSetup}
          recoveryCodes={recoveryCodes}
        />
        <PasskeysSection passkeys={passkeys} />
      </div>
    </ProfileLayout>
  );
}
