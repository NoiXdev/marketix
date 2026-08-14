import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, Checkbox, ErrorSummary, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function CrawlsCreate({ modes }: { modes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, post, processing, errors, transform } = useForm({
    start_url: '',
    mode: 'full_site',
    render_js: false,
    respect_robots: true,
    include_subdomains: false,
    crawl_sitemap: false,
    capture_screenshots: false,
    delay_ms: 0,
    max_pages: '' as number | '',
  });

  transform((data) => ({
    ...data,
    max_pages: data.max_pages === '' ? null : data.max_pages,
  }));

  function submit(e: FormEvent) {
    e.preventDefault();
    post(route('app.project.crawls.store', { project: project!.id }));
  }

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <AppLayout title={t('crawler.create')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.crawls.index', { project: project!.id })}>{t('crawler.title')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('crawler.create')}</h1>
        </div>

        <div className="max-w-2xl">
          <form onSubmit={submit} className="space-y-5">
            <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

            <FormSection>
              <Field label={t('crawler.start_url')} htmlFor="start_url" error={errors.start_url}>
                <Input
                  id="start_url"
                  type="url"
                  value={data.start_url}
                  onChange={(e) => setData('start_url', e.target.value)}
                  placeholder="https://example.com"
                  required
                />
              </Field>

              <Field label={t('crawler.mode')} htmlFor="mode" error={errors.mode}>
                <Select id="mode" value={data.mode} onChange={(e) => setData('mode', e.target.value)}>
                  {modes.map((m) => (
                    <option key={m.value} value={m.value}>
                      {m.label}
                    </option>
                  ))}
                </Select>
              </Field>

              <div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox checked={data.render_js} onChange={(e) => setData('render_js', e.target.checked)} />
                  {t('crawler.render_js')}
                </label>
                <p className="ml-6 mt-1 text-xs text-muted">{t('crawler.render_js_hint')}</p>
              </div>

              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox checked={data.respect_robots} onChange={(e) => setData('respect_robots', e.target.checked)} />
                {t('crawler.respect_robots')}
              </label>

              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox checked={data.include_subdomains} onChange={(e) => setData('include_subdomains', e.target.checked)} />
                {t('crawler.include_subdomains')}
              </label>

              <div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox checked={data.crawl_sitemap} onChange={(e) => setData('crawl_sitemap', e.target.checked)} />
                  {t('crawler.crawl_sitemap')}
                </label>
                <p className="ml-6 mt-1 text-xs text-muted">{t('crawler.crawl_sitemap_hint')}</p>
              </div>

              <div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox checked={data.capture_screenshots} onChange={(e) => setData('capture_screenshots', e.target.checked)} />
                  {t('crawler.capture_screenshots')}
                </label>
                <p className="ml-6 mt-1 text-xs text-muted">{t('crawler.capture_screenshots_hint')}</p>
              </div>

              <Field label={t('crawler.delay_ms')} htmlFor="delay_ms" error={errors.delay_ms}>
                <Input
                  id="delay_ms"
                  type="number"
                  min={0}
                  max={10000}
                  value={data.delay_ms}
                  onChange={(e) => setData('delay_ms', Number(e.target.value))}
                />
              </Field>

              <Field label={t('crawler.max_pages')} htmlFor="max_pages" hint={t('crawler.max_pages_hint')} error={errors.max_pages}>
                <Input
                  id="max_pages"
                  type="number"
                  min={1}
                  value={data.max_pages}
                  onChange={(e) => setData('max_pages', e.target.value === '' ? '' : Number(e.target.value))}
                />
              </Field>
            </FormSection>

            <Button type="submit" loading={processing}>
              {t('crawler.start')}
            </Button>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
