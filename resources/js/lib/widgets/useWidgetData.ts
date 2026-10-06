import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { WidgetConfig, WidgetType } from './schema';

type Result<T> = { request: string; data: T | null; error: boolean };

export function useWidgetData<T>(type: WidgetType, config: WidgetConfig) {
  const project = usePage<PageProps>().props.project!;
  const key = useMemo(() => JSON.stringify(config), [config]);
  // Bumped by reload() so a retry fetches again even when nothing else changed.
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<Result<T> | null>(null);

  // Identifies the request the widget currently wants. The widget is loading
  // until a result for exactly this request arrives, so a config change or a
  // retry shows the spinner without resetting state inside the effect.
  const request = `${project.id}|${type}|${key}|${attempt}`;

  useEffect(() => {
    // The cleanup drops responses from superseded requests (config changes,
    // double-clicked retries) and from requests that finish after unmount.
    let current = true;
    axios
      .get(route('app.project.widgets.data', { project: project.id, type, ...JSON.parse(key) }))
      .then((r) => current && setResult({ request, data: r.data, error: false }))
      .catch(() => current && setResult({ request, data: null, error: true }));

    return () => {
      current = false;
    };
  }, [request, project.id, type, key]);

  const reload = useCallback(() => setAttempt((a) => a + 1), []);
  const settled = result?.request === request ? result : null;

  return { data: settled?.data ?? null, loading: settled === null, error: settled?.error ?? false, reload };
}
