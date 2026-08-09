import { Badge } from '@/Components/ui';
import { QrStyle } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { ScanIssue, scannability } from '@/lib/qr/scannability';

interface Props {
  style: QrStyle;
}

const VARIANT = {
  good: 'success',
  warn: 'warning',
  bad: 'danger',
} as const;

export default function QrScannability({ style }: Props) {
  const { t } = useTranslation();
  const report = scannability(style);

  function issueMessage(issue: ScanIssue): string {
    switch (issue.code) {
      case 'contrast':
        return t('qr.scan.contrast', { ratio: Math.round(report.contrastRatio * 10) / 10 });
      case 'logo_ecc':
        return t('qr.scan.logo_ecc', { level: style.error_correction });
      case 'quiet_zone':
        return t('qr.scan.quiet_zone');
    }
  }

  return (
    <div className="rounded-[var(--radius)] border border-line bg-elevated p-4">
      <div className="flex items-center justify-between gap-3">
        <h3 className="text-sm font-semibold text-foreground">{t('qr.scan.title')}</h3>
        <Badge variant={VARIANT[report.level]}>{t(`qr.scan.${report.level}`)}</Badge>
      </div>
      {report.issues.length > 0 && (
        <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-muted">
          {report.issues.map((issue) => (
            <li key={issue.code}>{issueMessage(issue)}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
