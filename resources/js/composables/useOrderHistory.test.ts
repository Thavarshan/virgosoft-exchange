import { defineComponent, h } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useOrderHistory } from './useOrderHistory';
import type { OrderHistoryResponse, TradingOrder } from '@/types/trading';

const { fetchOrderHistory } = vi.hoisted(() => ({
    fetchOrderHistory: vi.fn(),
}));

vi.mock('@/services/trading', () => ({ fetchOrderHistory }));

function order(id: number): TradingOrder {
    return {
        id,
        user_id: 1,
        symbol: 'BTC',
        side: 'buy',
        price: '95000.00',
        amount: '0.01000000',
        status: 1,
        created_at: '2026-10-26T00:00:00.000000Z',
        updated_at: '2026-10-26T00:00:00.000000Z',
    };
}

function historyPage(
    ids: number[],
    currentPage = 1,
    total = ids.length,
): OrderHistoryResponse {
    return {
        orders: ids.map((id) => order(id)),
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: currentPage,
            last_page: Math.max(1, Math.ceil(total / 20)),
            from: ids.length > 0 ? 1 : null,
            links: [],
            path: '/api/profile/orders',
            per_page: 20,
            to: ids.length > 0 ? ids.length : null,
            total,
        },
    };
}

function history(...ids: number[]): OrderHistoryResponse {
    return historyPage(ids);
}

describe('useOrderHistory', () => {
    let state: ReturnType<typeof useOrderHistory>;
    let wrapper: VueWrapper;

    beforeEach(() => {
        fetchOrderHistory.mockReset();
        const Host = defineComponent({
            setup() {
                state = useOrderHistory();

                return () => h('div');
            },
        });

        wrapper = mount(Host);
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('exposes an initial failure and recovers on retry', async () => {
        fetchOrderHistory.mockRejectedValueOnce({
            response: { status: 503, data: { message: 'History unavailable' } },
        });

        await state.loadHistory();

        expect(state.orders.value).toEqual([]);
        expect(state.error.value).toBe('History unavailable');

        fetchOrderHistory.mockResolvedValueOnce(history(1));
        await state.loadHistory();

        expect(state.orders.value.map(({ id }) => id)).toEqual([1]);
        expect(state.error.value).toBeNull();
    });

    it('retains history and exposes a stale error during a refresh failure', async () => {
        fetchOrderHistory.mockResolvedValueOnce(history(1));
        await state.loadHistory();
        fetchOrderHistory.mockRejectedValueOnce({
            response: { status: 503, data: { message: 'Refresh failed' } },
        });

        await state.loadHistory(1);

        expect(state.orders.value.map(({ id }) => id)).toEqual([1]);
        expect(state.error.value).toBe('Refresh failed');
        expect(state.refreshing.value).toBe(false);
    });

    it('preserves the requested page while out-of-order responses resolve', async () => {
        fetchOrderHistory.mockResolvedValueOnce(history(1));
        await state.loadHistory();

        let resolvePageTwo: (value: OrderHistoryResponse) => void = () => {};
        let resolvePageThree: (value: OrderHistoryResponse) => void = () => {};
        fetchOrderHistory.mockReturnValueOnce(
            new Promise<OrderHistoryResponse>((resolve) => {
                resolvePageTwo = resolve;
            }),
        );
        fetchOrderHistory.mockReturnValueOnce(
            new Promise<OrderHistoryResponse>((resolve) => {
                resolvePageThree = resolve;
            }),
        );

        const pageTwoRequest = state.loadHistory(2);
        const pageThreeRequest = state.loadHistory(3);
        resolvePageThree(historyPage([3], 3, 41));
        await pageThreeRequest;
        resolvePageTwo(history(2));
        await pageTwoRequest;

        expect(fetchOrderHistory).toHaveBeenNthCalledWith(2, 2, {});
        expect(fetchOrderHistory).toHaveBeenNthCalledWith(3, 3, {});
        expect(state.orders.value.map(({ id }) => id)).toEqual([3]);
    });

    it('resets to page one and ignores stale filter responses', async () => {
        fetchOrderHistory.mockResolvedValueOnce(history(1));
        await state.loadHistory();

        let resolveBtc: (value: OrderHistoryResponse) => void = () => {};
        let resolveEth: (value: OrderHistoryResponse) => void = () => {};
        fetchOrderHistory.mockReturnValueOnce(
            new Promise<OrderHistoryResponse>((resolve) => {
                resolveBtc = resolve;
            }),
        );
        fetchOrderHistory.mockReturnValueOnce(
            new Promise<OrderHistoryResponse>((resolve) => {
                resolveEth = resolve;
            }),
        );

        const btcRequest = state.setFilters({ symbol: 'BTC' });
        expect(state.orders.value).toEqual([]);
        const ethRequest = state.setFilters({ symbol: 'ETH' });

        resolveEth(history(3));
        await ethRequest;
        resolveBtc(history(2));
        await btcRequest;

        expect(fetchOrderHistory).toHaveBeenNthCalledWith(2, 1, {
            symbol: 'BTC',
        });
        expect(fetchOrderHistory).toHaveBeenNthCalledWith(3, 1, {
            symbol: 'ETH',
        });
        expect(state.orders.value.map(({ id }) => id)).toEqual([3]);
    });

    it('falls back once when the requested last page disappears', async () => {
        fetchOrderHistory.mockResolvedValueOnce(history(1, 1, 21));
        await state.loadHistory();

        fetchOrderHistory.mockResolvedValueOnce(historyPage([], 2, 20));
        fetchOrderHistory.mockResolvedValueOnce(historyPage([99], 1, 20));

        await state.loadHistory(2);

        expect(fetchOrderHistory).toHaveBeenNthCalledWith(2, 2, {});
        expect(fetchOrderHistory).toHaveBeenNthCalledWith(3, 1, {});
        expect(state.pagination.value?.current_page).toBe(1);
        expect(state.orders.value.map(({ id }) => id)).toEqual([99]);
    });
});
