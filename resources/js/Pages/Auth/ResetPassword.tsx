import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('app.auth.reset'));
    };

    return (
        <GuestLayout
            title={t('auth.reset.title')}
            description={t('auth.reset.description')}
        >
            <Head title={t('auth.reset.head')} />

            <form onSubmit={submit} className="space-y-4">
                <Field label={t('auth.reset.email')} htmlFor="email">
                    <Input id="email" type="email" value={data.email} readOnly />
                </Field>

                <Field label={t('auth.reset.password')} htmlFor="password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoFocus
                    />
                </Field>

                <Field
                    label={t('auth.reset.confirm')}
                    htmlFor="password_confirmation"
                    error={errors.password_confirmation}
                >
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                </Field>

                <Button type="submit" loading={processing} className="w-full justify-center">
                    {t('auth.reset.submit')}
                </Button>
            </form>
        </GuestLayout>
    );
}
