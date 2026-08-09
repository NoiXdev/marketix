import { describe, it, expect } from 'vitest';
import { buildMatrix } from './matrix';

describe('buildMatrix', () => {
  it('produces a square matrix with odd finder-aligned size', () => {
    const m = buildMatrix('https://marketix.app', 'Q');
    expect(m.size).toBeGreaterThanOrEqual(21);
    expect((m.size - 21) % 4).toBe(0);           // valid QR versions: 21,25,29,...
  });
  it('marks the three finder regions', () => {
    const m = buildMatrix('hello', 'Q');
    expect(m.isFinder(0, 0)).toBe(true);         // top-left
    expect(m.isFinder(0, m.size - 1)).toBe(true);// top-right
    expect(m.isFinder(m.size - 1, 0)).toBe(true);// bottom-left
    expect(m.isFinder(m.size - 1, m.size - 1)).toBe(false); // no finder bottom-right
    expect(m.isFinder(Math.floor(m.size/2), Math.floor(m.size/2))).toBe(false);
  });
  it('is out-of-range safe', () => {
    const m = buildMatrix('x', 'L');
    expect(m.isDark(-1, 0)).toBe(false);
    expect(m.isDark(0, m.size)).toBe(false);
  });
});
