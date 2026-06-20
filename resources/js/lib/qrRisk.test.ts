import { describe, expect, it } from 'vitest';
import { isRiskyEdit, QrEditState } from './qrRisk';

const base: QrEditState = {
  type: 'link',
  is_dynamic: true,
  domain_id: 'dom1',
  slug: 'promo',
  content: { url: 'https://example.com/a' },
  style: { foreground: '#000', background: '#fff' } as QrEditState['style'],
};

describe('isRiskyEdit', () => {
  it('is safe when nothing relevant changed', () => {
    expect(isRiskyEdit(base, { ...base })).toBe(false);
  });

  it('is safe when only a dynamic redirect target changes', () => {
    expect(isRiskyEdit(base, { ...base, content: { url: 'https://example.com/b' } })).toBe(false);
  });

  it('is risky when a dynamic slug changes (encoded short link changes)', () => {
    expect(isRiskyEdit(base, { ...base, slug: 'promo-2' })).toBe(true);
  });

  it('is risky when a dynamic domain changes', () => {
    expect(isRiskyEdit(base, { ...base, domain_id: 'dom2' })).toBe(true);
  });

  it('is risky when the type changes', () => {
    expect(isRiskyEdit(base, { ...base, type: 'email' })).toBe(true);
  });

  it('is risky when the mode switches', () => {
    expect(isRiskyEdit(base, { ...base, is_dynamic: false })).toBe(true);
  });

  it('is risky when the style changes', () => {
    expect(isRiskyEdit(base, { ...base, style: { ...base.style, foreground: '#fff' } })).toBe(true);
  });

  it('is risky when a static content field changes', () => {
    const staticState: QrEditState = { ...base, is_dynamic: false, type: 'text', content: { text: 'hi' } };
    expect(isRiskyEdit(staticState, { ...staticState, content: { text: 'bye' } })).toBe(true);
  });

  it('is safe when a static content is unchanged', () => {
    const staticState: QrEditState = { ...base, is_dynamic: false, type: 'text', content: { text: 'hi' } };
    expect(isRiskyEdit(staticState, { ...staticState })).toBe(false);
  });
});
