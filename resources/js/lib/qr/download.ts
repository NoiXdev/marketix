import { QrStyle } from '@/data/qrTypes';
import { downloadBlob, toPdfBlob, toPngBlob, toSvgBlob } from './export';
import { renderQr } from './render';

export type QrDownloadFormat = 'png' | 'svg' | 'pdf';

// Rasterisation scale for PNG exports (matches the editor's download).
const PNG_SCALE = 8;

/**
 * Render a QR code from its encoded `data` + `style` and trigger a browser
 * download in the chosen format. Shared by the editor preview and the QR list
 * so both produce identical files.
 */
export async function downloadQr(format: QrDownloadFormat, data: string, style: QrStyle, name: string): Promise<void> {
  const { svg, width, height } = renderQr(data, style);
  const base = name || 'qr-code';

  switch (format) {
    case 'svg':
      downloadBlob(toSvgBlob(svg), `${base}.svg`);
      return;
    case 'png':
      downloadBlob(await toPngBlob(svg, width, height, PNG_SCALE), `${base}.png`);
      return;
    case 'pdf':
      downloadBlob(await toPdfBlob(svg, width, height), `${base}.pdf`);
      return;
  }
}
