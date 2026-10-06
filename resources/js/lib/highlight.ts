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

const HTML_TAG = /<[^>]*>/g;

const HTML_TAG_PART = /(<\/?|\/?>)|("[^"]*"|'[^']*')|([^\s=<>/"']+)(?=\s*=)|(=)|([^\s=<>/"']+)/g;

function highlightTag(tag: string): Token[] {
  const tokens: Token[] = [];
  let last = 0;
  let named = false;

  for (const match of tag.matchAll(HTML_TAG_PART)) {
    const index = match.index ?? 0;
    if (index > last) tokens.push({ kind: 'plain', text: tag.slice(last, index) });
    const [text, bracket, value, attribute, equals, word] = match;
    let kind: TokenKind = 'plain';
    if (bracket || equals) kind = 'punctuation';
    else if (value) kind = 'string';
    else if (attribute) kind = 'key';
    else if (word) {
      kind = named ? 'key' : 'keyword';
      named = true;
    }
    tokens.push({ kind, text });
    last = index + text.length;
  }

  if (last < tag.length) tokens.push({ kind: 'plain', text: tag.slice(last) });

  return tokens;
}

export function highlightHtml(code: string): Token[] {
  const tokens: Token[] = [];
  let last = 0;

  for (const match of code.matchAll(HTML_TAG)) {
    const index = match.index ?? 0;
    if (index > last) tokens.push({ kind: 'plain', text: code.slice(last, index) });
    tokens.push(...highlightTag(match[0]));
    last = index + match[0].length;
  }

  if (last < code.length) tokens.push({ kind: 'plain', text: code.slice(last) });

  return tokens;
}
