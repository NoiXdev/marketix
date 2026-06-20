import { describe, expect, it } from 'vitest';
import { translate } from './i18n';

const catalog = {
  auth: { login: { title: 'Welcome back' } },
  links: { count: ':count links' },
};

describe('translate', () => {
  it('resolves dot-notation keys', () => {
    expect(translate(catalog, 'auth.login.title')).toBe('Welcome back');
  });

  it('replaces :placeholders', () => {
    expect(translate(catalog, 'links.count', { count: 5 })).toBe('5 links');
  });

  it('returns the key when missing', () => {
    expect(translate(catalog, 'auth.nope')).toBe('auth.nope');
  });

  it('returns the key when a branch is not a string', () => {
    expect(translate(catalog, 'auth.login')).toBe('auth.login');
  });
});
