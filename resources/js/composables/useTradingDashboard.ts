import { ref, type Ref } from 'vue';
import { toApiError } from '@/services/api';
import { fetchDashboard } from '@/services/dashboard';
import type { DashboardData, DashboardPeriod } from '@/types/dashboard';

/**
 * Own dashboard request state while preserving data during same-period refreshes.
 */
export function useTradingDashboard(): {
    dashboard: Ref<DashboardData | null>;
    loading: Ref<boolean>;
    refreshing: Ref<boolean>;
    error: Ref<string | null>;
    loadDashboard: (period: DashboardPeriod) => Promise<void>;
} {
    const dashboard = ref<DashboardData | null>(null);
    const loading = ref(true);
    const refreshing = ref(false);
    const error = ref<string | null>(null);
    let requestId = 0;

    async function loadDashboard(period: DashboardPeriod): Promise<void> {
        const request = ++requestId;
        const samePeriod = dashboard.value?.period === period;

        error.value = null;
        if (samePeriod) {
            refreshing.value = true;
        } else {
            dashboard.value = null;
            loading.value = true;
        }

        try {
            const response = await fetchDashboard(period);

            if (request === requestId) {
                dashboard.value = response.dashboard;
            }
        } catch (requestError) {
            if (request === requestId) {
                error.value = toApiError(requestError).message;
            }
        } finally {
            if (request === requestId) {
                loading.value = false;
                refreshing.value = false;
            }
        }
    }

    return { dashboard, loading, refreshing, error, loadDashboard };
}
