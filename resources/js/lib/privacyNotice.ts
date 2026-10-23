import type { ControllerRole, LegalBasis, NoticeActivity, NoticeDefaults, NoticeParties, ProcessingSnapshot, SnapshotSite } from '@/types';

/** Mirrors App\Support\Privacy\NoticeOptions::ACTIVITIES, in the same order. */
export const NOTICE_ACTIVITIES: NoticeActivity[] = ['link_clicks', 'site_analytics', 'pixels', 'outbound_links', 'file_downloads', 'contact_links', 'site_search', 'custom_events'];

/**
 * The generator form's answers. Nothing here is persisted — the notice is
 * recomposed from this state on every change.
 */
export interface GeneratorState {
  role: ControllerRole;
  legalBasis: LegalBasis;
  locale: string;
  controllerName: string;
  controllerAddress: string;
  agencyName: string;
  agencyAddress: string;
  hostingProvider: string;
  hostingLocation: string;
  /** Every activity, switched on or off. The off ones are sent as disabled. */
  activities: Record<NoticeActivity, boolean>;
}

export type NoticeQuery = Record<string, string | string[]>;

type PartyField = 'controllerName' | 'controllerAddress' | 'agencyName' | 'agencyAddress' | 'hostingProvider' | 'hostingLocation';

/**
 * The party fields, paired with the query parameter PrivacyNoticeRequest
 * validates. Blank ones are left out so the composer emits a placeholder
 * and NoticeWarnings flags the gap, rather than printing an empty string.
 */
const PARTY_FIELDS: [PartyField, string][] = [
  ['controllerName', 'controller_name'],
  ['controllerAddress', 'controller_address'],
  ['agencyName', 'agency_name'],
  ['agencyAddress', 'agency_address'],
  ['hostingProvider', 'hosting_provider'],
  ['hostingLocation', 'hosting_location'],
];

/**
 * Turns the form state into the query parameters of
 * app.project.docs.privacy.notice. The endpoint rejects anything else, so
 * the parameter names here are fixed.
 */
export function buildNoticeQuery(state: GeneratorState): NoticeQuery {
  const query: NoticeQuery = {
    role: state.role,
    legal_basis: state.legalBasis,
    locale: state.locale,
  };

  for (const [field, parameter] of PARTY_FIELDS) {
    const value = state[field].trim();

    if (value !== '') {
      query[parameter] = value;
    }
  }

  const disabled = NOTICE_ACTIVITIES.filter((activity) => !state.activities[activity]);

  if (disabled.length > 0) {
    query['disabled_activities[]'] = disabled;
  }

  return query;
}

/** Serialises a notice query, repeating array keys the way PHP expects. */
export function noticeQueryString(query: NoticeQuery): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(query)) {
    if (Array.isArray(value)) {
      value.forEach((item) => params.append(key, item));
    } else {
      params.append(key, value);
    }
  }

  return params.toString();
}

/** Who the controller is depends on the role, so the fields are re-derived with it. */
export function controllerFor(role: ControllerRole, parties: NoticeParties): { name: string; address: string } {
  return role === 'client'
    ? { name: parties.client_name ?? '', address: parties.client_address ?? '' }
    : { name: parties.agency_name ?? '', address: parties.agency_address ?? '' };
}

/** Which activities this project actually performs, used to pre-fill the toggles. */
export function activitiesFrom(snapshot: ProcessingSnapshot): Record<NoticeActivity, boolean> {
  const sites = snapshot.sites;
  const anySite = (predicate: (site: SnapshotSite) => boolean) => sites.some(predicate);

  return {
    link_clicks: snapshot.link_count > 0,
    site_analytics: sites.length > 0,
    pixels: snapshot.pixel_providers.length > 0,
    outbound_links: anySite((site) => site.tracks_outbound_links),
    file_downloads: anySite((site) => site.tracks_file_downloads),
    contact_links: anySite((site) => site.tracks_contact_links),
    site_search: anySite((site) => site.tracks_site_search),
    custom_events: sites.length > 0,
  };
}

/** The state the form opens with: the server's defaults plus what the project does. */
export function initialGeneratorState(defaults: NoticeDefaults, parties: NoticeParties, snapshot: ProcessingSnapshot): GeneratorState {
  const controller = controllerFor(defaults.role, parties);

  return {
    role: defaults.role,
    legalBasis: defaults.legalBasis,
    locale: defaults.locale,
    controllerName: controller.name,
    controllerAddress: controller.address,
    agencyName: parties.agency_name ?? '',
    agencyAddress: parties.agency_address ?? '',
    hostingProvider: parties.hosting_provider ?? '',
    hostingLocation: parties.hosting_location ?? '',
    activities: activitiesFrom(snapshot),
  };
}
