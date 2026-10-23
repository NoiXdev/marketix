import { describe, expect, it } from 'vitest';
import { buildNoticeQuery, GeneratorState, noticeQueryString } from './privacyNotice';

function state(overrides: Partial<GeneratorState> = {}): GeneratorState {
  return {
    role: 'client',
    legalBasis: 'consent',
    locale: 'fr',
    controllerName: 'Client GmbH',
    controllerAddress: 'Hauptstrasse 1, 10115 Berlin',
    agencyName: 'Agency BV',
    agencyAddress: 'Keizersgracht 2, 1015 Amsterdam',
    hostingProvider: 'Hetzner',
    hostingLocation: 'Falkenstein, Germany',
    activities: {
      link_clicks: true,
      site_analytics: true,
      pixels: true,
      outbound_links: true,
      file_downloads: true,
      contact_links: true,
      site_search: true,
      custom_events: true,
    },
    ...overrides,
  };
}

describe('buildNoticeQuery', () => {
  it('maps form state onto the endpoint parameters', () => {
    expect(buildNoticeQuery(state())).toEqual({
      role: 'client',
      legal_basis: 'consent',
      locale: 'fr',
      controller_name: 'Client GmbH',
      controller_address: 'Hauptstrasse 1, 10115 Berlin',
      agency_name: 'Agency BV',
      agency_address: 'Keizersgracht 2, 1015 Amsterdam',
      hosting_provider: 'Hetzner',
      hosting_location: 'Falkenstein, Germany',
    });
  });

  it('omits party fields that are empty', () => {
    const query = buildNoticeQuery(
      state({
        controllerName: '',
        controllerAddress: '   ',
        agencyName: '',
        agencyAddress: '',
        hostingProvider: '  Hetzner  ',
        hostingLocation: '',
      }),
    );

    expect(query).toEqual({
      role: 'client',
      legal_basis: 'consent',
      locale: 'fr',
      hosting_provider: 'Hetzner',
    });
  });

  it('sends every switched-off activity', () => {
    const query = buildNoticeQuery(
      state({
        activities: {
          link_clicks: true,
          site_analytics: false,
          pixels: false,
          outbound_links: true,
          file_downloads: false,
          contact_links: false,
          site_search: true,
          custom_events: false,
        },
      }),
    );

    // The endpoint validates an array, so the key carries the [] suffix PHP
    // needs; the order follows NoticeOptions::ACTIVITIES.
    expect(query['disabled_activities[]']).toEqual(['site_analytics', 'pixels', 'file_downloads', 'contact_links', 'custom_events']);
    expect(buildNoticeQuery(state())).not.toHaveProperty('disabled_activities[]');
  });
});

describe('noticeQueryString', () => {
  it('repeats the array key once per switched-off activity', () => {
    const query = noticeQueryString({
      role: 'own_company',
      locale: 'de',
      'disabled_activities[]': ['pixels', 'site_search'],
    });

    expect(query).toBe('role=own_company&locale=de&disabled_activities%5B%5D=pixels&disabled_activities%5B%5D=site_search');
  });
});
