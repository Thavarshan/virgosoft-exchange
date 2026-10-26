import { ref, type Ref } from 'vue';
import { toApiError } from '@/services/api';
import { fetchOrderHistory } from '@/services/trading';
import type { OrderHistoryResponse, TradingOrder } from '@/types/trading';

/**
 * Own paginated order-history state and protect it from stale responses.
 */
export function useOrderHistory(): {
    orders: Ref<TradingOrder[]>;
    pagination: Ref<OrderHistoryResponse['meta'] | null>;
    loading: Ref<boolean>;
    refreshing: Ref<boolean>;
    error: Ref<string | null>;
    loadHistory: (page?: number) => Promise<void>;
} {
    const orders = ref<TradingOrder[]>([]);
    const pagination = ref<OrderHistoryResponse['meta'] | null>(null);
    const loading = ref(true);
    const refreshing = ref(false);
    const error = ref<string | null>(null);
    let requestId = 0;

    async function loadHistory(
        page = pagination.value?.current_page ?? 1,
    ): Promise<void> {
        const request = ++requestId;

        if (orders.value.length > 0) {
            refreshing.value = true;
        } else {
            loading.value = true;
        }

        try {
            const response = await fetchOrderHistory(page);

            if (request === requestId) {
                orders.value = response.orders;
                pagination.value = response.meta;
                error.value = null;
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

    return { orders, pagination, loading, refreshing, error, loadHistory };
}
