import { Button } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import type { GeneratedNotice } from '@/types';
import { Check, Copy, Loader2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

type CopyTarget = 'text' | 'html';

interface NoticePreviewProps {
  notice: GeneratedNotice | null;
  loading: boolean;
  failed: boolean;
}

export default function NoticePreview({ notice, loading, failed }: NoticePreviewProps) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState<CopyTarget | null>(null);

  const tkey = (suffix: string) => `docs.privacy.generator.${suffix}`;

  function copy(target: CopyTarget) {
    if (!notice || failed) {
      return;
    }

    navigator.clipboard.writeText(target === 'text' ? notice.text : notice.html).then(() => {
      setCopied(target);
      setTimeout(() => setCopied(null), 2000);
    });
  }

  return (
    <div className="space-y-4">
      {/* A failed request leaves the previous notice in state so the
          preview does not flash empty; nothing derived from it may be shown
          next to the error, and nothing stale may be copied. */}
      {notice && !failed && notice.warnings.length > 0 && (
        <div className="bg-warning-soft/60 rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--warning-foreground)_35%,transparent)] p-4">
          <h2 className="text-warning-foreground flex items-center gap-2 text-sm font-semibold">
            <TriangleAlert className="h-4 w-4 shrink-0" />
            {t(tkey('warnings.heading'))}
          </h2>
          <ul className="mt-2 space-y-2">
            {notice.warnings.map((warning) => (
              <li key={warning.key} className="text-warning-foreground flex gap-2 text-xs leading-relaxed">
                <span className="mt-[6px] h-1.5 w-1.5 shrink-0 rounded-full bg-[color:var(--warning-dot)]" />
                <span>{warning.message}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
        <div className="border-line flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
          <h2 className="text-foreground flex items-center gap-2 text-sm font-semibold">
            {t(tkey('preview.heading'))}
            {loading && <Loader2 className="text-subtle h-3.5 w-3.5 animate-spin" aria-hidden />}
          </h2>
          <div className="flex gap-2">
            <Button variant="secondary" size="sm" disabled={!notice || failed} onClick={() => copy('text')}>
              {copied === 'text' ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
              {copied === 'text' ? t(tkey('preview.copied')) : t(tkey('preview.copy_text'))}
            </Button>
            <Button variant="secondary" size="sm" disabled={!notice || failed} onClick={() => copy('html')}>
              {copied === 'html' ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
              {copied === 'html' ? t(tkey('preview.copied')) : t(tkey('preview.copy_html'))}
            </Button>
          </div>
        </div>

        {notice && !failed && notice.placeholders.length > 0 && (
          <p className="border-line bg-elevated text-muted border-b px-5 py-2 text-xs">{t(tkey('preview.placeholders'), { list: notice.placeholders.join(', ') })}</p>
        )}

        <div aria-busy={loading} className={`px-5 py-4 transition-opacity ${loading ? 'opacity-50' : ''}`}>
          {failed ? (
            <p className="text-danger-foreground text-sm">{t(tkey('preview.error'))}</p>
          ) : notice ? (
            <div className="space-y-4">
              {notice.sections.map((section, index) => (
                <section key={`${index}-${section.heading}`}>
                  <h3 className="text-foreground text-sm font-semibold">{section.heading}</h3>
                  {section.paragraphs.map((paragraph, paragraphIndex) => (
                    <p key={paragraphIndex} className="text-muted mt-1.5 text-sm leading-relaxed">
                      {paragraph}
                    </p>
                  ))}
                </section>
              ))}
            </div>
          ) : (
            <p className="text-subtle text-sm">{t(tkey('preview.loading'))}</p>
          )}
        </div>
      </div>
    </div>
  );
}
