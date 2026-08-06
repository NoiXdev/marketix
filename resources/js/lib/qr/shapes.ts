import type { EyeBallMode, EyeFrameMode, ModuleMode } from '@/data/qrTypes';
import type { QrMatrix } from './matrix';

// ── helpers ─────────────────────────────────────────────────────────────────

/** Round to 2 decimals and stringify (drops trailing zeros). */
const n = (v: number): string => String(Math.round(v * 100) / 100);

const clamp01 = (v: number): number => (v < 0 ? 0 : v > 1 ? 1 : v);

/** Sharp rectangle subpath (no arcs/curves). */
function rectPath(x: number, y: number, w: number, h: number): string {
  return `M ${n(x)} ${n(y)} h ${n(w)} v ${n(h)} h ${n(-w)} Z`;
}

/**
 * Rounded rectangle walked clockwise (SVG y-down), inserting a quarter `A`
 * arc at each corner flagged to round and a sharp `L` at each square corner.
 */
function roundedRectPath(
  x: number,
  y: number,
  size: number,
  rad: number,
  tl: boolean,
  tr: boolean,
  br: boolean,
  bl: boolean,
): string {
  const rTL = tl ? rad : 0;
  const rTR = tr ? rad : 0;
  const rBR = br ? rad : 0;
  const rBL = bl ? rad : 0;
  const parts: string[] = [];
  // start on the top edge, just right of the top-left corner
  parts.push(`M ${n(x + rTL)} ${n(y)}`);
  // top edge → top-right corner
  parts.push(`L ${n(x + size - rTR)} ${n(y)}`);
  parts.push(
    rTR > 0
      ? `A ${n(rTR)} ${n(rTR)} 0 0 1 ${n(x + size)} ${n(y + rTR)}`
      : `L ${n(x + size)} ${n(y)}`,
  );
  // right edge → bottom-right corner
  parts.push(`L ${n(x + size)} ${n(y + size - rBR)}`);
  parts.push(
    rBR > 0
      ? `A ${n(rBR)} ${n(rBR)} 0 0 1 ${n(x + size - rBR)} ${n(y + size)}`
      : `L ${n(x + size)} ${n(y + size)}`,
  );
  // bottom edge → bottom-left corner
  parts.push(`L ${n(x + rBL)} ${n(y + size)}`);
  parts.push(
    rBL > 0
      ? `A ${n(rBL)} ${n(rBL)} 0 0 1 ${n(x)} ${n(y + size - rBL)}`
      : `L ${n(x)} ${n(y + size)}`,
  );
  // left edge → top-left corner
  parts.push(`L ${n(x)} ${n(y + rTL)}`);
  parts.push(
    rTL > 0
      ? `A ${n(rTL)} ${n(rTL)} 0 0 1 ${n(x + rTL)} ${n(y)}`
      : `L ${n(x)} ${n(y)}`,
  );
  parts.push('Z');
  return parts.join(' ');
}

/** Full circle drawn with two `A` arc commands (semicircles). */
function circlePath(cx: number, cy: number, rad: number): string {
  return (
    `M ${n(cx - rad)} ${n(cy)} ` +
    `A ${n(rad)} ${n(rad)} 0 1 0 ${n(cx + rad)} ${n(cy)} ` +
    `A ${n(rad)} ${n(rad)} 0 1 0 ${n(cx - rad)} ${n(cy)} Z`
  );
}

// The three finder origins (row, col). Bottom-right is intentionally excluded.
function finderOrigins(size: number): [number, number][] {
  return [
    [0, 0],
    [0, size - 7],
    [size - 7, 0],
  ];
}

// ── module field ──────────────────────────────────────────────────────────

/**
 * SVG path `d` for every dark, non-finder module.
 *
 * - `square`: plain rectangle per module.
 * - `dots`: one full circle per module, never merged.
 * - `rounded` / `classy`: neighbor-aware rounded rectangle — a corner rounds
 *   only when BOTH orthogonal neighbors meeting there are light. `classy`
 *   additionally forces the top-right and bottom-left corners square so only
 *   the top-left / bottom-right diagonal pair may round.
 */
