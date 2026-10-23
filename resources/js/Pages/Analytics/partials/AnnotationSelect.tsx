import { Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate } from '@/Pages/Analytics/partials/period';
import { Annotation } from '@/Pages/Analytics/types';

export default function AnnotationSelect({
  label,
  annotations,
  selected,
  onSelect,
}: {
  label: string;
  annotations: Annotation[];
  selected?: Annotation;
  onSelect: (annotation: Annotation) => void;
}) {
  const { locale } = useTranslation();
  const sorted = [...annotations].sort((a, b) => b.date.localeCompare(a.date));

  return (
    <Select
      aria-label={label}
      value={selected?.id ?? ''}
      disabled={sorted.length === 0}
      onChange={(event) => {
        const annotation = sorted.find((a) => a.id === event.target.value);
        if (annotation) onSelect(annotation);
      }}
      className="truncate py-1.5 pr-7 pl-2 text-xs"
    >
      <option value="">{label}</option>
      {sorted.map((annotation) => (
        <option key={annotation.id} value={annotation.id}>
          {formatDate(annotation.date, locale)} · {annotation.title}
        </option>
      ))}
    </Select>
  );
}
