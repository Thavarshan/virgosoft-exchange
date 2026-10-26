import { ref, type Ref } from 'vue';
import { toApiError } from '@/services/api';
import { fetchOrderHistory } from '@/services/trading';
import type {
    OrderHistoryFilters,
    OrderHistoryResponse,
    TradingOrder,
} from '@/types/trading';

/**
 * Own paginated order-history state and protect it from stale responses.
 */
export function useOrderHistory(): {
    orders: Ref<TradingOrder[]>;
    pagination: Ref<OrderHistoryResponse['meta'] | null>;
    loading: Ref<boolean>;
    refreshing: Ref<boolean>;
    error: Ref<string | null>;
    filters: Ref<OrderHistoryFilters>;
    loadHistory: (page?: number) => Promise<void>;
    setFilters: (filters: OrderHistoryFilters) => Promise<void>;
} {
    const orders = ref<TradingOrder[]>([]);
    const pagination = ref<OrderHistoryResponse['meta'] | null>(null);
    const loading = ref(true);
    const refreshing = ref(false);
    const error = ref<string | null>(null);
    const filters = ref<OrderHistoryFilters>({});
    let requestId = 0;
    let loadedPage: number | null = null;
    let loadedFilterKey: string | null = null;

    function filterKey(value: OrderHistoryFilters): string {
        return JSON.stringify([
            value.symbol ?? null,
            value.side ?? null,
            value.status ?? null,
        ]);
    }

    async function loadHistory(
        page = pagination.value?.current_page ?? 1,
    ): Promise<void> {
        const request = ++requestId;
        const requestFilters = { ...filters.value };
        const requestFilterKey = filterKey(requestFilters);
        const queryChanged =
            loadedPage !== page || loadedFilterKey !== requestFilterKey;

        if (queryChanged) {
            orders.value = [];
            pagination.value = null;
            loading.value = true;
            refreshing.value = false;
        } else if (orders.value.length > 0) {
            refreshing.value = true;
        } else {
            loading.value = true;
        }

        error.value = queryChanged ? null : error.value;

        try {
            const response = await fetchOrderHistory(page, requestFilters);

            if (
                request === requestId &&
                page > response.meta.last_page &&
                response.meta.last_page > 0
            ) {
                await loadHistory(response.meta.last_page);

                return;
            }

            if (request === requestId) {
                orders.value = response.orders;
                pagination.value = response.meta;
                loadedPage = response.meta.current_page;
                loadedFilterKey = requestFilterKey;
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

    function setFilters(nextFilters: OrderHistoryFilters): Promise<void> {
        filters.value = { ...nextFilters };

        return loadHistory(1);
    }

    return {
        orders,
        pagination,
        loading,
        refreshing,
        error,
        filters,
        loadHistory,
        setFilters,
    };
}
