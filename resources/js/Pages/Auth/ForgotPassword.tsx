import { Button, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import { useTranslation } from '@/lib/i18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('app.auth.forgot'));
    };

    return (
        <GuestLayout
            title={t('auth.forgot.title')}
            description={t('auth.forgot.description')}
        >
            <Head title={t('auth.forgot.head')} />

            {status && (
                <div className="mb-4 rounded-[var(--radius-sm)] bg-success-soft px-4 py-3 text-sm text-success-foreground">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Field label={t('auth.forgot.email')} htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@example.com"
                    />
                </Field>

                <Button type="submit" loading={processing} className="w-full justify-center">
                    {t('auth.forgot.submit')}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted">
                <Link
                    href={route('app.auth.show-login')}
                    className="font-medium text-accent-soft-foreground hover:underline"
                >
                    {t('auth.forgot.back')}
                </Link>
            </p>
        </GuestLayout>
    );
}
