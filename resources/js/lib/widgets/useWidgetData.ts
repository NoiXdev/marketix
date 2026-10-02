import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { PageProps } from '@/types';
import type { WidgetConfig, WidgetType } from './schema';

export function useWidgetData<T>(type: WidgetType, config: WidgetConfig) {
  const project = usePage<PageProps>().props.project!;
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const key = useMemo(() => JSON.stringify(config), [config]);

  // Tracks the most recent request so stale responses (from a superseded
  // auto-refetch or a double-clicked retry) never overwrite newer state, and
  // a `mounted` flag so no response ever setState()s after unmount. Refs
  // persist across every reload() call — unlike a per-call closure variable,
  // they correctly guard both the effect-driven refetch and manual retries.
  const requestId = useRef(0);
  const mounted = useRef(true);

  useEffect(() => {
    mounted.current = true;
    return () => { mounted.current = false; };
  }, []);

  const reload = useCallback(() => {
    const id = ++requestId.current;
    setLoading(true);
    setError(false);
    axios
      .get(route('app.project.widgets.data', { project: project.id, type, ...config }))
      .then((r) => { if (mounted.current && requestId.current === id) setData(r.data); })
      .catch(() => { if (mounted.current && requestId.current === id) setError(true); })
      .finally(() => { if (mounted.current && requestId.current === id) setLoading(false); });
  }, [project.id, type, key]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => reload(), [reload]);

  return { data, loading, error, reload };
}
