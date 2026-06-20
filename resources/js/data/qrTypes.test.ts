import { describe, expect, it } from 'vitest';
import { buildQrContent } from './qrTypes';

describe('buildQrContent vcard', () => {
  it('emits mapped fields then extras then END', () => {
    const out = buildQrContent('vcard', false, {
      name: 'Jane', org: '', phone: '+49 30 1', email: '', url: '', address: '',
      extra: 'TITLE:CTO\nBDAY:1990-01-01',
    });
    expect(out).toBe(
      'BEGIN:VCARD\nVERSION:3.0\nFN:Jane\nTEL:+49 30 1\nTITLE:CTO\nBDAY:1990-01-01\nEND:VCARD',
    );
  });

  it('omits extras when absent', () => {
    const out = buildQrContent('vcard', false, { name: 'Jane' });
    expect(out).toBe('BEGIN:VCARD\nVERSION:3.0\nFN:Jane\nEND:VCARD');
  });
});
