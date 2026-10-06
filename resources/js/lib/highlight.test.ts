import { describe, expect, it } from 'vitest';
import { highlightHtml, highlightJs } from './highlight';

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
    expect(
      highlightJs(code)
        .map((t) => t.text)
        .join(''),
    ).toBe(code);
  });

  it('does not split escaped quotes inside strings', () => {
    expect(
      highlightJs("say('it\\'s')")
        .filter((t) => t.kind === 'string')
        .map((t) => t.text),
    ).toEqual(["'it\\'s'"]);
  });
});

describe('highlightHtml', () => {
  const snippet = '<script defer data-site="abc123" src="https://example.com/mx.js"></script>';
  const of = (kind: string) =>
    highlightHtml(snippet)
      .filter((t) => t.kind === kind)
      .map((t) => t.text);

  it('classifies tag names, attributes, values and brackets', () => {
    expect(of('keyword')).toEqual(['script', 'script']);
    expect(of('key')).toEqual(['defer', 'data-site', 'src']);
    expect(of('string')).toEqual(['"abc123"', '"https://example.com/mx.js"']);
    expect(of('punctuation')).toEqual(['<', '=', '=', '>', '</', '>']);
  });

  it('keeps the original text intact including text between tags', () => {
    const code = '<p class="x">Hello <b>world</b></p>';
    expect(
      highlightHtml(code)
        .map((t) => t.text)
        .join(''),
    ).toBe(code);
  });
});
