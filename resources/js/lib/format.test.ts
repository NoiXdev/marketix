import { describe, expect, it } from 'vitest';
import { formatAxisMoney, formatMoney, formatPercent, pointChange } from './format';

describe('formatPercent', () => {
  it('always shows one decimal', () => {
    expect(formatPercent(4, 'en')).toBe('4.0 %');
    expect(formatPercent(7.06, 'en')).toBe('7.1 %');
  });
});

describe('pointChange', () => {
  it('returns the difference in percentage points', () => {
    expect(pointChange(72.5, 73.3)).toBe(-0.8);
    expect(pointChange(29.8, 20.7)).toBe(9.1);
  });
});

describe('formatAxisMoney', () => {
  it('groups thousands and only abbreviates millions', () => {
    expect(formatAxisMoney(7500, 'USD', 'en')).toBe('$7,500');
    expect(formatAxisMoney(2_500_000, 'USD', 'en')).toBe('$2.5M');
  });
});

describe('formatMoney', () => {
  it('drops the cents from large amounts only', () => {
    expect(formatMoney(139145, 'USD', 'en')).toBe('$139,145');
    expect(formatMoney(498.73, 'USD', 'en')).toBe('$498.73');
  });
});
