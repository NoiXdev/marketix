import { CountryFlag } from '@/Components/icons/CountryFlag';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import { countryName, languageName } from '@/lib/displayNames';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import { Rank } from '@/Pages/Analytics/types';

export type AudienceData = {
  clicksByCountry: CountryDatum[];
  countries: Rank[];
  regions: Rank[];
  cities: Rank[];
  languages: Rank[];
  devices: Rank[];
  browsers: Rank[];
  operatingSystems: Rank[];
};

export default function AudienceTab({ clicksByCountry, countries, regions, cities, languages, devices, browsers, operatingSystems }: AudienceData) {
  const { t, locale } = useTranslation();
  const { addFilter } = useAnalytics();
  const { rows } = useRowBuilders();
  const noData = t('analytics.dashboard.no_data');
  const withFlag = (r: Rank) => ({ prefix: <CountryFlag code={String(r.country_code ?? '')} /> });

  return (
    <>
      <div className="mb-6">
        <WorldMap data={clicksByCountry} title={t('analytics.dashboard.map_title')} />
      </div>

      <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.locations')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'countries',
              label: t('analytics.dashboard.breakdown.countries'),
              rows: rows(countries, 'country', null, (r) => {
                const code = String(r.country_code ?? '');
                return {
                  label: countryName(code, locale, String(r.country || '—')),
                  prefix: <CountryFlag code={code} />,
                  onClick: code ? () => addFilter('country_code', code) : undefined,
                  title: code ? t('analytics.dashboard.filters.apply') : undefined,
                };
              }),
            },
            {
              key: 'regions',
              label: t('analytics.dashboard.breakdown.regions'),
              rows: rows(regions, 'region', 'region', withFlag),
            },
            {
              key: 'cities',
              label: t('analytics.dashboard.breakdown.cities'),
              rows: rows(cities, 'city', 'city', withFlag),
            },
            {
              key: 'languages',
              label: t('analytics.dashboard.breakdown.languages'),
              rows: rows(languages, 'language', 'language', (r) => ({ label: languageName(String(r.language), locale), sub: String(r.language) })),
            },
          ]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.technology')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'devices',
              label: t('analytics.dashboard.breakdown.devices'),
              rows: rows(devices, 'device', 'device', (r) => ({ prefix: <PlatformIcon kind="device" name={String(r.device ?? '')} /> })),
            },
            {
              key: 'browsers',
              label: t('analytics.dashboard.breakdown.browsers'),
              rows: rows(browsers, 'browser', 'browser', (r) => ({ prefix: <PlatformIcon kind="browser" name={String(r.browser ?? '')} /> })),
            },
            {
              key: 'os',
              label: t('analytics.dashboard.breakdown.os'),
              rows: rows(operatingSystems, 'os', 'os', (r) => ({ prefix: <PlatformIcon kind="os" name={String(r.os ?? '')} /> })),
            },
          ]}
        />
      </div>
    </>
  );
}
