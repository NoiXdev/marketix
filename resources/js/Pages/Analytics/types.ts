export type FilterKey =
  'hostname' | 'path' | 'entry_path' | 'exit_path' | 'referer_domain' | 'channel' | 'country_code' | 'region' | 'city' | 'browser' | 'os' | 'device' | 'language' | 'utm_source' | 'utm_medium' | 'utm_campaign' | 'visitor_type';

export type Filters = Partial<Record<FilterKey, string>>;

export type TabKey = 'overview' | 'realtime' | 'acquisition' | 'behavior' | 'audience' | 'conversions' | 'revenue';

export const TABS: TabKey[] = ['overview', 'realtime', 'acquisition', 'behavior', 'audience', 'conversions', 'revenue'];

export type SiteInfo = { id: string; name: string; domain: string; search_enabled: boolean; tracking_mode: 'cookie' | 'cookieless'; has_data: boolean; snippet: string };

export type Summary = {
  page_views: number;
  visitors: number;
  sessions: number;
  bounce_rate: number;
  engagement_rate: number;
  avg_duration: number;
  campaign_share: number;
};

export type Rank = Record<string, string | number> & { count: number };

export type CampaignRow = { value: string; sessions: number; visitors: number };

export type ChannelRow = { channel: string; count: number; visitors: number; engagement_rate: number | null; avg_duration: number };

export type EventRow = { name: string; count: number; visitors: number };

export type ValueRow = { value: string; count: number; visitors: number };

export type VisitorTypeRow = { type: 'new' | 'returning'; visitors: number; sessions: number; engagement_rate: number | null };

export type Interactions = { outbound: ValueRow[]; downloads: ValueRow[]; searches: ValueRow[]; notFound: ValueRow[] };

export type FunnelStepReport = {
  label: string | null;
  type: 'pageview' | 'event';
  value: string;
  sessions: number;
  rate: number;
  step_rate: number | null;
  drop_off: number;
};

export type FunnelReport = {
  id: string;
  name: string;
  entered: number;
  completed: number;
  conversion_rate: number;
  steps: FunnelStepReport[];
};

export type GoalCard = {
  id: string;
  name: string;
  type: string;
  match_value: string;
  conversions: number;
  visitors: number;
  rate: number;
  byCampaign: { value: string; conversions: number }[];
};
