import { CountryFlag } from '@/Components/icons/CountryFlag';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import { countryName, languageName } from '@/lib/displayNames';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import BreakdownCard, { breakdownTab } from '@/Pages/Analytics/partials/BreakdownCard';
import CardColumns from '@/Pages/Analytics/partials/CardColumns';
import { Rank, VisitorTypeRow } from '@/Pages/Analytics/types';

export type AudienceData = {
  clicksByCountry: CountryDatum[];
  countries: Rank[];
  regions: Rank[];
  cities: Rank[];
  languages: Rank[];
  devices: Rank[];
  browsers: Rank[];
  operatingSystems: Rank[];
  visitorTypes: VisitorTypeRow[];
};

export default function AudienceTab({ clicksByCountry, countries, regions, cities, languages, devices, browsers, operatingSystems, visitorTypes }: AudienceData) {
  const { t, locale } = useTranslation();
  const { site, addFilter } = useAnalytics();
  const { rows } = useRowBuilders();
  const noData = t('analytics.dashboard.no_data');
  const visitors = t('analytics.dashboard.units.visitors');
  const withFlag = (r: Rank) => ({ prefix: <CountryFlag code={String(r.country_code ?? '')} /> });

  return (
    <>
      <div className="mb-6">
        <WorldMap data={clicksByCountry} title={t('analytics.dashboard.map_title')} />
      </div>

      <CardColumns
        left={
          <BreakdownCard
            title={t('analytics.dashboard.breakdown.locations')}
            emptyLabel={noData}
            tabs={[
              breakdownTab({
                key: 'countries',
                label: t('analytics.dashboard.breakdown.countries'),
                unit: visitors,
                dimension: 'countries',
                items: countries,
                toRows: (items) =>
                  rows(items, 'country', null, (r) => {
                    const code = String(r.country_code ?? '');
                    return {
                      label: countryName(code, locale, String(r.country || '—')),
                      prefix: <CountryFlag code={code} />,
                      onClick: code ? () => addFilter('country_code', code) : undefined,
                      title: code ? t('analytics.dashboard.filters.apply') : undefined,
                    };
                  }),
              }),
              breakdownTab({
                key: 'regions',
                label: t('analytics.dashboard.breakdown.regions'),
                unit: visitors,
                dimension: 'regions',
                items: regions,
                toRows: (items) => rows(items, 'region', 'region', (r) => ({ ...withFlag(r), label: String(r.region_name ?? r.region) })),
              }),
              breakdownTab({
                key: 'cities',
                label: t('analytics.dashboard.breakdown.cities'),
                unit: visitors,
                dimension: 'cities',
                items: cities,
                toRows: (items) => rows(items, 'city', 'city', (r) => ({ ...withFlag(r), label: String(r.city_name ?? r.city) })),
              }),
              breakdownTab({
                key: 'languages',
                label: t('analytics.dashboard.breakdown.languages'),
                unit: visitors,
                dimension: 'languages',
                items: languages,
                toRows: (items) => rows(items, 'language', 'language', (r) => ({ label: languageName(String(r.language), locale), sub: String(r.language) })),
              }),
            ]}
          />
        }
        right={
          <>
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.technology')}
              emptyLabel={noData}
              tabs={[
                {
                  key: 'devices',
                  label: t('analytics.dashboard.breakdown.devices'),
                  unit: visitors,
                  rows: rows(devices, 'device', 'device', (r) => ({ prefix: <PlatformIcon kind="device" name={String(r.device ?? '')} /> })),
                },
                breakdownTab({
                  key: 'browsers',
                  label: t('analytics.dashboard.breakdown.browsers'),
                  unit: visitors,
                  dimension: 'browsers',
                  items: browsers,
                  toRows: (items) => rows(items, 'browser', 'browser', (r) => ({ prefix: <PlatformIcon kind="browser" name={String(r.browser ?? '')} /> })),
                }),
                breakdownTab({
                  key: 'os',
                  label: t('analytics.dashboard.breakdown.os'),
                  unit: visitors,
                  dimension: 'os',
                  items: operatingSystems,
                  toRows: (items) => rows(items, 'os', 'os', (r) => ({ prefix: <PlatformIcon kind="os" name={String(r.os ?? '')} /> })),
                }),
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.visitor_types.title')}
              emptyLabel={noData}
              // Without a cookie the visitor hash changes daily, so only same-day returns are recognised
              hint={site.tracking_mode === 'cookie' ? undefined : t(`analytics.dashboard.visitor_types.${site.tracking_mode}_hint`)}
              tabs={[
                {
                  key: 'types',
                  label: '',
                  unit: visitors,
                  rows: visitorTypes
                    .filter((r) => r.visitors > 0)
                    .map((r) => ({
                      key: r.type,
                      label: t(`analytics.dashboard.visitor_types.${r.type}`),
                      value: r.visitors,
                      note: t('analytics.dashboard.visitor_types.note', {
                        sessions: r.sessions.toLocaleString(locale),
                        rate: (r.engagement_rate ?? 0).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
                      }),
                      onClick: () => addFilter('visitor_type', r.type),
                      title: t('analytics.dashboard.filters.apply'),
                    })),
                },
              ]}
            />
          </>
        }
      />
    </>
  );
}
