import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { PageProps } from '@/types';
import type { WidgetConfig, WidgetType } from './schema';

export function useWidgetData<T>(type: WidgetType, config: WidgetConfig) {
  const project = usePage<PageProps>().props.project!;
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const key = useMemo(() => JSON.stringify(config), [config]);

  const reload = useCallback(() => {
    let cancelled = false;
    setLoading(true);
    setError(false);
    axios
      .get(route('app.project.widgets.data', { project: project.id, type, ...config }))
      .then((r) => !cancelled && setData(r.data))
      .catch(() => !cancelled && setError(true))
      .finally(() => !cancelled && setLoading(false));
    return () => { cancelled = true; };
  }, [project.id, type, key]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => reload(), [reload]);

  return { data, loading, error, reload };
}
