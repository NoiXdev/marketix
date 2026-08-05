import { Card } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import { Globe, LineChart, LinkIcon, QrCode } from 'lucide-react';

export default function QuickActions({ projectId }: { projectId: string }) {
  const { t } = useTranslation();
  const actions = [
    { icon: LinkIcon, t: 'qa_link', d: 'qa_link_d', href: route('app.project.links.create', { project: projectId }) },
    { icon: QrCode, t: 'qa_qr', d: 'qa_qr_d', href: route('app.project.qrcodes.create', { project: projectId }) },
    { icon: LineChart, t: 'qa_site', d: 'qa_site_d', href: route('app.project.sites.create', { project: projectId }) },
    { icon: Globe, t: 'qa_domain', d: 'qa_domain_d', href: route('app.project.domains.create', { project: projectId }) },
  ];
  return (
    <div className="grid grid-cols-2 gap-3.5 md:grid-cols-4">
      {actions.map(({ icon: Icon, t: tk, d, href }) => (
        <Link key={tk} href={href} className="flex items-center gap-3 rounded-[var(--radius)] border border-line bg-surface p-3.5 shadow-[var(--shadow-sm)] transition-colors hover:border-line-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]">
          <span className="grid h-[34px] w-[34px] place-items-center rounded-[9px] bg-accent-soft text-accent-soft-foreground"><Icon className="h-[17px] w-[17px]" /></span>
          <span className="min-w-0"><span className="block text-[13.5px] font-semibold text-foreground">{t(`common.dashboard.${tk}`)}</span><span className="block text-xs text-muted">{t(`common.dashboard.${d}`)}</span></span>
        </Link>
      ))}
    </div>
  );
}
