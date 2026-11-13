import { ref } from 'vue';
import es from '../../../lang/es.json';

export type Locale = 'en' | 'es';

// English strings are the keys; a missing translation falls back to English.
const catalogs: Record<Locale, Record<string, string>> = { en: {}, es };

const intlLocales: Record<Locale, string> = { en: 'en-US', es: 'es-ES' };

export const locale = ref<Locale>('en');

export function setLocale(value: string | null | undefined): void {
    const next: Locale = value?.startsWith('es') ? 'es' : 'en';

    locale.value = next;

    if (typeof document !== 'undefined') {
        document.documentElement.lang = next;
    }
}

/** BCP 47 tag for Intl number and date formatting. */
export function intlLocale(): string {
    return intlLocales[locale.value];
}

/**
 * Translate an English string. `:name` placeholders are replaced from
 * `replacements`, the same convention as Laravel's __(); null prints as
 * nothing, like `{{ null }}` in a template.
 */
export function t(
    key: string,
    replacements: Record<string, string | number | null | undefined> = {},
): string {
    let text = catalogs[locale.value][key] ?? key;

    // Longest names first, so `:count` is never eaten by a `:c` placeholder.
    const entries = Object.entries(replacements).sort(
        ([a], [b]) => b.length - a.length,
    );

    for (const [name, value] of entries) {
        text = text.replaceAll(`:${name}`, String(value ?? ''));
    }

    return text;
}
