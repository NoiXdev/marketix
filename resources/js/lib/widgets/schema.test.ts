import { describe, expect, it } from 'vitest';
import { newWidget, WIDGET_DEFS } from './schema';

describe('newWidget', () => {
  it('creates a kpi widget with defaults', () => {
    const w = newWidget('kpi');
    expect(w.type).toBe('kpi');
    expect(w.config.metric).toBe('clicks');
    expect(w.config.days).toBe(30);
    expect(w.layout.w).toBe(WIDGET_DEFS.kpi.defaultLayout.w);
    expect(typeof w.id).toBe('string');
  });

  it('defines every widget type', () => {
    expect(Object.keys(WIDGET_DEFS).sort()).toEqual(['activity', 'geo_map', 'kpi', 'quick_actions', 'timeseries', 'top_list']);
  });
});
