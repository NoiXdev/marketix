import { Button, Field, FormSection, IconButton, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { GoalCondition } from '@/types';
import { Plus, Trash2 } from 'lucide-react';

export type GoalFormData = {
  name: string;
  type: string;
  match_value: string;
  conditions: GoalCondition[];
  value: string;
  currency: string;
};

type Option = { value: string; label: string };

const MAX_CONDITIONS = 5;

export default function GoalFields({
  data,
  setData,
  errors,
  goalTypes,
}: {
  data: GoalFormData;
  setData: <K extends keyof GoalFormData>(key: K, value: GoalFormData[K]) => void;
  errors: Record<string, string | undefined>;
  goalTypes: Option[];
}) {
  const { t } = useTranslation();
  const isEvent = data.type === 'event';

  const updateCondition = (index: number, patch: Partial<GoalCondition>) =>
    setData(
      'conditions',
      data.conditions.map((c, i) => (i === index ? { ...c, ...patch } : c)),
    );

  return (
    <>
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
          hint={isEvent ? t('analytics.goals.form.match_value_hint_event') : t('analytics.goals.form.match_value_hint_page')}
          error={errors.match_value}
        >
          <Input id="match_value" value={data.match_value} onChange={(e) => setData('match_value', e.target.value)} />
        </Field>
      </FormSection>

      {/* Property conditions only exist for events; page views carry no properties */}
      {isEvent && (
        <FormSection title={t('analytics.goals.form.conditions')} description={t('analytics.goals.form.conditions_hint')}>
          {data.conditions.map((condition, i) => (
            <div key={i} className="flex items-start gap-2">
              <div className="grid flex-1 gap-2 sm:grid-cols-2">
                <Input
                  aria-label={t('analytics.goals.form.property')}
                  value={condition.property}
                  onChange={(e) => updateCondition(i, { property: e.target.value })}
                  placeholder={t('analytics.goals.form.property_placeholder')}
                  className="font-mono"
                />
                <Input
                  aria-label={t('analytics.goals.form.condition_value')}
                  value={condition.value}
                  onChange={(e) => updateCondition(i, { value: e.target.value })}
                  placeholder={t('analytics.goals.form.condition_value_placeholder')}
                />
              </div>
              <IconButton
                icon={Trash2}
                variant="danger"
                label={t('analytics.goals.form.remove_condition')}
                onClick={() =>
                  setData(
                    'conditions',
                    data.conditions.filter((_, idx) => idx !== i),
                  )
                }
              />
            </div>
          ))}
          {data.conditions.length < MAX_CONDITIONS && (
            <Button type="button" variant="secondary" size="sm" onClick={() => setData('conditions', [...data.conditions, { property: '', value: '' }])}>
              <Plus className="h-4 w-4" />
              {t('analytics.goals.form.add_condition')}
            </Button>
          )}
        </FormSection>
      )}

      <FormSection title={t('analytics.goals.form.value')} description={t('analytics.goals.form.value_hint')}>
        <div className="flex gap-3">
          <div className="flex-1">
            <Field label={t('analytics.goals.form.value_amount')} htmlFor="value" error={errors.value}>
              <Input id="value" type="number" min="0" step="0.01" inputMode="decimal" value={data.value} onChange={(e) => setData('value', e.target.value)} />
            </Field>
          </div>
          <div className="w-28">
            <Field label={t('analytics.goals.form.currency')} htmlFor="currency" error={errors.currency}>
              <Input id="currency" value={data.currency} maxLength={3} onChange={(e) => setData('currency', e.target.value.toUpperCase())} placeholder="CHF" />
            </Field>
          </div>
        </div>
      </FormSection>
    </>
  );
}
