export type Severity = 'error' | 'warning' | 'notice' | 'info';

const RANK: Record<Severity, number> = { error: 3, warning: 2, notice: 1, info: 0 };
const DOT: Record<Severity, string> = {
  error: 'bg-danger-foreground',
  warning: 'bg-warning-foreground',
  notice: 'bg-neutral-foreground',
  info: 'bg-neutral-foreground',
};

export function severityRank(sev: Severity): number {
  return RANK[sev] ?? 0;
}

export function severityDotClass(sev: Severity): string {
  return DOT[sev] ?? 'bg-neutral-foreground';
}

export function severityBadgeVariant(sev: Severity): 'danger' | 'warning' | 'neutral' {
  return sev === 'error' ? 'danger' : sev === 'warning' ? 'warning' : 'neutral';
}
