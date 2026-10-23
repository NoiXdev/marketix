import { Globe, HelpCircle, Monitor, MonitorSmartphone, Smartphone, Tablet } from 'lucide-react';
import { ReactNode, useId } from 'react';
import { siAndroid, siApple, siFirefoxbrowser, siLinux, siOpera } from 'simple-icons';

type Logo = { title: string; render: (id: string) => ReactNode };

// Microsoft's four-pane mark; Windows is not available in simple-icons.
const WINDOWS_PATH = 'M3 5.1 10.2 4v7.4H3V5.1Zm8.6-1.3L21 2.5v8.9h-9.4V3.8ZM3 12.6h7.2V20L3 18.9v-6.3Zm8.6 0H21v8.9l-9.4-1.4v-7.5Z';

function Gradient({ id, stops, from = [0, 0], to = [0, 1] }: { id: string; stops: string[]; from?: [number, number]; to?: [number, number] }) {
  return (
    <defs>
      <linearGradient id={id} x1={from[0]} y1={from[1]} x2={to[0]} y2={to[1]}>
        {stops.map((color, i) => (
          <stop key={i} offset={i / (stops.length - 1)} stopColor={color} />
        ))}
      </linearGradient>
    </defs>
  );
}

function chromeMark([top, right, left, center]: [string, string, string, string]): ReactNode {
  return (
    <>
      <path d="M2.77 6.02A11 11 0 0 1 21.8 7H12a5 5 0 0 0-4.33 7.5Z" fill={top} />
      <path d="M21.8 7a11 11 0 0 1-10.37 15.99l4.9-8.49A5 5 0 0 0 12 7Z" fill={right} />
      <path d="M11.43 22.99A11 11 0 0 1 2.77 6.02l4.9 8.48a5 5 0 0 0 8.66 0Z" fill={left} />
      <circle cx="12" cy="12" r="5" fill="#fff" />
      <circle cx="12" cy="12" r="4" fill={center} />
    </>
  );
}

const LOGOS: Record<string, Logo> = {
  chrome: { title: 'Chrome', render: () => chromeMark(['#EA4335', '#FBBC04', '#34A853', '#4285F4']) },
  chromium: { title: 'Chromium', render: () => chromeMark(['#8AB4F8', '#4285F4', '#1967D2', '#1A73E8']) },
  firefox: {
    title: 'Firefox',
    render: (id) => (
      <>
        <Gradient id={id} stops={['#FFBD4F', '#FF7139', '#E31587']} from={[0.8, 0]} to={[0.2, 1]} />
        <path d={siFirefoxbrowser.path} fill={`url(#${id})`} />
      </>
    ),
  },
  safari: {
    title: 'Safari',
    render: (id) => (
      <>
        <Gradient id={id} stops={['#19D7FF', '#1E64F0']} />
        <circle cx="12" cy="12" r="11" fill={`url(#${id})`} />
        <path d="M18.4 5.6 13.13 13.13 10.87 10.87Z" fill="#FF3B30" />
        <path d="M5.6 18.4 10.87 10.87 13.13 13.13Z" fill="#fff" />
      </>
    ),
  },
  edge: {
    title: 'Edge',
    render: (id) => (
      <>
        <Gradient id={id} stops={['#0C59A4', '#1B9DE2', '#36C694']} from={[0, 0]} to={[1, 1]} />
        <path d="M4 12h16a8 8 0 1 0-2.34 5.66" fill="none" stroke={`url(#${id})`} strokeWidth="3.4" strokeLinecap="round" />
      </>
    ),
  },
  opera: {
    title: 'Opera',
    render: (id) => (
      <>
        <Gradient id={id} stops={['#FF1B2D', '#A70014']} />
        <path d={siOpera.path} fill={`url(#${id})`} />
      </>
    ),
  },
  windows: {
    title: 'Windows',
    render: (id) => (
      <>
        <Gradient id={id} stops={['#28A8EA', '#0067B8']} from={[0, 0]} to={[1, 1]} />
        <path d={WINDOWS_PATH} fill={`url(#${id})`} />
      </>
    ),
  },
  apple: { title: 'Apple', render: () => <path d={siApple.path} fill="var(--foreground)" /> },
  android: { title: 'Android', render: () => <path d={siAndroid.path} fill="#3DDC84" /> },
  linux: { title: 'Linux', render: () => <path d={siLinux.path} fill="var(--foreground)" /> },
};

const BROWSERS = ['chrome', 'chromium', 'firefox', 'safari', 'edge', 'opera'];

const SYSTEMS = ['macos', 'ios', 'android', 'linux', 'windows'];

const ALIASES: Record<string, string> = { macos: 'apple', ios: 'apple' };

function BrandLogo({ logo, className }: { logo: Logo; className: string }) {
  const id = `logo${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;

  return (
    <svg viewBox="0 0 24 24" className={className} role="img" aria-label={logo.title}>
      {logo.render(id)}
    </svg>
  );
}

export function PlatformIcon({ kind, name, className = 'h-4 w-4 text-subtle' }: { kind: 'browser' | 'os' | 'device'; name?: string | null; className?: string }) {
  const key = (name ?? '').trim().toLowerCase();

  if (kind === 'browser' || kind === 'os') {
    if ((kind === 'browser' ? BROWSERS : SYSTEMS).includes(key)) return <BrandLogo logo={LOGOS[ALIASES[key] ?? key]} className={className} />;
    return kind === 'browser' ? <Globe className={className} aria-hidden /> : <HelpCircle className={className} aria-hidden />;
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
