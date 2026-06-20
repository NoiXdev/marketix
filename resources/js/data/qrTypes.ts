export type QrType =
  | 'text' | 'sms' | 'wifi' | 'vcard' | 'event'                              // static-capable
  | 'link' | 'email' | 'phone' | 'application' | 'file' | 'whatsapp' | 'crypto'; // dynamic-only

export interface QrTypeConfig {
  value: QrType;
  label: string;
  category: 'static' | 'dynamic' | 'both';
  icon: string; // emoji shorthand for display
  defaultContent: Record<string, string>;
}

export const QR_TYPES: QrTypeConfig[] = [
  // ── Static ─────────────────────────────────────────────────────────────
  { value: 'text',   label: 'Text',        category: 'static', icon: '✏️', defaultContent: { text: '' } },
  { value: 'sms',    label: 'SMS & Message', category: 'both',  icon: '💬', defaultContent: { phone: '', message: '' } },
  { value: 'wifi',   label: 'WiFi',         category: 'static', icon: '📶', defaultContent: { ssid: '', password: '', encryption: 'WPA', hidden: 'false' } },
  { value: 'vcard',  label: 'vCard',        category: 'both',   icon: '👤', defaultContent: { name: '', phone: '', email: '', org: '', url: '', address: '' } },
  { value: 'event',  label: 'Event',        category: 'static', icon: '📅', defaultContent: { title: '', start: '', end: '', location: '', description: '' } },
  // ── Dynamic (also usable as static / no-tracking) ────────────────────────
  { value: 'link',        label: 'Link',           category: 'both', icon: '🔗', defaultContent: { url: '' } },
  { value: 'email',       label: 'Email',          category: 'both', icon: '📧', defaultContent: { email: '', subject: '', body: '' } },
  { value: 'phone',       label: 'Phone',          category: 'both', icon: '📞', defaultContent: { phone: '' } },
  { value: 'application', label: 'Application',    category: 'both', icon: '📱', defaultContent: { url_ios: '', url_android: '', url_fallback: '' } },
  { value: 'file',        label: 'File',           category: 'both', icon: '📄', defaultContent: { file_url: '' } },
  { value: 'whatsapp',    label: 'WhatsApp',       category: 'both', icon: '🟢', defaultContent: { phone: '', message: '' } },
  { value: 'crypto',      label: 'Cryptocurrency', category: 'both', icon: '₿',  defaultContent: { currency: 'BTC', address: '', amount: '', label: '' } },
];

export const STATIC_TYPES  = QR_TYPES.filter(t => t.category === 'static' || t.category === 'both');
export const DYNAMIC_TYPES = QR_TYPES.filter(t => t.category === 'dynamic' || t.category === 'both');

// A QR is tracked only when it is dynamic (static-only types can never be tracked).
// The three dual-mode types ('sms', 'vcard', 'whatsapp') are tracked iff dynamic.
export function qrTypeTrackable(config: QrTypeConfig, isDynamic: boolean): boolean {
  return isDynamic && config.category !== 'static';
}

// ── Content → QR string ───────────────────────────────────────────────────

function buildVCard(c: Record<string, string>): string {
  const extra = (c.extra || '').split('\n').map(s => s.trim()).filter(Boolean);
  return [
    'BEGIN:VCARD', 'VERSION:3.0',
    c.name       ? `FN:${c.name}`          : '',
    c.org        ? `ORG:${c.org}`          : '',
    c.phone      ? `TEL:${c.phone}`        : '',
    c.email      ? `EMAIL:${c.email}`      : '',
    c.url        ? `URL:${c.url}`          : '',
    c.address    ? `ADR:;;${c.address};;;` : '',
    ...extra,
    'END:VCARD',
  ].filter(Boolean).join('\n');
}

function buildEvent(c: Record<string, string>): string {
  const toIcal = (dt: string) => dt.replace(/[-:T]/g, '').slice(0, 15) + 'Z';
  return [
    'BEGIN:VCALENDAR', 'VERSION:2.0',
    'BEGIN:VEVENT',
    c.title       ? `SUMMARY:${c.title}`         : '',
    c.start       ? `DTSTART:${toIcal(c.start)}`  : '',
    c.end         ? `DTEND:${toIcal(c.end)}`      : '',
    c.location    ? `LOCATION:${c.location}`      : '',
    c.description ? `DESCRIPTION:${c.description}` : '',
    'END:VEVENT', 'END:VCALENDAR',
  ].filter(Boolean).join('\n');
}

export function buildQrContent(
  type: QrType,
  isDynamic: boolean,
  content: Record<string, string>,
  dynamicUrl?: string,
): string {
  // Dynamic types encode a redirect URL (when the QR has been saved and has a code)
  if (isDynamic && dynamicUrl) return dynamicUrl;

  switch (type) {
    case 'text':        return content.text || '';
    case 'sms':         return `SMSTO:${content.phone || ''}:${content.message || ''}`;
    case 'wifi':        return `WIFI:T:${content.encryption || 'WPA'};S:${content.ssid || ''};P:${content.password || ''};H:${content.hidden === 'true'};`;
    case 'vcard':       return buildVCard(content);
    case 'event':       return buildEvent(content);
    case 'link':        return content.url || '';
    case 'email':       return `mailto:${content.email || ''}?subject=${encodeURIComponent(content.subject || '')}&body=${encodeURIComponent(content.body || '')}`;
    case 'phone':       return `tel:${content.phone || ''}`;
    case 'whatsapp':    return `https://wa.me/${(content.phone || '').replace(/[^0-9]/g, '')}${content.message ? `?text=${encodeURIComponent(content.message)}` : ''}`;
    case 'crypto':      return `${(content.currency || 'bitcoin').toLowerCase()}:${content.address || ''}${content.amount ? `?amount=${content.amount}` : ''}`;
    case 'application': return content.url_fallback || content.url_android || content.url_ios || '';
    case 'file':        return content.file_url || '';
    default:            return '';
  }
}

export type DotStyle           = 'square' | 'dots' | 'rounded' | 'classy' | 'classy-rounded' | 'extra-rounded';
export type CornerSquareStyle  = 'square' | 'dot' | 'extra-rounded';
export type CornerDotStyle     = 'square' | 'dot';
export type LogoType           = 'none' | 'predefined' | 'custom';

export interface QrStyle {
  foreground:           string;
  background:           string;
  dot_style:            DotStyle;
  corner_square_style:  CornerSquareStyle;
  corner_dot_style:     CornerDotStyle;
  logo_type:            LogoType;
  logo_name:            string;
  logo_data:            string;
  logo_size:            number;
}

export const DEFAULT_STYLE: QrStyle = {
  foreground:           '#000000',
  background:           '#ffffff',
  dot_style:            'square',
  corner_square_style:  'square',
  corner_dot_style:     'square',
  logo_type:            'none',
  logo_name:            '',
  logo_data:            '',
  logo_size:            30,
};
