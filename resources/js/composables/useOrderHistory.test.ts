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

function history(...ids: number[]): OrderHistoryResponse {
    return {
        orders: ids.map((id) => order(id)),
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            from: ids.length > 0 ? 1 : null,
            last_page: 1,
            links: [],
            path: '/api/profile/orders',
            per_page: 20,
            to: ids.length > 0 ? ids.length : null,
            total: ids.length,
        },
    };
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
        resolvePageThree(history(3));
        await pageThreeRequest;
        resolvePageTwo(history(2));
        await pageTwoRequest;

        expect(fetchOrderHistory).toHaveBeenNthCalledWith(2, 2);
        expect(fetchOrderHistory).toHaveBeenNthCalledWith(3, 3);
        expect(state.orders.value.map(({ id }) => id)).toEqual([3]);
    });
});
