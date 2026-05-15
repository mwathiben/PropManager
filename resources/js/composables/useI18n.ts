/**
 * Phase-24 I18N-FRONT-2: the canonical translation accessor for Vue
 * components. Components import THIS — not vue-i18n directly — so a
 * future engine swap is a one-file change. It re-exports vue-i18n's
 * `t` + `locale` and adds the supported-locale list from the
 * Inertia-shared props (HandleInertiaRequests I18N-INFRA-3).
 *
 * Usage in a component:
 *   const { t, locale, availableLocales } = useI18n();
 *   t('common.save')
 */
import { computed, type ComputedRef, type WritableComputedRef } from 'vue';
import { useI18n as useVueI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';

interface UseI18nReturn {
    t: ReturnType<typeof useVueI18n>['t'];
    locale: WritableComputedRef<string>;
    availableLocales: ComputedRef<Record<string, string>>;
}

export function useI18n(): UseI18nReturn {
    const { t, locale } = useVueI18n();
    const page = usePage();

    const availableLocales = computed(
        () => (page.props.availableLocales as Record<string, string>) ?? { en: 'English' },
    );

    return { t, locale, availableLocales };
}
