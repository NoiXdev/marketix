import { describe, expect, it } from 'vitest';
import { canonicalLanguage, countryName, languageName } from './displayNames';

describe('countryName', () => {
  it('localizes ISO country codes into the UI language', () => {
    expect(countryName('CH', 'de')).toBe('Schweiz');
    expect(countryName('de', 'fr')).toBe('Allemagne');
    expect(countryName('US', 'en')).toBe('United States');
  });

  it('falls back to the stored name when no code is known', () => {
    expect(countryName('', 'de', 'Switzerland')).toBe('Switzerland');
    expect(countryName('not-a-code', 'de', 'Fallback')).toBe('Fallback');
  });
});

describe('languageName', () => {
  it('localizes language codes', () => {
    expect(languageName('fr', 'de')).toBe('Französisch');
    expect(languageName('de', 'en')).toBe('German');
    expect(languageName('gsw', 'de')).toBe('Schweizerdeutsch');
  });

  it('reads codes that older versions cut to two letters', () => {
    expect(canonicalLanguage('gs')).toBe('gsw');
    expect(languageName('gs', 'de')).toBe('Schweizerdeutsch');
  });
});
