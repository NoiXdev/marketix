import { Globe, HelpCircle, Monitor, MonitorSmartphone, Smartphone, Tablet } from 'lucide-react';
import { siAndroid, siApple, siFirefoxbrowser, siGooglechrome, siLinux, siOpera, siSafari } from 'simple-icons';

type SimpleIcon = { path: string; title: string };

// Microsoft's four-pane mark; Windows is not available in simple-icons.
const WINDOWS_PATH = 'M3 5.1 10.2 4v7.4H3V5.1Zm8.6-1.3L21 2.5v8.9h-9.4V3.8ZM3 12.6h7.2V20L3 18.9v-6.3Zm8.6 0H21v8.9l-9.4-1.4v-7.5Z';

function Brand({ icon, className }: { icon: SimpleIcon; className: string }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor" role="img" aria-label={icon.title}>
      <path d={icon.path} />
    </svg>
  );
}

/**
 * Prefix glyph for a browser / operating-system / device name. Brand logos come
 * from simple-icons where available; brand-policy gaps (Edge, Windows) and the
 * "Other" bucket fall back to neutral lucide glyphs. All render monochrome in
 * the current text color so they sit quietly next to the label.
 */
export function PlatformIcon({ kind, name, className = 'h-4 w-4 text-subtle' }: { kind: 'browser' | 'os' | 'device'; name?: string | null; className?: string }) {
  const key = (name ?? '').trim().toLowerCase();

  if (kind === 'browser') {
    switch (key) {
      case 'chrome':
      case 'chromium':
        return <Brand icon={siGooglechrome} className={className} />;
      case 'firefox':
        return <Brand icon={siFirefoxbrowser} className={className} />;
      case 'safari':
        return <Brand icon={siSafari} className={className} />;
      case 'opera':
        return <Brand icon={siOpera} className={className} />;
      default: // Edge, Other, unknown
        return <Globe className={className} aria-hidden />;
    }
  }

  if (kind === 'os') {
    switch (key) {
      case 'macos':
      case 'ios':
        return <Brand icon={siApple} className={className} />;
      case 'android':
        return <Brand icon={siAndroid} className={className} />;
      case 'linux':
        return <Brand icon={siLinux} className={className} />;
      case 'windows':
        return (
          <svg viewBox="0 0 24 24" className={className} fill="currentColor" role="img" aria-label="Windows">
            <path d={WINDOWS_PATH} />
          </svg>
        );
      default:
        return <HelpCircle className={className} aria-hidden />;
    }
  }

  switch (key) {
    case 'desktop':
      return <Monitor className={className} aria-hidden />;
    case 'mobile':
      return <Smartphone className={className} aria-hidden />;
    case 'tablet':
      return <Tablet className={className} aria-hidden />;
    default:
      return <MonitorSmartphone className={className} aria-hidden />;
  }
}
