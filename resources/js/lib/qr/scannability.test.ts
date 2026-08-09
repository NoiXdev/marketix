import { describe, it, expect } from 'vitest';
import { scannability } from './scannability';
import { DEFAULT_STYLE } from '../../data/qrTypes';

describe('scannability', () => {
  it('black on white with no logo is good', () => {
    const r = scannability(DEFAULT_STYLE);
    expect(r.level).toBe('good');
    expect(r.issues).toHaveLength(0);
    expect(r.contrastRatio).toBeGreaterThan(20);
  });
  it('flags low contrast as bad', () => {
    const r = scannability({ ...DEFAULT_STYLE, foreground: '#888888', background: '#777777' });
    expect(r.issues.some(i => i.code === 'contrast')).toBe(true);
    expect(r.level).toBe('bad');
  });
  it('flags an oversized logo against a low ECC level', () => {
    const r = scannability({ ...DEFAULT_STYLE, error_correction: 'L', logo_type: 'custom', logo_data: 'x', logo_size: 40 });
    expect(r.issues.some(i => i.code === 'logo_ecc')).toBe(true);
  });
  it('flags a small quiet zone', () => {
    const r = scannability({ ...DEFAULT_STYLE, quiet_zone: 1 });
    expect(r.issues.some(i => i.code === 'quiet_zone')).toBe(true);
  });
});
