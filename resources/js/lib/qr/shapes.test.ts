import { describe, it, expect } from 'vitest';
import { modulesPath, eyeBallsPath } from './shapes';
import type { QrMatrix } from './matrix';

// 3×3 fake matrix, no finders, only the centre module dark.
function single(): QrMatrix {
  return { size: 3, isFinder: () => false, isDark: (r, c) => r === 1 && c === 1 };
}
// 1×2 horizontal pair dark (row 0, cols 0-1) in a 1×2 grid.
function pair(): QrMatrix {
  return { size: 2, isFinder: () => false, isDark: (r) => r === 0 };
}

describe('modulesPath', () => {
  it('square mode emits no arc/curve commands', () => {
    const d = modulesPath(single(), 'square', 1, 10, 0);
    expect(d).not.toMatch(/[AaQqCc]/);       // no rounding
    expect(d.length).toBeGreaterThan(0);
  });
  it('rounded isolated module rounds all four corners (4 arcs)', () => {
    const d = modulesPath(single(), 'rounded', 1, 10, 0);
    expect((d.match(/A/g) ?? []).length).toBe(4);
  });
  it('rounded adjacent modules do not round the shared edge', () => {
    // A horizontal pair: the two inner corners (touching a dark neighbor) stay square,
    // so total arcs < 8 (would be 8 if each module rounded independently).
    const d = modulesPath(pair(), 'rounded', 1, 10, 0);
    expect((d.match(/A/g) ?? []).length).toBeLessThan(8);
  });
  it('dots mode emits one closed circle per module', () => {
    const d = modulesPath(single(), 'dots', 1, 10, 0);
    expect((d.match(/A/g) ?? []).length).toBeGreaterThanOrEqual(2); // circle via 2 arcs
  });
});

describe('eyeBallsPath', () => {
  it('renders three eye balls', () => {
    const m: QrMatrix = { size: 21, isFinder: () => true, isDark: () => true };
    const d = eyeBallsPath(m, 'square', 0, 10, 0);
    // three 3×3 balls → three rect subpaths (three "M" move commands minimum)
    expect((d.match(/M/g) ?? []).length).toBeGreaterThanOrEqual(3);
  });
});
