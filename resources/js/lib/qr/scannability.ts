import type { QrEcc, QrStyle } from '../../data/qrTypes';

export type ScanLevel = 'good' | 'warn' | 'bad';

export interface ScanIssue {
  code: 'contrast' | 'logo_ecc' | 'quiet_zone';
  level: ScanLevel;
  message: string;
}

export interface ScanReport {
  level: ScanLevel;
  issues: ScanIssue[];
  contrastRatio: number;
}

const ECC_RESERVE: Record<QrEcc, number> = { L: 0.07, M: 0.15, Q: 0.25, H: 0.30 };

function hexToRgb(hex: string): [number, number, number] {
  let h = hex.replace('#', '');
  if (h.length === 3) {
    h = h.split('').map(c => c + c).join('');
  }
  const num = parseInt(h, 16);
  return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
}

function relativeLuminance(hex: string): number {
  const [r, g, b] = hexToRgb(hex).map(v => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrastRatio(foreground: string, background: string): number {
  const bg = background === 'transparent' ? '#ffffff' : background;
  const l1raw = relativeLuminance(foreground);
  const l2raw = relativeLuminance(bg);
  const l1 = Math.max(l1raw, l2raw);
  const l2 = Math.min(l1raw, l2raw);
  return (l1 + 0.05) / (l2 + 0.05);
}

function levelRank(level: ScanLevel): number {
  return level === 'bad' ? 2 : level === 'warn' ? 1 : 0;
}

export function scannability(style: QrStyle): ScanReport {
  const issues: ScanIssue[] = [];
  const ratio = contrastRatio(style.foreground, style.background);

  if (ratio < 2.5) {
    issues.push({ code: 'contrast', level: 'bad', message: 'Contrast ratio is too low for reliable scanning.' });
  } else if (ratio < 4) {
    issues.push({ code: 'contrast', level: 'warn', message: 'Contrast ratio is low; consider darker/lighter colors.' });
  }

  if (style.logo_type !== 'none') {
    const coverage = Math.pow(style.logo_size / 100, 2);
    const reserve = ECC_RESERVE[style.error_correction];
    if (coverage > reserve) {
      issues.push({ code: 'logo_ecc', level: 'bad', message: 'Logo is too large for the selected error-correction level.' });
    } else if (coverage > reserve * 0.7) {
      issues.push({ code: 'logo_ecc', level: 'warn', message: 'Logo size is close to the error-correction budget; consider a higher ECC level.' });
    }
  }

  if (style.quiet_zone < 4) {
    issues.push({ code: 'quiet_zone', level: 'warn', message: 'Quiet zone is smaller than recommended (4 modules).' });
  }

  const level: ScanLevel = issues.reduce<ScanLevel>(
    (worst, issue) => (levelRank(issue.level) > levelRank(worst) ? issue.level : worst),
    'good',
  );

  return { level, issues, contrastRatio: ratio };
}
