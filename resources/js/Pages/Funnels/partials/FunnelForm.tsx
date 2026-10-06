import { Button, ErrorSummary, Field, FormSection, IconButton, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

export type FunnelStep = { type: string; value: string; label: string | null };

export type FunnelFormData = { name: string; steps: FunnelStep[] };

type Option = { value: string; label: string };

export default function FunnelForm({
  data,
  errors,
  stepTypes,
  limits,
  processing,
  submitLabel,
  onChange,
  onSubmit,
}: {
  data: FunnelFormData;
  errors: Record<string, string | undefined>;
  stepTypes: Option[];
  limits: { min: number; max: number };
  processing: boolean;
  submitLabel: string;
  onChange: (data: FunnelFormData) => void;
  onSubmit: () => void;
}) {
  const { t } = useTranslation();
  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  function updateStep(index: number, patch: Partial<FunnelStep>) {
    onChange({ ...data, steps: data.steps.map((step, i) => (i === index ? { ...step, ...patch } : step)) });
  }

  function moveStep(index: number, offset: number) {
    const steps = [...data.steps];
    const [step] = steps.splice(index, 1);
    steps.splice(index + offset, 0, step);
    onChange({ ...data, steps });
  }

  function submit(e: FormEvent) {
    e.preventDefault();
    onSubmit();
  }

  return (
    <form onSubmit={submit} className="space-y-5">
      <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

      <FormSection>
        <Field label={t('analytics.funnels.form.name')} htmlFor="name" error={errors.name}>
          <Input id="name" value={data.name} placeholder={t('analytics.funnels.form.name_placeholder')} onChange={(e) => onChange({ ...data, name: e.target.value })} />
        </Field>
      </FormSection>

      <FormSection title={t('analytics.funnels.form.steps')} description={t('analytics.funnels.form.steps_hint')}>
        <ol className="space-y-3">
          {data.steps.map((step, index) => (
            <li key={index} className="border-line bg-canvas rounded-lg border p-3">
              <div className="mb-3 flex items-center justify-between gap-2">
                <span className="text-foreground inline-flex items-center gap-2 text-sm font-semibold">
                  <span className="bg-accent-soft text-accent-soft-foreground grid h-6 w-6 place-items-center rounded-full text-xs font-bold">{index + 1}</span>
                  {t('analytics.funnels.form.step', { number: index + 1 })}
                </span>
                <div className="flex items-center gap-1">
                  <IconButton icon={ArrowUp} label={t('analytics.funnels.form.move_up')} disabled={index === 0} onClick={() => moveStep(index, -1)} />
                  <IconButton icon={ArrowDown} label={t('analytics.funnels.form.move_down')} disabled={index === data.steps.length - 1} onClick={() => moveStep(index, 1)} />
                  <IconButton
                    icon={Trash2}
                    label={t('analytics.funnels.form.remove_step')}
                    variant="danger"
                    disabled={data.steps.length <= limits.min}
                    onClick={() => onChange({ ...data, steps: data.steps.filter((_, i) => i !== index) })}
                  />
                </div>
              </div>
              <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
                <Field label={t('analytics.funnels.form.step_type')} htmlFor={`step-${index}-type`}>
                  <Select id={`step-${index}-type`} value={step.type} onChange={(e) => updateStep(index, { type: e.target.value })}>
                    {stepTypes.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field
                  label={t('analytics.funnels.form.step_value')}
                  htmlFor={`step-${index}-value`}
                  hint={step.type === 'event' ? t('analytics.goals.form.match_value_hint_event') : t('analytics.goals.form.match_value_hint_page')}
                  error={errors[`steps.${index}.value`]}
                >
                  <Input
                    id={`step-${index}-value`}
                    value={step.value}
                    placeholder={step.type === 'event' ? 'purchase' : '/produkte/*'}
                    onChange={(e) => updateStep(index, { value: e.target.value })}
                  />
                </Field>
              </div>
              <div className="mt-3">
                <Field label={t('analytics.funnels.form.step_label')} htmlFor={`step-${index}-label`} error={errors[`steps.${index}.label`]}>
                  <Input
                    id={`step-${index}-label`}
                    value={step.label ?? ''}
                    placeholder={t('analytics.funnels.form.step_label_placeholder')}
                    onChange={(e) => updateStep(index, { label: e.target.value })}
                  />
                </Field>
              </div>
            </li>
          ))}
        </ol>

        <Button
          variant="secondary"
          disabled={data.steps.length >= limits.max}
          onClick={() => onChange({ ...data, steps: [...data.steps, { type: 'pageview', value: '', label: null }] })}
        >
          <Plus className="h-4 w-4" />
          {t('analytics.funnels.form.add_step')}
        </Button>
        {errors.steps && <p className="text-danger-foreground text-xs">{errors.steps}</p>}
      </FormSection>

      <Button type="submit" loading={processing}>
        {submitLabel}
      </Button>
    </form>
  );
}
