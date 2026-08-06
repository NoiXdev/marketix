import { QR_ICONS, iconToDataUrl, type QrIcon } from '../../data/qrIcons';
import type { QrGradient, QrStyle } from '../../data/qrTypes';
import { buildMatrix } from './matrix';
import { eyeBallsPath, eyeFramesPath, modulesPath } from './shapes';

export interface RenderResult {
  svg: string;
  width: number;
  height: number;
}

// ── layout constants (px) ────────────────────────────────────────────────────
const CELL = 10;
const FRAME_PAD = 16; // border padding for simple / rounded frames
const FRAME_STROKE = 4;
const FRAME_RADIUS = 20; // corner radius for the rounded frame
const BADGE_HEIGHT = 64; // extra height reserved for the bottom badge bar

// ── helpers ──────────────────────────────────────────────────────────────────

/** Round to 2 decimals and stringify (drops trailing zeros). */
const n = (v: number): string => String(Math.round(v * 100) / 100);

const clamp01 = (v: number): number => (v < 0 ? 0 : v > 1 ? 1 : v);

/** Escape a string for inclusion in XML text / attribute content. */
function escapeXml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

/**
 * Resolve the active logo data-URL, mirroring the QrPreview component:
 * custom logos come straight from `logo_data`, predefined ones are rendered
 * from the icon catalogue via `iconToDataUrl`.
 */
function getLogoUrl(style: QrStyle): string | undefined {
  if (style.logo_type === 'custom' && style.logo_data) return style.logo_data;
  if (style.logo_type === 'predefined' && style.logo_name) {
    const icon = QR_ICONS.find((i: QrIcon) => i.id === style.logo_name);
    if (icon) return iconToDataUrl(icon);
  }
  return undefined;
}

/** A gradient `<def>` with a deterministic id. Linear gradients rotate about the centre. */
function gradientDef(id: string, g: QrGradient): string {
  const stops = g.stops
    .map(
      (s) =>
        `<stop offset="${n(clamp01(s.offset))}" stop-color="${escapeXml(s.color)}"/>`,
    )
    .join('');
  if (g.type === 'radial') {
    return `<radialGradient id="${id}">${stops}</radialGradient>`;
  }
  return `<linearGradient id="${id}" gradientTransform="rotate(${n(g.rotation)} 0.5 0.5)">${stops}</linearGradient>`;
}

// ── main ─────────────────────────────────────────────────────────────────────

