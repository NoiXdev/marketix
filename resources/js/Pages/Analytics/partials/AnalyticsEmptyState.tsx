import CodeSnippet, { CodeLanguage } from '@/Components/CodeSnippet';
import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

export default function AnalyticsEmptyState({
  icon: Icon,
  title,
  text,
  code,
  codeLanguage = 'js',
  action,
  framed = false,
}: {
  icon: LucideIcon;
  title: string;
  text?: string;
  code?: string;
  codeLanguage?: CodeLanguage;
  action?: ReactNode;
  framed?: boolean;
}) {
  return (
    <div className={`flex flex-col items-center px-6 text-center ${framed ? 'border-line bg-surface rounded-[var(--radius)] border border-dashed py-10' : 'py-8'}`}>
      <span className="bg-accent-soft text-accent-soft-foreground mb-3 grid h-10 w-10 place-items-center rounded-full">
        <Icon className="h-5 w-5" />
      </span>
      <p className="text-foreground text-sm font-semibold">{title}</p>
      {text && <p className="text-muted mt-1 max-w-lg text-sm">{text}</p>}
      {code && <CodeSnippet code={code} language={codeLanguage} wrap={codeLanguage === 'html'} className="mt-4 max-w-2xl" />}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
