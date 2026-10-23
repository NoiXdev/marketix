export interface User {
  id: string;
  name: string;
  email: string;
  super_admin: boolean;
}

export interface Project {
  id: string;
  name: string;
  locked: boolean;
}

export interface DomainCheckDetails {
  dns?: { domain_ips?: string[]; app_ips?: string[]; error?: string };
  ssl?: { expires_at?: number; names?: string[]; error?: string };
  reachable?: { status?: number; error?: string };
}

export interface Domain {
  id: string;
  name: string;
  redirect_root: string | null;
  redirect_not_found: string | null;
  status: 'healthy' | 'error' | 'pending';
  dns_ok: boolean | null;
  reachable_ok: boolean | null;
  ssl_ok: boolean | null;
  check_details: DomainCheckDetails | null;
  last_checked_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Pixel {
  id: string;
  provider: string;
  name: string;
  tag: string;
  created_at?: string;
}

export type PixelOption = Pick<Pixel, 'id' | 'name' | 'provider'>;

export interface Site {
  id: string;
  name: string;
  domain: string;
  additional_hostnames?: string | null;
  timezone?: string;
  tracking_id: string;
  tracking_mode: string;
  consent_mode: string;
  consent_signal?: string | null;
  respect_dnt?: boolean;
  track_outbound_links?: boolean;
  track_file_downloads?: boolean;
  track_contact_links?: boolean;
  site_search_params?: string | null;
  retention_days?: number | null;
  created_at?: string;
}

export interface GoalCondition {
  property: string;
  value: string;
}

export interface Goal {
  id: string;
  name: string;
  type: string;
  match_value: string;
  conditions: GoalCondition[];
  value: number | null;
  currency: string | null;
}

export type ProjectRole = 'admin' | 'member';

export interface ProjectMember {
  id: string;
  name: string;
  email: string;
  role: ProjectRole;
}

export interface ProjectInvitation {
  id: string;
  email: string;
  role: ProjectRole;
  expires_at: string;
  expired: boolean;
  can_resend: boolean;
}

export interface ActivityEntry {
  id: number;
  log_name: string;
  description: string;
  event: string | null;
  subject_type: string | null;
  causer: { id: string; name: string } | null;
  // Spatie v5 attribute diffs (old → new) from the attribute_changes column.
  changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> };
  // Manual custom data (role, email, ip, …) from the properties column.
  properties: Record<string, unknown>;
  created_at: string;
  project?: { id: string; name: string } | null;
}

export interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  };
  projects: Project[];
  project?: Project;
  currentProjectRole?: ProjectRole | null;
  version: string;
  updateAvailable: { version: string; url: string } | null;
  branding: {
    appName: string;
    logoLight: string | null;
    logoDark: string | null;
    favicon: string | null;
  };
  flash: {
    success?: string;
    error?: string;
    warning?: string;
    token?: string;
  };
  locale: string;
  availableLocales: { code: string; label: string }[];
  translations: Record<string, unknown>;
  navCounts?: Record<string, number>;
};

// ── Privacy notice generator ───────────────────────────────────────────

export type ControllerRole = 'own_company' | 'client';

export type LegalBasis = 'legitimate_interest' | 'consent';

/** Mirrors App\Support\Privacy\NoticeOptions::ACTIVITIES. */
export type NoticeActivity = 'link_clicks' | 'site_analytics' | 'pixels' | 'outbound_links' | 'file_downloads' | 'contact_links' | 'site_search' | 'custom_events';

export interface SnapshotSite {
  domain: string;
  tracking_mode: string;
  consent_mode: string;
  respects_dnt: boolean;
  tracks_outbound_links: boolean;
  tracks_file_downloads: boolean;
  tracks_contact_links: boolean;
  tracks_site_search: boolean;
  retention_days: number;
}

/** App\Support\Privacy\ProcessingSnapshot::toArray(). */
export interface ProcessingSnapshot {
  sites: SnapshotSite[];
  link_count: number;
  pixel_providers: string[];
  pixel_link_count: number;
  click_retention_days: number;
  geolocation_available: boolean;
  uses_cookies: boolean;
  has_consentless_cookies: boolean;
}

/** Party details the admin settings and the project supply. */
export interface NoticeParties {
  agency_name: string | null;
  agency_address: string | null;
  hosting_provider: string | null;
  hosting_location: string | null;
  client_name: string | null;
  client_address: string | null;
}

export interface NoticeDefaults {
  role: ControllerRole;
  locale: string;
  legalBasis: LegalBasis;
}

export interface NoticeSection {
  heading: string;
  paragraphs: string[];
}

export interface NoticeWarning {
  key: string;
  message: string;
}

/** The JSON body of app.project.docs.privacy.notice. */
export interface GeneratedNotice {
  sections: NoticeSection[];
  text: string;
  html: string;
  placeholders: string[];
  warnings: NoticeWarning[];
}
