export interface ParsedVCard {
  displayName: string;
  fields: Record<string, string>;
  extra: string[];
}

// CRLF/CR/LF followed by a single space or tab is a folded continuation line.
const FOLD = /\r\n[ \t]|\n[ \t]|\r[ \t]/g;

function unescape(value: string): string {
  return value
    .replace(/\\n/gi, '\n')
    .replace(/\\,/g, ',')
    .replace(/\\;/g, ';')
    .replace(/\\\\/g, '\\');
}

// Property name = text before the first ':' , minus any ';' parameters, uppercased.
function propName(line: string): string {
  const colon = line.indexOf(':');
  const head = colon === -1 ? line : line.slice(0, colon);
  const semi = head.indexOf(';');
  return (semi === -1 ? head : head.slice(0, semi)).toUpperCase().trim();
}

function propValue(line: string): string {
  const colon = line.indexOf(':');
  return colon === -1 ? '' : line.slice(colon + 1);
}

// ADR is ';'-separated structured parts; join the non-empty ones readably.
function joinAddress(value: string): string {
  return value.split(';').map(s => s.trim()).filter(Boolean).join(', ');
}

// N is family;given;additional;prefix;suffix → "Given Family".
function nameFromN(value: string): string {
  const parts = value.split(';');
  const family = (parts[0] ?? '').trim();
  const given = (parts[1] ?? '').trim();
  return [given, family].filter(Boolean).join(' ');
}

export function parseVCards(text: string): ParsedVCard[] {
  const lines = text.replace(FOLD, '').split(/\r\n|\r|\n/);

  const cards: ParsedVCard[] = [];
  let cur: ParsedVCard | null = null;
  let nValue = '';

  for (const raw of lines) {
    const line = raw.trimEnd();
    if (!line) continue;
    const name = propName(line);

    if (name === 'BEGIN' && propValue(line).toUpperCase() === 'VCARD') {
      cur = { displayName: '', fields: { name: '', org: '', phone: '', email: '', url: '', address: '' }, extra: [] };
      nValue = '';
      continue;
    }
    if (name === 'END' && propValue(line).toUpperCase() === 'VCARD') {
      if (cur) {
        if (!cur.fields.name && nValue) cur.fields.name = nameFromN(nValue);
        cur.displayName = cur.fields.name || cur.fields.email || `Contact ${cards.length + 1}`;
        cards.push(cur);
      }
      cur = null;
      continue;
    }
    if (!cur || name === 'VERSION') continue;

    const value = propValue(line);
    switch (name) {
      case 'FN':    if (!cur.fields.name)    { cur.fields.name = unescape(value); continue; } break;
      case 'ORG':   if (!cur.fields.org)     { cur.fields.org = unescape(value); continue; } break;
      case 'TEL':   if (!cur.fields.phone)   { cur.fields.phone = unescape(value); continue; } break;
      case 'EMAIL': if (!cur.fields.email)   { cur.fields.email = unescape(value); continue; } break;
      case 'URL':   if (!cur.fields.url)     { cur.fields.url = unescape(value); continue; } break;
      case 'ADR':   if (!cur.fields.address) { cur.fields.address = joinAddress(value); continue; } break;
      case 'N':     if (!nValue) nValue = value; break; // captured for fallback, also preserved below
    }
    cur.extra.push(line);
  }

  return cards;
}

export function mergeVCardIntoContent(
  content: Record<string, string>,
  parsed: ParsedVCard,
): Record<string, string> {
  const next: Record<string, string> = { ...content };
  for (const [key, value] of Object.entries(parsed.fields)) {
    if (value) next[key] = value;
  }
  next.extra = parsed.extra.join('\n');
  return next;
}

export function extraSummary(extra: string | undefined): { count: number; names: string[] } {
  const lines = (extra ?? '').split('\n').map(l => l.trim()).filter(Boolean);
  return { count: lines.length, names: lines.map(propName) };
}
