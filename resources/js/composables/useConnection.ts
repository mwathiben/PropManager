/**
 * Phase-26 PWA-NETWORK-2: Network Information API wrapper.
 *
 * Surfaces navigator.connection.{effectiveType, saveData} reactively
 * so pages can defer expensive renders (Chart.js panes on Dashboard
 * + Reports thumbnails) on slow connections or when the user has
 * Data-Saver enabled.
 *
 * Why a composable instead of inline navigator checks? Two reasons:
 *   1. Firefox and Safari don't ship the NetworkInformation API.
 *      The composable returns safe fallbacks (`isSlow=false,
 *      effectiveType='4g'`) so pages don't need browser-detection.
 *   2. Connection state changes via `change` event — components need
 *      reactivity, not a one-time read.
 *
 * Threshold for `isSlow`: effectiveType in ('slow-2g', '2g') OR
 * saveData=true. Tested against the Chrome DevTools "Slow 3G"
 * preset which sets effectiveType='3g' — explicitly NOT slow by
 * this rule. 3G is the BASELINE Kenyan network; we don't downgrade
 * the UX for every 3G user, only for actually-bad networks.
 */

import { ref, onMounted, onBeforeUnmount, type Ref } from 'vue';

type EffectiveType = 'slow-2g' | '2g' | '3g' | '4g';

type NetworkInformation = {
    effectiveType?: EffectiveType;
    saveData?: boolean;
    downlink?: number;
    rtt?: number;
    addEventListener: (event: 'change', cb: () => void) => void;
    removeEventListener: (event: 'change', cb: () => void) => void;
};

type NavigatorWithConnection = Navigator & {
    connection?: NetworkInformation;
    mozConnection?: NetworkInformation;
    webkitConnection?: NetworkInformation;
};

export type UseConnectionReturn = {
    effectiveType: Ref<EffectiveType>;
    saveData: Ref<boolean>;
    downlink: Ref<number | null>;
    rtt: Ref<number | null>;
    isSlow: Ref<boolean>;
};

function getConnection(): NetworkInformation | undefined {
    if (typeof navigator === 'undefined') return undefined;
    const nav = navigator as NavigatorWithConnection;
    return nav.connection ?? nav.mozConnection ?? nav.webkitConnection;
}

export function useConnection(): UseConnectionReturn {
    const conn = getConnection();

    const effectiveType = ref<EffectiveType>(conn?.effectiveType ?? '4g');
    const saveData = ref<boolean>(conn?.saveData ?? false);
    const downlink = ref<number | null>(conn?.downlink ?? null);
    const rtt = ref<number | null>(conn?.rtt ?? null);
    const isSlow = ref<boolean>(false);

    function recompute(): void {
        const c = getConnection();
        if (!c) return;
        effectiveType.value = c.effectiveType ?? '4g';
        saveData.value = c.saveData ?? false;
        downlink.value = c.downlink ?? null;
        rtt.value = c.rtt ?? null;
        isSlow.value = effectiveType.value === 'slow-2g' || effectiveType.value === '2g' || saveData.value === true;
    }

    recompute();

    onMounted(() => {
        if (conn) {
            conn.addEventListener('change', recompute);
        }
    });

    onBeforeUnmount(() => {
        if (conn) {
            conn.removeEventListener('change', recompute);
        }
    });

    return { effectiveType, saveData, downlink, rtt, isSlow };
}
