import { Button, Card, Field, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
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

export default function TwoFactorSection({ enabled, pending, setup, recoveryCodes }: Props) {
  const { t } = useTranslation();
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

  const regenerate = () => {
    passwordForm.post(route('app.profile.two-factor.recovery-codes'), {
      preserveScroll: true,
      onSuccess: () => passwordForm.reset(),
    });
  };

  return (
    <Card className="space-y-4 p-4">
      <h2 className="text-foreground text-sm font-semibold">{t('profile.two_factor.heading')}</h2>

      {recoveryCodes && (
        <div className="bg-warning-soft rounded-[var(--radius-sm)] p-3">
          <p className="text-warning-foreground text-xs font-medium">{t('profile.two_factor.recovery_codes_notice')}</p>
          <ul className="text-warning-foreground mt-2 grid grid-cols-2 gap-1 font-mono text-xs">
            {recoveryCodes.map((c) => (
              <li key={c}>{c}</li>
            ))}
          </ul>
        </div>
      )}

      {!enabled && !pending && <Button onClick={enable}>{t('profile.two_factor.enable')}</Button>}

      {pending && setup && (
        <form onSubmit={confirm} className="space-y-3">
          <p className="text-muted text-sm">{t('profile.two_factor.scan_instruction')}</p>
          <img src={setup.qrCode} alt={t('profile.two_factor.qr_alt')} className="h-44 w-44" />
          <p className="text-subtle text-xs">
            {t('profile.two_factor.manual_key')} <span className="font-mono">{setup.secretKey}</span>
          </p>
          <Field htmlFor="two_factor_code" error={confirmForm.errors.code}>
            <Input
              id="two_factor_code"
              type="text"
              inputMode="numeric"
              placeholder="123456"
              value={confirmForm.data.code}
              onChange={(e) => confirmForm.setData('code', e.target.value)}
            />
          </Field>
          <Button type="submit" loading={confirmForm.processing}>
            {t('profile.two_factor.confirm')}
          </Button>
        </form>
      )}

      {enabled && (
        <div className="space-y-3">
          <p className="text-success-foreground text-sm">{t('profile.two_factor.enabled')}</p>
          <form onSubmit={disable} className="flex flex-col items-end gap-2">
            <div className="w-full flex-1">
              <Field label={t('profile.two_factor.current_password')} htmlFor="two_factor_current_password" error={passwordForm.errors.current_password}>
                <Input
                  id="two_factor_current_password"
                  type="password"
                  value={passwordForm.data.current_password}
                  onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                />
              </Field>
            </div>
            <div className="grid w-full grid-cols-1 sm:grid-cols-2 gap-2">
              <Button type="submit" size={'sm'} variant="danger" disabled={passwordForm.processing}>
                {t('profile.two_factor.disable')}
              </Button>
              <Button type="button" size={'sm'} variant="secondary" onClick={regenerate} disabled={passwordForm.processing}>
                {t('profile.two_factor.regenerate')}
              </Button>
            </div>
          </form>
        </div>
      )}
    </Card>
  );
}
