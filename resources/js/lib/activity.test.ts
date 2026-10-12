import { ActivityEntry } from '@/types';
import { describe, expect, it } from 'vitest';
import { describeActivity, logNameLabel } from './activity';
import { translate } from './i18n';

const catalog = {
  activity: {
    feed: {
      labels: { created: 'created', login: 'signed in', stats_reset: 'reset link statistics' },
      events: { created: 'created :subject', moved_out: 'moved :subject to “:project”' },
    },
    subjects: { Url: 'a link' },
    log_names: { url: 'Links' },
  },
};

const t = (key: string, replacements?: Record<string, string | number>) => translate(catalog, key, replacements);

function entry(overrides: Partial<ActivityEntry>): ActivityEntry {
  return {
    id: 1,
    log_name: 'url',
    description: 'created',
    event: 'created',
    subject_type: 'Url',
    causer: null,
    changes: {},
    properties: {},
    created_at: '2026-10-12T00:00:00Z',
    ...overrides,
  };
}

describe('describeActivity', () => {
  it('names the subject of model events', () => {
    expect(describeActivity(entry({}), t)).toBe('created a link');
  });

  it('falls back to the class name for unknown subjects', () => {
    expect(describeActivity(entry({ subject_type: 'Widget' }), t)).toBe('created Widget');
  });

  it('includes the other project for moves', () => {
    expect(describeActivity(entry({ description: 'moved_out', event: null, properties: { project: 'Beta' } }), t)).toBe('moved a link to “Beta”');
  });

  it('translates codes without a subject', () => {
    expect(describeActivity(entry({ description: 'login', event: null, subject_type: null }), t)).toBe('signed in');
  });

  it('uses the event when the description is free text', () => {
    expect(describeActivity(entry({ description: 'Stats reset', event: 'stats_reset' }), t)).toBe('reset link statistics');
  });

  it('keeps unknown descriptions as they are', () => {
    expect(describeActivity(entry({ description: 'Something else', event: null }), t)).toBe('Something else');
  });
});

describe('logNameLabel', () => {
  it('translates known log names and keeps unknown ones', () => {
    expect(logNameLabel('url', t)).toBe('Links');
    expect(logNameLabel('custom', t)).toBe('custom');
  });
});
