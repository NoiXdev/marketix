import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForcePasswordChange() {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('app.password.change.update'));
    };

    return (
        <GuestLayout
            title={t('auth.force_password_change.title')}
            description={t('auth.force_password_change.description')}
        >
            <Head title={t('auth.force_password_change.head')} />

            <form onSubmit={submit} className="space-y-4">
                <Field
                    label={t('auth.force_password_change.current_password')}
                    htmlFor="current_password"
                    error={errors.current_password}
                >
                    <Input
                        id="current_password"
                        type="password"
                        autoComplete="current-password"
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        autoFocus
                    />
                </Field>

                <Field
                    label={t('auth.force_password_change.password')}
                    htmlFor="password"
                    error={errors.password}
                >
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </Field>

                <Field
                    label={t('auth.force_password_change.confirm')}
                    htmlFor="password_confirmation"
                    error={errors.password_confirmation}
                >
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </Field>

                <Button type="submit" loading={processing} className="w-full justify-center">
                    {t('auth.force_password_change.submit')}
                </Button>
            </form>
        </GuestLayout>
    );
}
