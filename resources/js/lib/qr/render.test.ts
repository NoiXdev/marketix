import { describe, it, expect } from 'vitest';
import { renderQr } from './render';
import { DEFAULT_STYLE } from '../../data/qrTypes';

describe('renderQr', () => {
  it('returns a well-formed svg with matching viewBox', () => {
    const r = renderQr('https://marketix.app', DEFAULT_STYLE);
    expect(r.svg.startsWith('<svg')).toBe(true);
    expect(r.svg).toContain(`viewBox="0 0 ${r.width} ${r.height}"`);
    expect(r.svg).toContain('</svg>');
  });
  it('emits a linearGradient def when fg_gradient is set', () => {
    const r = renderQr('x', { ...DEFAULT_STYLE, fg_gradient: {
      type: 'linear', rotation: 45, stops: [{offset:0,color:'#111'},{offset:1,color:'#999'}] } });
    expect(r.svg).toContain('<linearGradient');
    expect(r.svg).toContain('url(#');
  });
  it('renders a transparent background (no bg rect) when background is "transparent"', () => {
    const r = renderQr('x', { ...DEFAULT_STYLE, background: 'transparent' });
    expect(r.svg).not.toMatch(/<rect[^>]+class="qr-bg"/);
  });
  it('reserves extra height for a bottom badge frame', () => {
    const plain = renderQr('x', DEFAULT_STYLE);
    const badge = renderQr('x', { ...DEFAULT_STYLE, frame_style: 'badge-bottom', frame_text: 'Scan me' });
    expect(badge.height).toBeGreaterThan(plain.height);
    expect(badge.svg).toContain('Scan me');
  });
});
