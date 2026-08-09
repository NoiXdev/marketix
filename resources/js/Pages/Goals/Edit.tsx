import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, ErrorSummary, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Goal, PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function GoalsEdit({ site, goal, goalTypes }: { site: { id: string }; goal: Goal; goalTypes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, put, processing, errors } = useForm({ name: goal.name, type: goal.type, match_value: goal.match_value });

  function submit(e: FormEvent) {
    e.preventDefault();
    put(route('app.project.analytics.goals.update', { project: project!.id, site: site.id, goal: goal.id }));
  }

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <AppLayout title={t('analytics.goals.edit')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.analytics.goals.index', { project: project!.id, site: site.id })}>
            {t('analytics.goals.back')}
          </BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('analytics.goals.edit')}</h1>
        </div>

        <div className="max-w-2xl">
          <form onSubmit={submit} className="space-y-5">
            <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

            <FormSection>
              <Field label={t('analytics.goals.form.name')} htmlFor="name" error={errors.name}>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
              </Field>

              <Field label={t('analytics.goals.form.type')} htmlFor="type">
                <Select id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                  {goalTypes.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </Field>

              <Field
                label={t('analytics.goals.form.match_value')}
                htmlFor="match_value"
                hint={data.type === 'event' ? t('analytics.goals.form.match_value_hint_event') : t('analytics.goals.form.match_value_hint_page')}
                error={errors.match_value}
              >
                <Input id="match_value" value={data.match_value} onChange={(e) => setData('match_value', e.target.value)} />
              </Field>
            </FormSection>

            <Button type="submit" loading={processing}>
              {t('common.actions.save')}
            </Button>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
