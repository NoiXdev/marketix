import { highlightJs, TokenKind } from '@/lib/highlight';
import { useTranslation } from '@/lib/i18n';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

const COLORS: Record<TokenKind, string> = {
  string: 'text-[color:var(--code-string)]',
  number: 'text-[color:var(--code-number)]',
  keyword: 'text-[color:var(--code-keyword)]',
  function: 'text-[color:var(--code-function)]',
  key: 'text-[color:var(--code-key)]',
  punctuation: 'text-[color:var(--code-punctuation)]',
  plain: '',
};

export default function CodeSnippet({ code, className = '' }: { code: string; className?: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  function copy() {
    navigator.clipboard.writeText(code).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <div className={`relative inline-flex max-w-full items-center rounded-[var(--radius-sm)] bg-[color:var(--code-bg)] text-left ${className}`}>
      <pre className="overflow-x-auto py-2.5 pr-11 pl-3.5 font-mono text-xs leading-relaxed text-[color:var(--code-fg)]">
        <code>
          {highlightJs(code).map((token, i) => (
            <span key={i} className={COLORS[token.kind]}>
              {token.text}
            </span>
          ))}
        </code>
      </pre>
      <button
        type="button"
        onClick={copy}
        title={copied ? t('analytics.sites.copied') : t('analytics.sites.copy')}
        aria-label={copied ? t('analytics.sites.copied') : t('analytics.sites.copy')}
        className={`absolute top-1/2 right-1.5 -translate-y-1/2 rounded-md p-1.5 transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
          copied ? 'text-[color:var(--code-string)]' : 'text-[color:var(--code-punctuation)] hover:bg-white/10 hover:text-[color:var(--code-fg)]'
        }`}
      >
        {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
      </button>
    </div>
  );
}