export function renderQr(data: string, style: QrStyle): RenderResult {
  const m = buildMatrix(data || 'https://marketix.app', style.error_correction);
  const quiet = style.quiet_zone;
  const offset = quiet * CELL;
  const inner = (m.size + quiet * 2) * CELL;

  // ── frame layout ────────────────────────────────────────────────────────
  const framed = style.frame_style === 'simple' || style.frame_style === 'rounded';
  const padX = framed ? FRAME_PAD : 0;
  const padTop = framed ? FRAME_PAD : 0;
  const padBottom =
    style.frame_style === 'badge-bottom' ? BADGE_HEIGHT : framed ? FRAME_PAD : 0;
  const width = inner + padX * 2;
  const height = inner + padTop + padBottom;

  // ── paints ──────────────────────────────────────────────────────────────
  const fgPaint = style.fg_gradient ? 'url(#qrg-fg)' : style.foreground;
  const bgPaint = style.bg_gradient ? 'url(#qrg-bg)' : style.background;
  const eyePaint = style.eye_gradient
    ? 'url(#qrg-eye)'
    : style.eye_color || fgPaint;

  // Colour used to "clear" the area behind the logo (never a gradient url).
  const clearColor =
    style.background === 'transparent' ? '#ffffff' : style.background;

  // ── defs ────────────────────────────────────────────────────────────────
  const defs: string[] = [];
  if (style.fg_gradient) defs.push(gradientDef('qrg-fg', style.fg_gradient));
  if (style.eye_gradient) defs.push(gradientDef('qrg-eye', style.eye_gradient));
  if (style.bg_gradient) defs.push(gradientDef('qrg-bg', style.bg_gradient));

  const layers: string[] = [];

  // ── frame background (whole canvas) ───────────────────────────────────────
  if (style.frame_style !== 'none') {
    const rx = style.frame_style === 'rounded' ? FRAME_RADIUS : 0;
    layers.push(
      `<rect x="0" y="0" width="${n(width)}" height="${n(height)}" rx="${n(rx)}" fill="${escapeXml(style.frame_background)}"/>`,
    );
  }

  // ── QR background (module area) ───────────────────────────────────────────
  if (style.background !== 'transparent') {
    layers.push(
      `<rect class="qr-bg" x="${n(padX)}" y="${n(padTop)}" width="${n(inner)}" height="${n(inner)}" fill="${escapeXml(bgPaint)}"/>`,
    );
  }

  // ── modules + eyes (translated into the framed content box) ───────────────
  const modules = modulesPath(m, style.module_mode, style.module_rounding, CELL, offset);
  const frames = eyeFramesPath(m, style.eye_frame_mode, style.eye_frame_rounding, CELL, offset);
  const balls = eyeBallsPath(m, style.eye_ball_mode, style.eye_ball_rounding, CELL, offset);

  const content: string[] = [];
  if (modules) content.push(`<path d="${modules}" fill="${escapeXml(fgPaint)}"/>`);
  if (frames)
    content.push(
      `<path d="${frames}" fill="${escapeXml(eyePaint)}" fill-rule="evenodd"/>`,
    );
  if (balls) content.push(`<path d="${balls}" fill="${escapeXml(eyePaint)}"/>`);

  // ── logo ──────────────────────────────────────────────────────────────────
  const logoUrl = getLogoUrl(style);
  if (logoUrl && style.logo_size > 0) {
    const side = inner * (style.logo_size / 100);
    const margin = style.logo_margin * CELL;
    const cx = inner / 2;
    const cy = inner / 2;
    if (style.logo_clear_modules) {
      const clearSide = side + margin * 2;
      content.push(
        `<rect x="${n(cx - clearSide / 2)}" y="${n(cy - clearSide / 2)}" width="${n(clearSide)}" height="${n(clearSide)}" fill="${escapeXml(clearColor)}"/>`,
      );
    }
    content.push(
      `<image href="${escapeXml(logoUrl)}" x="${n(cx - side / 2)}" y="${n(cy - side / 2)}" width="${n(side)}" height="${n(side)}" preserveAspectRatio="xMidYMid meet"/>`,
    );
  }

  layers.push(`<g transform="translate(${n(padX)} ${n(padTop)})">${content.join('')}</g>`);

  // ── frame foreground ──────────────────────────────────────────────────────
  if (framed) {
    const rx = style.frame_style === 'rounded' ? FRAME_RADIUS : 0;
    const inset = FRAME_STROKE / 2;
    layers.push(
      `<rect x="${n(inset)}" y="${n(inset)}" width="${n(width - FRAME_STROKE)}" height="${n(height - FRAME_STROKE)}" rx="${n(rx)}" fill="none" stroke="${escapeXml(style.frame_color)}" stroke-width="${n(FRAME_STROKE)}"/>`,
    );
  } else if (style.frame_style === 'badge-bottom') {
    const barY = inner;
    layers.push(
      `<rect x="0" y="${n(barY)}" width="${n(width)}" height="${n(BADGE_HEIGHT)}" fill="${escapeXml(style.frame_color)}"/>`,
    );
    layers.push(
      `<text x="${n(width / 2)}" y="${n(barY + BADGE_HEIGHT / 2)}" text-anchor="middle" dominant-baseline="central" font-family="system-ui, sans-serif" font-size="${n(BADGE_HEIGHT * 0.44)}" font-weight="600" fill="${escapeXml(style.frame_text_color)}">${escapeXml(style.frame_text)}</text>`,
    );
  }

  const defsBlock = defs.length ? `<defs>${defs.join('')}</defs>` : '';
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="${n(width)}" height="${n(height)}" viewBox="0 0 ${n(width)} ${n(height)}">` +
    defsBlock +
    layers.join('') +
    `</svg>`;

  return { svg, width, height };
}
