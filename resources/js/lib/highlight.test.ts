import { describe, expect, it } from 'vitest';
import { highlightJs } from './highlight';

describe('highlightJs', () => {
  it('classifies function calls, strings, keys, numbers and punctuation', () => {
    const tokens = highlightJs("marketix('event', 'purchase', { value: 49.90, currency: 'CHF' });");
    const of = (kind: string) => tokens.filter((t) => t.kind === kind).map((t) => t.text);

    expect(of('function')).toEqual(['marketix']);
    expect(of('string')).toEqual(["'event'", "'purchase'", "'CHF'"]);
    expect(of('key')).toEqual(['value', 'currency']);
    expect(of('number')).toEqual(['49.90']);
    expect(of('punctuation')).toContain('{');
  });

  it('keeps the original text intact', () => {
    const code = "const ok = true; marketix('404');";
    expect(highlightJs(code).map((t) => t.text).join('')).toBe(code);
  });

  it('does not split escaped quotes inside strings', () => {
    expect(highlightJs("say('it\\'s')").filter((t) => t.kind === 'string').map((t) => t.text)).toEqual(["'it\\'s'"]);
  });
});
