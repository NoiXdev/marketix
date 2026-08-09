import { Checkbox, Field, Input, Select } from '@/Components/ui';
import { QrType } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { ParsedVCard, extraSummary, mergeVCardIntoContent, parseVCards } from '@/lib/vcard';
import { Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';

const textareaCls =
  'w-full resize-none rounded-[var(--radius-sm)] border border-line-strong bg-surface px-3 py-2 text-sm text-foreground transition-colors placeholder:text-subtle focus-visible:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';

interface Props {
  type: QrType;
  content: Record<string, string>;
  onChange: (content: Record<string, string>) => void;
}

function VCardImport({ content, onChange }: {
  content: Record<string, string>;
  onChange: (content: Record<string, string>) => void;
}) {
  const { t } = useTranslation();
  const fileRef = useRef<HTMLInputElement>(null);
  const [drag, setDrag] = useState(false);
  const [error, setError] = useState('');
  const [choices, setChoices] = useState<ParsedVCard[]>([]);

  function handleFiles(files: FileList | null) {
    const file = files?.[0];
    if (!file) return;
    setError('');
    const reader = new FileReader();
    reader.onload = () => {
      const cards = parseVCards(String(reader.result));
      if (cards.length === 0) { setError(t('qr.vcard.error')); setChoices([]); return; }
      if (cards.length === 1) { onChange(mergeVCardIntoContent(content, cards[0])); setChoices([]); return; }
      setChoices(cards);
    };
    reader.readAsText(file);
  }

  function pick(card: ParsedVCard) {
    onChange(mergeVCardIntoContent(content, card));
    setChoices([]);
  }

  const summary = extraSummary(content.extra);

  return (
    <div className="space-y-2">
      <div
        onDragOver={e => { e.preventDefault(); setDrag(true); }}
        onDragLeave={() => setDrag(false)}
        onDrop={e => { e.preventDefault(); setDrag(false); handleFiles(e.dataTransfer.files); }}
        onClick={() => fileRef.current?.click()}
        className={`flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed py-6 text-subtle transition-colors ${
          drag
            ? 'border-accent text-accent-soft-foreground'
            : 'border-line-strong hover:border-accent hover:text-accent-soft-foreground'
        }`}
      >
        <Upload className="h-5 w-5" />
        <span className="text-xs">{t('qr.vcard.drop')}</span>
      </div>
      <input ref={fileRef} type="file" accept=".vcf,text/vcard,text/x-vcard" className="hidden"
        onChange={e => handleFiles(e.target.files)} />

      {error && <p className="text-xs text-danger-foreground">{error}</p>}

      {choices.length > 0 && (
        <div className="rounded-lg border border-line p-2">
          <p className="mb-2 text-xs text-muted">
            {t('qr.vcard.pick', { count: choices.length })}
          </p>
          <div className="space-y-1">
            {choices.map((c, i) => (
              <button key={i} type="button" onClick={() => pick(c)}
                className="block w-full rounded-md px-3 py-1.5 text-left text-sm text-foreground hover:bg-accent-soft">
                {c.displayName}
              </button>
            ))}
          </div>
        </div>
      )}

      {summary.count > 0 && (
        <div className="flex items-center justify-between rounded-md bg-elevated px-3 py-2 text-xs">
          <span className="text-muted">
            {t('qr.vcard.extras', { count: summary.count, fields: summary.names.join(', ') })}
          </span>
          <button type="button" onClick={() => onChange({ ...content, extra: '' })}
            className="flex shrink-0 items-center gap-1 text-danger-foreground hover:opacity-80">
            <X className="h-3.5 w-3.5" /> {t('qr.vcard.clear')}
          </button>
        </div>
      )}
    </div>
  );
}

export default function QrContentForm({ type, content, onChange }: Props) {
  const set = (key: string, val: string) => onChange({ ...content, [key]: val });
  const v   = (key: string, fallback = '') => content[key] ?? fallback;

  switch (type) {
    case 'text':
      return (
        <Field label="Text">
          <textarea rows={4} value={v('text')} onChange={e => set('text', e.target.value)}
            placeholder="Enter any text…" className={textareaCls} />
        </Field>
      );

    case 'sms':
      return (
        <div className="space-y-4">
          <Field label="Phone number">
            <Input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)}
              placeholder="+49 123 456789" />
          </Field>
          <Field label="Message" hint="Optional pre-filled message">
            <textarea rows={3} value={v('message')} onChange={e => set('message', e.target.value)}
              placeholder="Hello!" className={textareaCls} />
          </Field>
        </div>
      );

    case 'wifi':
      return (
        <div className="space-y-4">
          <Field label="Network name (SSID)">
            <Input type="text" value={v('ssid')} onChange={e => set('ssid', e.target.value)}
              placeholder="MyWiFi" />
          </Field>
          <Field label="Password">
            <Input type="text" value={v('password')} onChange={e => set('password', e.target.value)}
              placeholder="••••••••" />
          </Field>
          <Field label="Encryption">
            <Select value={v('encryption', 'WPA')} onChange={e => set('encryption', e.target.value)}>
              <option value="WPA">WPA/WPA2</option>
              <option value="WEP">WEP</option>
              <option value="nopass">None</option>
            </Select>
          </Field>
          <div className="flex items-center gap-2">
            <Checkbox id="hidden" checked={v('hidden') === 'true'}
              onChange={e => set('hidden', e.target.checked ? 'true' : 'false')} />
            <label htmlFor="hidden" className="text-sm text-foreground">Hidden network</label>
          </div>
        </div>
      );

    case 'vcard':
      return (
        <div className="space-y-4">
          <VCardImport content={content} onChange={onChange} />
          <Field label="Full name"><Input type="text" value={v('name')} onChange={e => set('name', e.target.value)} placeholder="Jane Doe" /></Field>
          <Field label="Organisation"><Input type="text" value={v('org')} onChange={e => set('org', e.target.value)} placeholder="Acme Corp" /></Field>
          <Field label="Phone"><Input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)} placeholder="+49 123 456789" /></Field>
          <Field label="Email"><Input type="email" value={v('email')} onChange={e => set('email', e.target.value)} placeholder="jane@example.com" /></Field>
          <Field label="Website"><Input type="url" value={v('url')} onChange={e => set('url', e.target.value)} placeholder="https://example.com" /></Field>
          <Field label="Address"><Input type="text" value={v('address')} onChange={e => set('address', e.target.value)} placeholder="123 Main St, Berlin" /></Field>
        </div>
      );

    case 'event':
      return (
        <div className="space-y-4">
          <Field label="Title"><Input type="text" value={v('title')} onChange={e => set('title', e.target.value)} placeholder="Team Meeting" /></Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Start"><Input type="datetime-local" value={v('start')} onChange={e => set('start', e.target.value)} /></Field>
            <Field label="End"><Input type="datetime-local" value={v('end')} onChange={e => set('end', e.target.value)} /></Field>
          </div>
          <Field label="Location"><Input type="text" value={v('location')} onChange={e => set('location', e.target.value)} placeholder="Conference Room A" /></Field>
          <Field label="Description">
            <textarea rows={3} value={v('description')} onChange={e => set('description', e.target.value)}
              placeholder="Optional description…" className={textareaCls} />
          </Field>
        </div>
      );

    case 'link':
      return (
        <Field label="URL">
          <Input type="url" value={v('url')} onChange={e => set('url', e.target.value)}
            placeholder="https://example.com" autoFocus />
        </Field>
      );

    case 'email':
      return (
        <div className="space-y-4">
          <Field label="Email address"><Input type="email" value={v('email')} onChange={e => set('email', e.target.value)} placeholder="contact@example.com" /></Field>
          <Field label="Subject"><Input type="text" value={v('subject')} onChange={e => set('subject', e.target.value)} placeholder="Optional subject" /></Field>
          <Field label="Body">
            <textarea rows={3} value={v('body')} onChange={e => set('body', e.target.value)}
              placeholder="Optional message body…" className={textareaCls} />
          </Field>
        </div>
      );

    case 'phone':
      return (
        <Field label="Phone number">
          <Input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)}
            placeholder="+49 123 456789" />
        </Field>
      );

    case 'application':
      return (
        <div className="space-y-4">
          <Field label="App Store URL (iOS)"><Input type="url" value={v('url_ios')} onChange={e => set('url_ios', e.target.value)} placeholder="https://apps.apple.com/…" /></Field>
          <Field label="Google Play URL (Android)"><Input type="url" value={v('url_android')} onChange={e => set('url_android', e.target.value)} placeholder="https://play.google.com/…" /></Field>
          <Field label="Fallback URL" hint="Used when OS can't be determined">
            <Input type="url" value={v('url_fallback')} onChange={e => set('url_fallback', e.target.value)} placeholder="https://example.com/app" />
          </Field>
        </div>
      );

    case 'file':
      return (
        <Field label="File URL" hint="Direct link to the file (PDF, image, etc.)">
          <Input type="url" value={v('file_url')} onChange={e => set('file_url', e.target.value)}
            placeholder="https://example.com/file.pdf" />
        </Field>
      );

    case 'whatsapp':
      return (
        <div className="space-y-4">
          <Field label="WhatsApp number" hint="Include country code, no spaces or dashes">
            <Input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)}
              placeholder="+491234567890" />
          </Field>
          <Field label="Pre-filled message">
            <textarea rows={3} value={v('message')} onChange={e => set('message', e.target.value)}
              placeholder="Hello!" className={textareaCls} />
          </Field>
        </div>
      );

    case 'crypto':
      return (
        <div className="space-y-4">
          <Field label="Currency">
            <Select value={v('currency', 'BTC')} onChange={e => set('currency', e.target.value)}>
              {['BTC','ETH','LTC','BCH','XRP','DOGE','SOL','USDT','BNB','ADA'].map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </Select>
          </Field>
          <Field label="Wallet address"><Input type="text" value={v('address')} onChange={e => set('address', e.target.value)} placeholder="1A1zP1eP5QGefi2DMPTfTL5SLmv7Divf..." /></Field>
          <Field label="Amount" hint="Optional">
            <Input type="number" step="any" min="0" value={v('amount')} onChange={e => set('amount', e.target.value)}
              placeholder="0.001" />
          </Field>
          <Field label="Label" hint="Optional description">
            <Input type="text" value={v('label')} onChange={e => set('label', e.target.value)}
              placeholder="Donation" />
          </Field>
        </div>
      );

    default:
      return null;
  }
}