export function modulesPath(
  matrix: QrMatrix,
  mode: ModuleMode,
  rounding: number,
  cell: number,
  offset: number,
): string {
  const { size, isDark, isFinder } = matrix;
  const rad = clamp01(rounding) * (cell / 2);
  const dotRad = 0.5 * cell * Math.max(clamp01(rounding), 0.6);
  const parts: string[] = [];

  for (let r = 0; r < size; r++) {
    for (let c = 0; c < size; c++) {
      if (!isDark(r, c) || isFinder(r, c)) continue;
      const x = offset + c * cell;
      const y = offset + r * cell;

      if (mode === 'square') {
        parts.push(rectPath(x, y, cell, cell));
        continue;
      }
      if (mode === 'dots') {
        parts.push(circlePath(x + cell / 2, y + cell / 2, dotRad));
        continue;
      }

      // rounded | classy — neighbor-aware corners
      const up = isDark(r - 1, c);
      const down = isDark(r + 1, c);
      const left = isDark(r, c - 1);
      const right = isDark(r, c + 1);
      const tl = !up && !left;
      let tr = !up && !right;
      const br = !down && !right;
      let bl = !down && !left;
      if (mode === 'classy') {
        tr = false;
        bl = false;
      }
      parts.push(roundedRectPath(x, y, cell, rad, tl, tr, br, bl));
    }
  }

  return parts.join(' ');
}

// ── eye frames (7×7 outer ring) ─────────────────────────────────────────────

/**
 * SVG path `d` for the three finder frames: a 7×7 outer ring (outer square
 * minus the inner 5×5 hole) per finder, rendered as two subpaths for even-odd
 * fill. `dots` → circular ring, `rounded` → rounded-square ring, `square` → sharp.
 */
export function eyeFramesPath(
  matrix: QrMatrix,
  mode: EyeFrameMode,
  rounding: number,
  cell: number,
  offset: number,
): string {
  const { size } = matrix;
  const parts: string[] = [];

  for (const [r0, c0] of finderOrigins(size)) {
    const ox = offset + c0 * cell;
    const oy = offset + r0 * cell;
    const outer = 7 * cell;
    const ix = ox + cell;
    const iy = oy + cell;
    const inner = 5 * cell;

    if (mode === 'dots') {
      // outer circle + inner circle
      const ocx = ox + outer / 2;
      const ocy = oy + outer / 2;
      parts.push(circlePath(ocx, ocy, outer / 2));
      parts.push(circlePath(ocx, ocy, inner / 2));
      continue;
    }
    if (mode === 'rounded') {
      const outerRad = clamp01(rounding) * cell * 3.5; // up to outer/2
      const innerRad = clamp01(rounding) * cell * 2.5; // up to inner/2
      parts.push(roundedRectPath(ox, oy, outer, outerRad, true, true, true, true));
      parts.push(roundedRectPath(ix, iy, inner, innerRad, true, true, true, true));
      continue;
    }
    // square (sharp)
    parts.push(rectPath(ox, oy, outer, outer));
    parts.push(rectPath(ix, iy, inner, inner));
  }

  return parts.join(' ');
}

// ── eye balls (inner 3×3) ────────────────────────────────────────────────────

/**
 * SVG path `d` for the three finder balls: the inner 3×3 block at each finder
 * origin + (2,2). `square` sharp, `rounded` rounded by `rounding`, `dot` circle.
 */
export function eyeBallsPath(
  matrix: QrMatrix,
  mode: EyeBallMode,
  rounding: number,
  cell: number,
  offset: number,
): string {
  const { size } = matrix;
  const parts: string[] = [];

  for (const [r0, c0] of finderOrigins(size)) {
    const bx = offset + (c0 + 2) * cell;
    const by = offset + (r0 + 2) * cell;
    const ball = 3 * cell;

    if (mode === 'dot') {
      parts.push(circlePath(bx + ball / 2, by + ball / 2, ball / 2));
      continue;
    }
    if (mode === 'rounded') {
      const rad = clamp01(rounding) * (ball / 2);
      parts.push(roundedRectPath(bx, by, ball, rad, true, true, true, true));
      continue;
    }
    // square (sharp)
    parts.push(rectPath(bx, by, ball, ball));
  }

  return parts.join(' ');
}
