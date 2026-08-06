import { Button } from '@/Components/ui';
import { QrStyle } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { downloadBlob, toPdfBlob, toPngBlob, toSvgBlob } from '@/lib/qr/export';
import { renderQr } from '@/lib/qr/render';
import { Download } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
  data: string;
  style: QrStyle;
  name?: string;
}

const DEBOUNCE_MS = 150;

export default function QrPreview({ data, style, name = 'qr-code' }: Props) {
  const { t } = useTranslation();

  // Recomputed immediately so the very first paint has content, then kept in
  // sync with `data`/`style` via a short debounce so slider drags stay smooth.
  const immediate = useMemo(() => renderQr(data, style), [data, style]);
  const [result, setResult] = useState(immediate);

  useEffect(() => {
    const id = window.setTimeout(() => setResult(immediate), DEBOUNCE_MS);
    return () => window.clearTimeout(id);
  }, [immediate]);

  const { svg, width, height } = result;

  function downloadSvg() {
    downloadBlob(toSvgBlob(svg), `${name}.svg`);
  }

  async function downloadPng() {
    try {
      const blob = await toPngBlob(svg, width, height, 8);
      downloadBlob(blob, `${name}.png`);
    } catch (e) {
      console.error(e);
    }
  }

  async function downloadPdf() {
    try {
      const blob = await toPdfBlob(svg, width, height);
      downloadBlob(blob, `${name}.pdf`);
    } catch (e) {
      console.error(e);
    }
  }

  return (
    <div className="flex flex-col items-center gap-4">
      <div
        className="w-[280px] max-w-full overflow-hidden rounded-[12px] border border-line [&>svg]:block [&>svg]:h-auto [&>svg]:w-full"
        style={{ background: style.background }}
        dangerouslySetInnerHTML={{ __html: svg }}
      />
      <div className="flex gap-2">
        <Button type="button" variant="secondary" size="sm" onClick={downloadPng}>
          <Download className="h-3.5 w-3.5" /> {t('qr.export.png')}
        </Button>
        <Button type="button" variant="secondary" size="sm" onClick={downloadSvg}>
          <Download className="h-3.5 w-3.5" /> {t('qr.export.svg')}
        </Button>
        <Button type="button" variant="secondary" size="sm" onClick={downloadPdf}>
          <Download className="h-3.5 w-3.5" /> {t('qr.export.pdf')}
        </Button>
      </div>
    </div>
  );
}
