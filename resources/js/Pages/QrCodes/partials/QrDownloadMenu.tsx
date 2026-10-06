import { buildQrContent, QrStyle, QrType } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import type { QrDownloadFormat } from '@/lib/qr/download';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Download, Loader2 } from 'lucide-react';
import { useState } from 'react';

interface Props {
  name: string;
  type: QrType;
  isDynamic: boolean;
  content: Record<string, string>;
  dynamicUrl: string | null;
  style: QrStyle;
}

const FORMATS: { format: QrDownloadFormat; labelKey: string }[] = [
  { format: 'png', labelKey: 'qr.export.png' },
  { format: 'svg', labelKey: 'qr.export.svg' },
  { format: 'pdf', labelKey: 'qr.export.pdf' },
];

/**
 * Per-row QR download dropdown. Renders and exports the QR entirely in the
 * browser, reusing the editor's pipeline. The heavy export libs (jsPDF) are
 * imported only when a format is picked, so the list page stays light.
 */
export default function QrDownloadMenu({ name, type, isDynamic, content, dynamicUrl, style }: Props) {
  const { t } = useTranslation();
  const [busy, setBusy] = useState(false);

  async function handle(format: QrDownloadFormat) {
    setBusy(true);
    try {
      const data = buildQrContent(type, isDynamic, content, dynamicUrl ?? undefined);
      const { downloadQr } = await import('@/lib/qr/download');
      await downloadQr(format, data, style, name || 'qr-code');
    } catch (e) {
      console.error(e);
    } finally {
      setBusy(false);
    }
  }

  const btn =
    'inline-flex items-center justify-center rounded-md p-1.5 text-subtle transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:opacity-50';

  return (
    <Menu>
      <MenuButton type="button" title={t('qr.export.download')} aria-label={t('qr.export.download')} disabled={busy} onClick={(e) => e.stopPropagation()} className={btn}>
        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
      </MenuButton>
      <MenuItems anchor="bottom end" className="border-line bg-surface z-30 w-36 rounded-md border py-1 shadow-lg [--anchor-gap:4px] focus:outline-none">
        {FORMATS.map(({ format, labelKey }) => (
          <MenuItem key={format}>
            <button
              type="button"
              onClick={() => handle(format)}
              className="text-muted data-[focus]:bg-elevated data-[focus]:text-foreground flex w-full items-center gap-2 px-3 py-2 text-sm"
            >
              <Download className="text-subtle h-3.5 w-3.5" />
              {t(labelKey)}
            </button>
          </MenuItem>
        ))}
      </MenuItems>
    </Menu>
  );
}
