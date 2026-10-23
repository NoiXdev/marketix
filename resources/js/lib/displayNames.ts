export function displayName(type: 'region' | 'language', code: string, locale: string, fallback?: string): string {
  if (!code) return fallback ?? code;
  try {
    return new Intl.DisplayNames([locale], { type }).of(type === 'region' ? code.toUpperCase() : code) ?? fallback ?? code;
  } catch {
    return fallback ?? code;
  }
}

export function countryName(code: string, locale: string, fallback?: string): string {
  return displayName('region', code, locale, fallback);
}

const LEGACY_LANGUAGE_CODES: Record<string, string> = { gs: 'gsw' };

export function canonicalLanguage(code: string): string {
  const key = code.trim().toLowerCase();
  return LEGACY_LANGUAGE_CODES[key] ?? key;
}

export function languageName(code: string, locale: string): string {
  return displayName('language', canonicalLanguage(code), locale, code);
}
