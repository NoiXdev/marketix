import { QrIcon, QR_ICONS, iconToDataUrl } from '@/data/qrIcons';
import { QrStyle } from '@/data/qrTypes';
import { Download } from 'lucide-react';
import QRCodeStyling from 'qr-code-styling';
import { useEffect, useRef } from 'react';

interface Props {
  data: string;
  style: QrStyle;
  name?: string;
}

function getLogoUrl(style: QrStyle): string | undefined {
  if (style.logo_type === 'custom' && style.logo_data) return style.logo_data;
  if (style.logo_type === 'predefined' && style.logo_name) {
    const icon = QR_ICONS.find((i: QrIcon) => i.id === style.logo_name);
    if (icon) return iconToDataUrl(icon);
  }
  return undefined;
}

// A stable key that represents which logo is active — used to detect image changes
function logoKey(style: QrStyle): string {
  return `${style.logo_type}::${style.logo_name}::${style.logo_data?.slice(0, 32) ?? ''}`;
}

function buildOptions(data: string, style: QrStyle): ConstructorParameters<typeof QRCodeStyling>[0] {
  const logoUrl = getLogoUrl(style);
  return {
    width: 280,
    height: 280,
    type: 'canvas' as const,
    data: data || 'https://marketix.app',
    dotsOptions:          { color: style.foreground, type: style.dot_style as any },
    cornersSquareOptions: { color: style.foreground, type: style.corner_square_style as any },
    cornersDotOptions:    { color: style.foreground, type: style.corner_dot_style as any },
    backgroundOptions:    { color: style.background },
    ...(logoUrl
      ? { image: logoUrl, imageOptions: { crossOrigin: 'anonymous' as const, margin: 4, imageSize: style.logo_size / 100 } }
      : { image: '', imageOptions: { imageSize: 0 } }),
  };
}

function initQr(container: HTMLDivElement, data: string, style: QrStyle): QRCodeStyling {
  container.innerHTML = '';
  const qr = new QRCodeStyling(buildOptions(data, style));
  qr.append(container);
  return qr;
}

export default function QrPreview({ data, style, name = 'qr-code' }: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const qrRef        = useRef<QRCodeStyling | null>(null);
  const prevLogoKey  = useRef<string>('');

  // Mount
  useEffect(() => {
    if (!containerRef.current) return;
    prevLogoKey.current = logoKey(style);
    qrRef.current = initQr(containerRef.current, data, style);
    return () => {
      if (containerRef.current) containerRef.current.innerHTML = '';
      qrRef.current = null;
    };
  }, []);

  // Update whenever data or style changes
  useEffect(() => {
    if (!qrRef.current || !containerRef.current) return;

    const key = logoKey(style);
    if (key !== prevLogoKey.current) {
      // qr-code-styling's update() doesn't apply image changes reliably —
      // destroy and recreate the instance when the logo changes.
      prevLogoKey.current = key;
      qrRef.current = initQr(containerRef.current, data, style);
    } else {
      qrRef.current.update(buildOptions(data, style));
    }
  }, [data, style]);

  function download(ext: 'png' | 'svg') {
    qrRef.current?.download({ name, extension: ext });
  }

  return (
    <div className="flex flex-col items-center gap-4">
      <div
        ref={containerRef}
        className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700"
        style={{ background: style.background }}
      />
      <div className="flex gap-2">
        <button type="button" onClick={() => download('png')}
          className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
          <Download className="h-3.5 w-3.5" /> PNG
        </button>
        <button type="button" onClick={() => download('svg')}
          className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
          <Download className="h-3.5 w-3.5" /> SVG
        </button>
      </div>
    </div>
  );
}
