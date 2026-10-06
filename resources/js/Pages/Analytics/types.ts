export type FilterKey =
  'path' | 'entry_path' | 'exit_path' | 'referer_domain' | 'channel' | 'country_code' | 'browser' | 'os' | 'device' | 'language' | 'utm_source' | 'utm_medium' | 'utm_campaign';

export type Filters = Partial<Record<FilterKey, string>>;

export type TabKey = 'overview' | 'acquisition' | 'behavior' | 'audience' | 'conversions';

export const TABS: TabKey[] = ['overview', 'acquisition', 'behavior', 'audience', 'conversions'];

export type SiteInfo = { id: string; name: string; domain: string; search_enabled: boolean };

export type Summary = {
  page_views: number;
  visitors: number;
  sessions: number;
  bounce_rate: number;
  avg_duration: number;
  campaign_share: number;
};

export type Rank = Record<string, string | number> & { count: number };

export type CampaignRow = { value: string; sessions: number; visitors: number };

export type EventRow = { name: string; count: number; visitors: number };

export type ValueRow = { value: string; count: number; visitors: number };

export type Interactions = { outbound: ValueRow[]; downloads: ValueRow[]; searches: ValueRow[]; notFound: ValueRow[] };

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
