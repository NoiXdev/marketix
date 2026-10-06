export type TokenKind = 'string' | 'number' | 'keyword' | 'function' | 'key' | 'punctuation' | 'plain';

export type Token = { kind: TokenKind; text: string };

const PATTERN =
  /('(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")|(\b\d+(?:\.\d+)?\b)|\b(true|false|null|undefined|const|let|var|function|return)\b|([A-Za-z_$][\w$]*)(?=\s*\()|([A-Za-z_$][\w$]*)(?=\s*:)|([{}()[\],;:.=])/g;

const KINDS: TokenKind[] = ['string', 'number', 'keyword', 'function', 'key', 'punctuation'];

export function highlightJs(code: string): Token[] {
  const tokens: Token[] = [];
  let last = 0;

  for (const match of code.matchAll(PATTERN)) {
    const index = match.index ?? 0;
    if (index > last) tokens.push({ kind: 'plain', text: code.slice(last, index) });
    const group = match.slice(1).findIndex((value) => value !== undefined);
    tokens.push({ kind: KINDS[group] ?? 'plain', text: match[0] });
    last = index + match[0].length;
  }

  if (last < code.length) tokens.push({ kind: 'plain', text: code.slice(last) });

  return tokens;
}
