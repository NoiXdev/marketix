import { describe, expect, it } from 'vitest';
import { parseVCards, mergeVCardIntoContent, extraSummary } from './vcard';

const card = (body: string) => `BEGIN:VCARD\nVERSION:3.0\n${body}\nEND:VCARD`;

describe('parseVCards', () => {
  it('returns [] for garbage input', () => {
    expect(parseVCards('not a vcard')).toEqual([]);
    expect(parseVCards('')).toEqual([]);
  });

  it('maps the six known properties', () => {
    const [c] = parseVCards(card(
      'FN:Jane Doe\nORG:Acme\nTEL:+49 30 1\nEMAIL:jane@example.com\nURL:https://example.com\nADR:;;Main St;Berlin;;12345;Germany',
    ));
    expect(c.fields).toEqual({
      name: 'Jane Doe', org: 'Acme', phone: '+49 30 1',
      email: 'jane@example.com', url: 'https://example.com',
      address: 'Main St, Berlin, 12345, Germany',
    });
    expect(c.extra).toEqual([]);
    expect(c.displayName).toBe('Jane Doe');
  });

  it('keeps the first TEL/EMAIL and preserves the rest as extras', () => {
    const [c] = parseVCards(card('FN:Jane\nTEL:111\nTEL;TYPE=HOME:222\nEMAIL:a@x.io\nEMAIL:b@x.io'));
    expect(c.fields.phone).toBe('111');
    expect(c.fields.email).toBe('a@x.io');
    expect(c.extra).toEqual(['TEL;TYPE=HOME:222', 'EMAIL:b@x.io']);
  });

  it('preserves unmapped properties verbatim, including N and parameters', () => {
    const [c] = parseVCards(card('FN:Jane\nN:Doe;Jane;;;\nTITLE:CTO\nBDAY:1990-01-01'));
    expect(c.extra).toEqual(['N:Doe;Jane;;;', 'TITLE:CTO', 'BDAY:1990-01-01']);
  });

  it('derives the name from N when FN is absent', () => {
    const [c] = parseVCards(card('N:Doe;Jane;;;\nTEL:111'));
    expect(c.fields.name).toBe('Jane Doe');
    expect(c.displayName).toBe('Jane Doe');
  });

  it('unfolds folded continuation lines', () => {
    const [c] = parseVCards('BEGIN:VCARD\nVERSION:3.0\nFN:Jane\nNOTE:line one\n  line two\nEND:VCARD');
    expect(c.extra).toEqual(['NOTE:line one line two']);
  });

  it('parses multiple contacts', () => {
    const cards = parseVCards(`${card('FN:Alice')}\n${card('FN:Bob')}`);
    expect(cards.map(c => c.displayName)).toEqual(['Alice', 'Bob']);
  });

  it('falls back to email then a generated label for displayName', () => {
    const cards = parseVCards(`${card('EMAIL:only@x.io')}\n${card('TEL:999')}`);
    expect(cards[0].displayName).toBe('only@x.io');
    expect(cards[1].displayName).toBe('Contact 2');
  });

  it('unescapes common sequences in mapped field values', () => {
    const [c] = parseVCards(card('FN:Doe\\, Jane\nORG:A\\;B'));
    expect(c.fields.name).toBe('Doe, Jane');
    expect(c.fields.org).toBe('A;B');
  });
});

describe('mergeVCardIntoContent', () => {
  it('overwrites only fields the card provides and joins extras', () => {
    const content = { name: 'Old', org: 'OldOrg', phone: '', email: '', url: '', address: '' };
    const parsed = {
      displayName: 'New',
      fields: { name: 'New', org: '', phone: '123', email: '', url: '', address: '' },
      extra: ['TITLE:CTO', 'BDAY:1990'],
    };
    const out = mergeVCardIntoContent(content, parsed);
    expect(out.name).toBe('New');
    expect(out.org).toBe('OldOrg');
    expect(out.phone).toBe('123');
    expect(out.extra).toBe('TITLE:CTO\nBDAY:1990');
  });

  it('clears extras when the card has none', () => {
    const out = mergeVCardIntoContent({ extra: 'OLD:x' }, {
      displayName: 'X', fields: { name: 'X' }, extra: [],
    });
    expect(out.extra).toBe('');
  });
});

describe('extraSummary', () => {
  it('counts lines and lists property names', () => {
    expect(extraSummary('TITLE:CTO\nBDAY:1990\nTEL;TYPE=HOME:222'))
      .toEqual({ count: 3, names: ['TITLE', 'BDAY', 'TEL'] });
  });

  it('handles empty/undefined', () => {
    expect(extraSummary('')).toEqual({ count: 0, names: [] });
    expect(extraSummary(undefined)).toEqual({ count: 0, names: [] });
  });
});
