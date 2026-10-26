import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useOrderbook } from './useOrderbook';
import type { OrderbookResponse, TradingOrder } from '@/types/trading';

const { fetchOrderbook } = vi.hoisted(() => ({
    fetchOrderbook: vi.fn(),
}));

vi.mock('@/services/trading', () => ({ fetchOrderbook }));

function order(id: number, symbol: TradingOrder['symbol']): TradingOrder {
    return {
        id,
        user_id: 1,
        symbol,
        side: 'buy',
        price: '95000.00',
        amount: '0.01000000',
        status: 1,
        created_at: '2026-10-26T00:00:00.000000Z',
        updated_at: '2026-10-26T00:00:00.000000Z',
    };
}

function response(...orders: TradingOrder[]): OrderbookResponse {
    return { orders };
}

describe('useOrderbook', () => {
    let state: ReturnType<typeof useOrderbook>;

    beforeEach(() => {
        fetchOrderbook.mockReset();
        const Host = defineComponent({
            setup() {
                state = useOrderbook();

                return () => h('div');
            },
        });

        mount(Host);
    });

    it('keeps same-market rows visible during a background refresh', async () => {
        fetchOrderbook.mockResolvedValueOnce(response(order(1, 'BTC')));
        await state.loadOrderbook('BTC');

        let resolveRefresh: (value: OrderbookResponse) => void = () => {};
        const refresh = new Promise<OrderbookResponse>((resolve) => {
            resolveRefresh = resolve;
        });
        fetchOrderbook.mockReturnValueOnce(refresh);

        const pending = state.loadOrderbook('BTC', false);

        expect(state.orders.value).toHaveLength(1);
        expect(state.loadedSymbol.value).toBe('BTC');
        expect(state.refreshing.value).toBe(true);

        resolveRefresh(response(order(2, 'BTC')));
        await pending;

        expect(state.orders.value[0]?.id).toBe(2);
        expect(state.lastUpdated.value).not.toBeNull();
    });

    it('clears old rows and ignores out-of-order responses during market changes', async () => {
        fetchOrderbook.mockResolvedValueOnce(response(order(1, 'BTC')));
        await state.loadOrderbook('BTC');

        let resolveEth: (value: OrderbookResponse) => void = () => {};
        let resolveBtc: (value: OrderbookResponse) => void = () => {};
        fetchOrderbook.mockReturnValueOnce(
            new Promise<OrderbookResponse>((resolve) => {
                resolveEth = resolve;
            }),
        );
        fetchOrderbook.mockReturnValueOnce(
            new Promise<OrderbookResponse>((resolve) => {
                resolveBtc = resolve;
            }),
        );

        const ethRequest = state.loadOrderbook('ETH');
        expect(state.orders.value).toEqual([]);
        expect(state.loadedSymbol.value).toBeNull();
        expect(state.lastUpdated.value).toBeNull();

        const btcRequest = state.loadOrderbook('BTC');
        resolveEth(response(order(2, 'ETH')));
        await nextTick();
        expect(state.orders.value).toEqual([]);

        resolveBtc(response(order(3, 'BTC')));
        await Promise.all([ethRequest, btcRequest]);

        expect(state.loadedSymbol.value).toBe('BTC');
        expect(state.orders.value[0]?.id).toBe(3);
    });

    it('does not retain another market when that market request fails', async () => {
        fetchOrderbook.mockResolvedValueOnce(response(order(1, 'BTC')));
        await state.loadOrderbook('BTC');
        fetchOrderbook.mockRejectedValueOnce({
            response: {
                status: 500,
                data: { message: 'Market request failed' },
            },
        });

        await state.loadOrderbook('ETH');

        expect(state.orders.value).toEqual([]);
        expect(state.loadedSymbol.value).toBeNull();
        expect(state.lastUpdated.value).toBeNull();
        expect(state.error.value).toBe('Market request failed');
    });
});
