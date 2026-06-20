import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

type Catalog = Record<string, unknown>;
type Replacements = Record<string, string | number>;

export function translate(
  catalog: Catalog,
  key: string,
  replacements?: Replacements,
): string {
  let node: unknown = catalog;
  for (const segment of key.split('.')) {
    if (node && typeof node === 'object' && segment in (node as Catalog)) {
      node = (node as Catalog)[segment];
    } else {
      return key;
    }
  }

  if (typeof node !== 'string') {
    return key;
  }

  if (!replacements) {
    return node;
  }

  return Object.entries(replacements).reduce(
    (acc, [name, value]) => acc.replaceAll(`:${name}`, String(value)),
    node,
  );
}

export function useTranslation() {
  const { translations, locale } = usePage<PageProps>().props;
  return {
    locale,
    t: (key: string, replacements?: Replacements) =>
      translate(translations as Catalog, key, replacements),
  };
}
