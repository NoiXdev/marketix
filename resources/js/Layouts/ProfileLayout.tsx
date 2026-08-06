import Brand from '@/Components/Brand';
import { BackLink } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Head } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface ProfileLayoutProps {
  title?: string;
}

export default function ProfileLayout({ children, title }: PropsWithChildren<ProfileLayoutProps>) {
  const { t } = useTranslation();

  return (
    <div className="min-h-screen bg-canvas text-foreground">
      {title && <Head title={title} />}
      <header className="flex h-14 items-center border-b border-line bg-surface px-4">
        <Brand />
      </header>
      <main className="mx-auto w-full max-w-md px-4 py-10">
        <div className="mb-6">
          <BackLink href="/">{t('profile.back')}</BackLink>
        </div>
        {children}
      </main>
    </div>
  );
}
