import { ref, type Ref } from 'vue';
import { toApiError } from '@/services/api';
import { fetchOrderbook } from '@/services/trading';
import type { AssetSymbol, TradingOrder } from '@/types/trading';

/**
 * Own orderbook loading, polling-friendly refresh state, and stale-response
 * protection for the selected market.
 */
export function useOrderbook(): {
    orders: Ref<TradingOrder[]>;
    loadedSymbol: Ref<AssetSymbol | null>;
    loading: Ref<boolean>;
    refreshing: Ref<boolean>;
    error: Ref<string | null>;
    lastUpdated: Ref<string | null>;
    loadOrderbook: (
        symbol: AssetSymbol,
        showLoading?: boolean,
    ) => Promise<void>;
} {
    const orders = ref<TradingOrder[]>([]);
    const loadedSymbol = ref<AssetSymbol | null>(null);
    const loading = ref(true);
    const refreshing = ref(false);
    const error = ref<string | null>(null);
    const lastUpdated = ref<string | null>(null);
    let requestId = 0;

    async function loadOrderbook(
        symbol: AssetSymbol,
        showLoading = orders.value.length === 0,
    ): Promise<void> {
        const request = ++requestId;
        const changingMarket =
            loadedSymbol.value !== null && loadedSymbol.value !== symbol;

        if (changingMarket) {
            // Never label the previous market's rows as the newly selected market.
            orders.value = [];
            loadedSymbol.value = null;
            lastUpdated.value = null;
            error.value = null;
            loading.value = true;
            refreshing.value = false;
        } else if (showLoading && orders.value.length === 0) {
            loading.value = true;
        } else {
            refreshing.value = true;
        }

        try {
            const response = await fetchOrderbook(symbol);

            if (request === requestId) {
                orders.value = response.orders;
                loadedSymbol.value = symbol;
                error.value = null;
                lastUpdated.value = new Date().toISOString();
            }
        } catch (requestError) {
            if (request === requestId) {
                if (changingMarket) {
                    orders.value = [];
                    loadedSymbol.value = null;
                    lastUpdated.value = null;
                }
                error.value = toApiError(requestError).message;
            }
        } finally {
            if (request === requestId) {
                loading.value = false;
                refreshing.value = false;
            }
        }
    }

    return {
        orders,
        loadedSymbol,
        loading,
        refreshing,
        error,
        lastUpdated,
        loadOrderbook,
    };
}
