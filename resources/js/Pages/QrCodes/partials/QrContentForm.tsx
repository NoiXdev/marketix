import { QrType } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { ParsedVCard, extraSummary, mergeVCardIntoContent, parseVCards } from '@/lib/vcard';
import { Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';

const inp = 'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
const sel = inp;

interface Props {
  type: QrType;
  content: Record<string, string>;
  onChange: (content: Record<string, string>) => void;
}

function Field({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">{label}</label>
      {hint && <p className="text-xs text-slate-400 dark:text-slate-500">{hint}</p>}
      <div className="mt-1">{children}</div>
    </div>
  );
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
        className={`flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed py-6 text-slate-400 transition-colors ${
          drag
            ? 'border-indigo-400 text-indigo-500'
            : 'border-slate-300 hover:border-indigo-400 hover:text-indigo-500 dark:border-slate-700'
        }`}
      >
        <Upload className="h-5 w-5" />
        <span className="text-xs">{t('qr.vcard.drop')}</span>
      </div>
      <input ref={fileRef} type="file" accept=".vcf,text/vcard,text/x-vcard" className="hidden"
        onChange={e => handleFiles(e.target.files)} />

      {error && <p className="text-xs text-red-600">{error}</p>}

      {choices.length > 0 && (
        <div className="rounded-lg border border-slate-200 p-2 dark:border-slate-700">
          <p className="mb-2 text-xs text-slate-500 dark:text-slate-400">
            {t('qr.vcard.pick', { count: choices.length })}
          </p>
          <div className="space-y-1">
            {choices.map((c, i) => (
              <button key={i} type="button" onClick={() => pick(c)}
                className="block w-full rounded-md px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-indigo-50 dark:text-slate-300 dark:hover:bg-indigo-900/20">
                {c.displayName}
              </button>
            ))}
          </div>
        </div>
      )}

      {summary.count > 0 && (
        <div className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800">
          <span className="text-slate-500 dark:text-slate-400">
            {t('qr.vcard.extras', { count: summary.count, fields: summary.names.join(', ') })}
          </span>
          <button type="button" onClick={() => onChange({ ...content, extra: '' })}
            className="flex shrink-0 items-center gap-1 text-red-500 hover:text-red-700">
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
            placeholder="Enter any text…" className={inp + ' resize-none'} />
        </Field>
      );

    case 'sms':
      return (
        <div className="space-y-4">
          <Field label="Phone number">
            <input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)}
              placeholder="+49 123 456789" className={inp} />
          </Field>
          <Field label="Message" hint="Optional pre-filled message">
            <textarea rows={3} value={v('message')} onChange={e => set('message', e.target.value)}
              placeholder="Hello!" className={inp + ' resize-none'} />
          </Field>
        </div>
      );

    case 'wifi':
      return (
        <div className="space-y-4">
          <Field label="Network name (SSID)">
            <input type="text" value={v('ssid')} onChange={e => set('ssid', e.target.value)}
              placeholder="MyWiFi" className={inp} />
          </Field>
          <Field label="Password">
            <input type="text" value={v('password')} onChange={e => set('password', e.target.value)}
              placeholder="••••••••" className={inp} />
          </Field>
          <Field label="Encryption">
            <select value={v('encryption', 'WPA')} onChange={e => set('encryption', e.target.value)} className={sel}>
              <option value="WPA">WPA/WPA2</option>
              <option value="WEP">WEP</option>
              <option value="nopass">None</option>
            </select>
          </Field>
          <div className="flex items-center gap-2">
            <input type="checkbox" id="hidden" checked={v('hidden') === 'true'}
              onChange={e => set('hidden', e.target.checked ? 'true' : 'false')}
              className="h-4 w-4 rounded border-slate-300 text-indigo-600" />
            <label htmlFor="hidden" className="text-sm text-slate-700 dark:text-slate-300">Hidden network</label>
          </div>
        </div>
      );

    case 'vcard':
      return (
        <div className="space-y-4">
          <VCardImport content={content} onChange={onChange} />
          <Field label="Full name"><input type="text" value={v('name')} onChange={e => set('name', e.target.value)} placeholder="Jane Doe" className={inp} /></Field>
          <Field label="Organisation"><input type="text" value={v('org')} onChange={e => set('org', e.target.value)} placeholder="Acme Corp" className={inp} /></Field>
          <Field label="Phone"><input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)} placeholder="+49 123 456789" className={inp} /></Field>
          <Field label="Email"><input type="email" value={v('email')} onChange={e => set('email', e.target.value)} placeholder="jane@example.com" className={inp} /></Field>
          <Field label="Website"><input type="url" value={v('url')} onChange={e => set('url', e.target.value)} placeholder="https://example.com" className={inp} /></Field>
          <Field label="Address"><input type="text" value={v('address')} onChange={e => set('address', e.target.value)} placeholder="123 Main St, Berlin" className={inp} /></Field>
        </div>
      );

    case 'event':
      return (
        <div className="space-y-4">
          <Field label="Title"><input type="text" value={v('title')} onChange={e => set('title', e.target.value)} placeholder="Team Meeting" className={inp} /></Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Start"><input type="datetime-local" value={v('start')} onChange={e => set('start', e.target.value)} className={inp} /></Field>
            <Field label="End"><input type="datetime-local" value={v('end')} onChange={e => set('end', e.target.value)} className={inp} /></Field>
          </div>
          <Field label="Location"><input type="text" value={v('location')} onChange={e => set('location', e.target.value)} placeholder="Conference Room A" className={inp} /></Field>
          <Field label="Description">
            <textarea rows={3} value={v('description')} onChange={e => set('description', e.target.value)}
              placeholder="Optional description…" className={inp + ' resize-none'} />
          </Field>
        </div>
      );

    case 'link':
      return (
        <Field label="URL">
          <input type="url" value={v('url')} onChange={e => set('url', e.target.value)}
            placeholder="https://example.com" className={inp} autoFocus />
        </Field>
      );

    case 'email':
      return (
        <div className="space-y-4">
          <Field label="Email address"><input type="email" value={v('email')} onChange={e => set('email', e.target.value)} placeholder="contact@example.com" className={inp} /></Field>
          <Field label="Subject"><input type="text" value={v('subject')} onChange={e => set('subject', e.target.value)} placeholder="Optional subject" className={inp} /></Field>
          <Field label="Body">
            <textarea rows={3} value={v('body')} onChange={e => set('body', e.target.value)}
              placeholder="Optional message body…" className={inp + ' resize-none'} />
          </Field>
        </div>
      );

    case 'phone':
      return (
        <Field label="Phone number">
          <input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)}
            placeholder="+49 123 456789" className={inp} />
        </Field>
      );

    case 'application':
      return (
        <div className="space-y-4">
          <Field label="App Store URL (iOS)"><input type="url" value={v('url_ios')} onChange={e => set('url_ios', e.target.value)} placeholder="https://apps.apple.com/…" className={inp} /></Field>
          <Field label="Google Play URL (Android)"><input type="url" value={v('url_android')} onChange={e => set('url_android', e.target.value)} placeholder="https://play.google.com/…" className={inp} /></Field>
          <Field label="Fallback URL" hint="Used when OS can't be determined">
            <input type="url" value={v('url_fallback')} onChange={e => set('url_fallback', e.target.value)} placeholder="https://example.com/app" className={inp} />
          </Field>
        </div>
      );

    case 'file':
      return (
        <Field label="File URL" hint="Direct link to the file (PDF, image, etc.)">
          <input type="url" value={v('file_url')} onChange={e => set('file_url', e.target.value)}
            placeholder="https://example.com/file.pdf" className={inp} />
        </Field>
      );

    case 'whatsapp':
      return (
        <div className="space-y-4">
          <Field label="WhatsApp number" hint="Include country code, no spaces or dashes">
            <input type="tel" value={v('phone')} onChange={e => set('phone', e.target.value)}
              placeholder="+491234567890" className={inp} />
          </Field>
          <Field label="Pre-filled message">
            <textarea rows={3} value={v('message')} onChange={e => set('message', e.target.value)}
              placeholder="Hello!" className={inp + ' resize-none'} />
          </Field>
        </div>
      );

    case 'crypto':
      return (
        <div className="space-y-4">
          <Field label="Currency">
            <select value={v('currency', 'BTC')} onChange={e => set('currency', e.target.value)} className={sel}>
              {['BTC','ETH','LTC','BCH','XRP','DOGE','SOL','USDT','BNB','ADA'].map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </Field>
          <Field label="Wallet address"><input type="text" value={v('address')} onChange={e => set('address', e.target.value)} placeholder="1A1zP1eP5QGefi2DMPTfTL5SLmv7Divf..." className={inp} /></Field>
          <Field label="Amount" hint="Optional">
            <input type="number" step="any" min="0" value={v('amount')} onChange={e => set('amount', e.target.value)}
              placeholder="0.001" className={inp} />
          </Field>
          <Field label="Label" hint="Optional description">
            <input type="text" value={v('label')} onChange={e => set('label', e.target.value)}
              placeholder="Donation" className={inp} />
          </Field>
        </div>
      );

    default:
      return null;
  }
}
