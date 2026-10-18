import { Badge, Checkbox, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { GeneratorState } from '@/lib/privacyNotice';
import ChoiceCards from '@/Pages/Sites/partials/ChoiceCards';
import ToggleRow from '@/Pages/Sites/partials/ToggleRow';
import type { ControllerRole, LegalBasis, NoticeActivity, ProcessingSnapshot } from '@/types';
import { Lock, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

// Brand names, so they are not translated. Mirrors App\Enums\PixelProvider.
const PIXEL_PROVIDER_LABELS: Record<string, string> = {
  google_tag_manager: 'Google Tag Manager',
  google_analytics: 'Google Analytics',
  facebook: 'Facebook',
  google_ads: 'Google Ads',
  linkedin: 'LinkedIn',
  twitter: 'Twitter',
  adroll: 'AdRoll',
  quora: 'Quora',
  pinterest: 'Pinterest',
  bing: 'Bing',
  snapchat: 'Snapchat',
  reddit: 'Reddit',
  tiktok: 'TikTok',
};

interface NoticeFormProps {
  state: GeneratorState;
  snapshot: ProcessingSnapshot;
  canEditClient: boolean;
  locales: { code: string; label: string }[];
  onChange: (patch: Partial<GeneratorState>) => void;
  onRoleChange: (role: ControllerRole) => void;
}

export default function NoticeForm({ state, snapshot, canEditClient, locales, onChange, onRoleChange }: NoticeFormProps) {
  const { t } = useTranslation();

  const tkey = (suffix: string) => `docs.privacy.generator.${suffix}`;

  function setActivities(keys: NoticeActivity[], enabled: boolean) {
    const activities = { ...state.activities };
    keys.forEach((activity) => {
      activities[activity] = enabled;
    });
    onChange({ activities });
  }

  const sites = snapshot.sites;
  const hasPixels = snapshot.pixel_providers.length > 0;
  const tracksOutbound = sites.some((site) => site.tracks_outbound_links || site.tracks_file_downloads);
  const tracksSearch = sites.some((site) => site.tracks_site_search);

  // A row describes what the project does; when it does not do it, say so
  // instead of describing processing that is not taking place.
  const detail = (active: boolean, suffix: string, replacements?: Record<string, string | number>) => (active ? t(tkey(suffix), replacements) : t(tkey('activities.inactive')));

  const providers = snapshot.pixel_providers.map((provider) => PIXEL_PROVIDER_LABELS[provider] ?? provider).join(', ');

  return (
    <div className="min-w-0 space-y-4">
      <FormSection>
        <ChoiceCards
          legend={t(tkey('role.label'))}
          name="notice_role"
          value={state.role}
          onChange={(value) => onRoleChange(value as ControllerRole)}
          options={[
            { value: 'own_company', title: t(tkey('role.own_company.title')), text: t(tkey('role.own_company.text')) },
            { value: 'client', title: t(tkey('role.client.title')), text: t(tkey('role.client.text')) },
          ]}
        />
      </FormSection>

      <FormSection title={t(tkey('controller.heading'))} description={t(tkey('controller.description'))}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t(tkey('controller.name'))} htmlFor="controller_name">
            <Input
              id="controller_name"
              value={state.controllerName}
              readOnly={!canEditClient}
              placeholder={t(tkey('controller.name_placeholder'))}
              onChange={(event) => onChange({ controllerName: event.target.value })}
            />
          </Field>
          <Field label={t(tkey('controller.address'))} htmlFor="controller_address">
            <Input
              id="controller_address"
              value={state.controllerAddress}
              readOnly={!canEditClient}
              placeholder={t(tkey('controller.address_placeholder'))}
              onChange={(event) => onChange({ controllerAddress: event.target.value })}
            />
          </Field>
        </div>
        {!canEditClient && (
          <p className="text-subtle flex items-start gap-2 text-xs">
            <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>{t(tkey('controller.locked'))}</span>
          </p>
        )}
      </FormSection>

      <FormSection title={t(tkey('processors.heading'))} description={t(tkey('processors.description'))}>
        <dl className="divide-line -my-2 divide-y text-sm">
          {state.role === 'client' && (
            <PartyRow label={t(tkey('processors.agency'))} value={state.agencyName} secondary={state.agencyAddress} empty={t(tkey('processors.empty'))} />
          )}
          <PartyRow label={t(tkey('processors.hosting'))} value={state.hostingProvider} empty={t(tkey('processors.empty'))} />
          <PartyRow label={t(tkey('processors.location'))} value={state.hostingLocation} empty={t(tkey('processors.empty'))} />
        </dl>
      </FormSection>

      <FormSection title={t(tkey('activities.heading'))} description={t(tkey('activities.description'))}>
        <div className="space-y-1.5">
          <ToggleRow
            checked={state.activities.link_clicks}
            onChange={(checked) => setActivities(['link_clicks'], checked)}
            title={t(tkey('activities.link_clicks.title'))}
            text={detail(snapshot.link_count > 0, 'activities.link_clicks.text', { count: snapshot.link_count })}
          />

          <div>
            <ToggleRow
              checked={state.activities.site_analytics}
              onChange={(checked) => setActivities(['site_analytics'], checked)}
              title={t(tkey('activities.site_analytics.title'))}
              text={detail(sites.length > 0, 'activities.site_analytics.text')}
            />
            {sites.length > 0 && (
              <ul className="mt-1 ml-7 space-y-1.5">
                {sites.map((site) => (
                  <li key={site.domain} className="flex flex-wrap items-center gap-1.5">
                    <span className="text-foreground text-xs font-medium">{site.domain}</span>
                    <Badge variant="neutral">{t(`app.tracking_mode_${site.tracking_mode}`)}</Badge>
                    {/* A cookie set before any consent is what the preview warns about, so the badge says so here too. */}
                    <Badge variant={site.tracking_mode === 'cookie' && site.consent_mode === 'immediate' ? 'warning' : 'neutral'}>
                      {t(`app.consent_mode_${site.consent_mode}`)}
                    </Badge>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Pixels fire before the redirect with no consent gate anywhere in
              the product, so the row is flagged rather than listed plainly.
              Where they really are in use the row is locked on: a transfer
              that happens may not be switched out of the published text. */}
          <div
            className={`rounded-[var(--radius-sm)] border p-3 ${
              hasPixels ? 'bg-warning-soft/50 border-[color:color-mix(in_srgb,var(--warning-foreground)_35%,transparent)]' : 'border-line'
            }`}
          >
            <PixelRowShell locked={hasPixels}>
              <Checkbox
                className="mt-0.5 shrink-0"
                checked={hasPixels || state.activities.pixels}
                disabled={hasPixels}
                readOnly={hasPixels}
                onChange={hasPixels ? undefined : (event) => setActivities(['pixels'], event.target.checked)}
              />
              <span className="min-w-0">
                <span className="text-foreground flex flex-wrap items-center gap-2 text-sm font-medium">
                  {t(tkey('activities.pixels.title'))}
                  {hasPixels && (
                    <Badge variant="warning">
                      <TriangleAlert className="h-3 w-3" />
                      {t(tkey('activities.pixels.flag'))}
                    </Badge>
                  )}
                </span>
                <span className="text-muted mt-0.5 block text-xs leading-relaxed">{detail(hasPixels, 'activities.pixels.text')}</span>
                {hasPixels && (
                  <>
                    <span className="text-muted mt-1 block text-xs">{t(tkey('activities.pixels.providers'), { providers })}</span>
                    <span className="text-muted block text-xs">{t(tkey('activities.pixels.links'), { count: snapshot.pixel_link_count })}</span>
                    <span className="text-muted mt-1.5 flex items-start gap-1.5 text-xs">
                      <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                      <span>{t(tkey('activities.pixels.required'))}</span>
                    </span>
                  </>
                )}
              </span>
            </PixelRowShell>
          </div>

          <ToggleRow
            checked={state.activities.outbound_links || state.activities.file_downloads}
            onChange={(checked) => setActivities(['outbound_links', 'file_downloads'], checked)}
            title={t(tkey('activities.outbound.title'))}
            text={detail(tracksOutbound, 'activities.outbound.text')}
          />

          <ToggleRow
            checked={state.activities.site_search}
            onChange={(checked) => setActivities(['site_search'], checked)}
            title={t(tkey('activities.site_search.title'))}
            text={detail(tracksSearch, 'activities.site_search.text')}
          />

          <ToggleRow
            checked={state.activities.custom_events}
            onChange={(checked) => setActivities(['custom_events'], checked)}
            title={t(tkey('activities.custom_events.title'))}
            text={detail(sites.length > 0, 'activities.custom_events.text')}
          />
        </div>
      </FormSection>

      <FormSection>
        <ChoiceCards
          legend={t(tkey('legal_basis.label'))}
          name="notice_legal_basis"
          value={state.legalBasis}
          onChange={(value) => onChange({ legalBasis: value as LegalBasis })}
          options={[
            { value: 'legitimate_interest', title: t(tkey('legal_basis.legitimate_interest.title')), text: t(tkey('legal_basis.legitimate_interest.text')) },
            { value: 'consent', title: t(tkey('legal_basis.consent.title')), text: t(tkey('legal_basis.consent.text')) },
          ]}
        />
      </FormSection>

      <FormSection>
        <Field label={t(tkey('language.label'))} hint={t(tkey('language.hint'))} htmlFor="notice_locale">
          <Select id="notice_locale" value={state.locale} onChange={(event) => onChange({ locale: event.target.value })}>
            {locales.map((locale) => (
              <option key={locale.code} value={locale.code}>
                {locale.label}
              </option>
            ))}
          </Select>
        </Field>
      </FormSection>
    </div>
  );
}

/**
 * The pixel row's wrapper. A locked row carries no label, because there is
 * nothing to click: the disclosure is mandatory while pixels are attached.
 */
function PixelRowShell({ locked, children }: { locked: boolean; children: ReactNode }) {
  return locked ? <div className="flex items-start gap-3">{children}</div> : <label className="flex cursor-pointer items-start gap-3">{children}</label>;
}

/** One read-only party line in the processor summary. */
function PartyRow({ label, value, secondary, empty }: { label: string; value: string; secondary?: string; empty: string }) {
  const name = value.trim();
  const address = (secondary ?? '').trim();

  return (
    <div className="flex items-baseline justify-between gap-4 py-2">
      <dt className="text-muted shrink-0 text-xs font-semibold">{label}</dt>
      <dd className="min-w-0 text-right">
        {name === '' && address === '' ? (
          <span className="text-subtle text-xs italic">{empty}</span>
        ) : (
          <>
            {name !== '' && <span className="text-foreground block text-sm break-words">{name}</span>}
            {address !== '' && <span className="text-muted block text-xs break-words">{address}</span>}
          </>
        )}
      </dd>
    </div>
  );
}
