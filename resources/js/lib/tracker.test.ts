import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { describe, expect, it } from 'vitest';

const source = readFileSync(new URL('../../analytics/mx.js', import.meta.url), 'utf8');

type Hit = Record<string, unknown>;

// Runs the snippet against a minimal browser: cookies, window events and the hits it sends
async function loadTracker(trackingMode: string, consent: boolean) {
  const hits: Hit[] = [];
  const cookies = new Map<string, string>();
  const listeners = new Map<string, Array<() => void>>();

  const document = {
    currentScript: { src: 'https://marketix.test/mx.js', getAttribute: (name: string) => (name === 'data-site' ? 'site-1' : null) },
    get cookie() {
      return [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
    },
    set cookie(value: string) {
      const [pair, ...attributes] = value.split(';');
      const [name, cookieValue] = pair.split('=');
      const expires = attributes.find((attribute) => attribute.trim().toLowerCase().startsWith('expires='));
      if (expires && new Date(expires.trim().slice('expires='.length)).getTime() < Date.now()) {
        cookies.delete(name);
      } else {
        cookies.set(name, cookieValue);
      }
    },
    title: 'Home',
    referrer: '',
    visibilityState: 'visible',
    documentElement: { scrollHeight: 1000, clientHeight: 1000, scrollTop: 0 },
    body: { scrollHeight: 1000 },
    addEventListener: () => {},
  };

  const config = { tracking_mode: trackingMode, consent_mode: 'immediate', consent_signal: 'UC_UI', outbound_links: false, file_downloads: false };

  const window: Record<string, unknown> = {
    document,
    location: { protocol: 'https:', hostname: 'example.com', host: 'example.com', origin: 'https://example.com', pathname: '/', search: '', href: 'https://example.com/' },
    history: { pushState: () => {}, replaceState: () => {} },
    crypto: { randomUUID: () => 'id-1' },
    innerHeight: 1000,
    pageYOffset: 0,
    URL,
    URLSearchParams,
    setTimeout,
    UC_UI: consent,
    addEventListener: (type: string, listener: () => void) => listeners.set(type, [...(listeners.get(type) ?? []), listener]),
    fetch: (url: string, init?: { body?: string }) => {
      if (url.includes('/a/config/')) {
        return Promise.resolve({ ok: true, json: () => Promise.resolve(config) });
      }
      hits.push(JSON.parse(init?.body ?? '{}'));
      return Promise.resolve({ ok: true });
    },
  };
  window.window = window;

  runInNewContext(source, window);
  await new Promise((resolve) => setTimeout(resolve, 0));

  return {
    hits,
    cookie: () => cookies.get('mx_vid'),
    setConsent(granted: boolean) {
      window.UC_UI = granted;
      (listeners.get('marketix:consent') ?? []).forEach((listener) => listener());
    },
    event: (name: string) => (window.marketix as (command: string, name: string) => void)('event', name),
  };
}

describe('hybrid tracking mode', () => {
  it('counts visitors anonymously until they consent', async () => {
    const tracker = await loadTracker('hybrid', false);

    expect(tracker.hits).toHaveLength(1);
    expect(tracker.hits[0]).not.toHaveProperty('visitor_id');
    expect(tracker.cookie()).toBeUndefined();
  });

  it('uses the visitor id right away when consent is already given', async () => {
    const tracker = await loadTracker('hybrid', true);

    expect(tracker.hits).toEqual([expect.objectContaining({ path: '/', visitor_id: 'id-1' })]);
    expect(tracker.cookie()).toBe('id-1');
  });

  it('hands the anonymous session over once consent is given on the page', async () => {
    const tracker = await loadTracker('hybrid', false);

    tracker.setConsent(true);
    tracker.event('signup');

    expect(tracker.hits.slice(1)).toEqual([
      { site: 'site-1', type: 'consent', path: '/', visitor_id: 'id-1' },
      expect.objectContaining({ type: 'event', name: 'signup', visitor_id: 'id-1' }),
    ]);
    expect(tracker.cookie()).toBe('id-1');
  });

  it('removes the cookie and counts anonymously again when consent is withdrawn', async () => {
    const tracker = await loadTracker('hybrid', true);
    expect(tracker.cookie()).toBe('id-1');

    tracker.setConsent(false);
    tracker.event('signup');

    expect(tracker.cookie()).toBeUndefined();
    expect(tracker.hits[1]).toEqual(expect.objectContaining({ type: 'event', name: 'signup' }));
    expect(tracker.hits[1]).not.toHaveProperty('visitor_id');
  });
});

describe('cookieless tracking mode', () => {
  it('never sets a cookie or sends a visitor id, even with consent', async () => {
    const tracker = await loadTracker('cookieless', true);

    tracker.event('signup');

    expect(tracker.hits).toHaveLength(2);
    tracker.hits.forEach((hit) => expect(hit).not.toHaveProperty('visitor_id'));
    expect(tracker.cookie()).toBeUndefined();
  });
});
