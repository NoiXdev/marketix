export type WidgetType = 'kpi' | 'timeseries' | 'top_list' | 'geo_map' | 'activity' | 'quick_actions';

export interface WidgetConfig {
  metric?: string;
  dimension?: string;
  limit?: number;
  days?: number;
  title?: string | null;
}

export interface WidgetLayout {
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface Widget {
  id: string;
  type: WidgetType;
  config: WidgetConfig;
  layout: WidgetLayout;
}

interface WidgetDef {
  labelKey: string;
  defaultConfig: WidgetConfig;
  defaultLayout: { w: number; h: number; minW: number; minH: number; maxH: number };
}

export const WIDGET_DEFS: Record<WidgetType, WidgetDef> = {
  kpi: {
    labelKey: 'widgets.types.kpi',
    defaultConfig: { metric: 'clicks', days: 30, title: null },
    defaultLayout: { w: 3, h: 2, minW: 2, minH: 2, maxH: 4 },
  },
  timeseries: {
    labelKey: 'widgets.types.timeseries',
    defaultConfig: { days: 30, title: null },
    defaultLayout: { w: 8, h: 5, minW: 4, minH: 4, maxH: 8 },
  },
  top_list: {
    labelKey: 'widgets.types.top_list',
    defaultConfig: { dimension: 'links', limit: 5, days: 30, title: null },
    defaultLayout: { w: 4, h: 5, minW: 3, minH: 3, maxH: 10 },
  },
  geo_map: {
    labelKey: 'widgets.types.geo_map',
    defaultConfig: { days: 30, title: null },
    defaultLayout: { w: 8, h: 6, minW: 4, minH: 4, maxH: 10 },
  },
  activity: {
    labelKey: 'widgets.types.activity',
    defaultConfig: { limit: 6, title: null },
    defaultLayout: { w: 4, h: 5, minW: 3, minH: 3, maxH: 10 },
  },
  quick_actions: {
    labelKey: 'widgets.types.quick_actions',
    defaultConfig: { title: null },
    defaultLayout: { w: 6, h: 2, minW: 3, minH: 2, maxH: 4 },
  },
};

export function newWidget(type: WidgetType): Widget {
  const def = WIDGET_DEFS[type];
  return {
    id: crypto.randomUUID(),
    type,
    config: { ...def.defaultConfig },
    // y:Infinity tells react-grid-layout to drop the item at the bottom.
    layout: { x: 0, y: Infinity, w: def.defaultLayout.w, h: def.defaultLayout.h },
  };
}
