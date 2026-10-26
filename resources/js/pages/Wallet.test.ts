import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
    OrderHistoryResponse,
    OrderbookResponse,
    ProfileResponse,
    TradingOrder,
} from '@/types/trading';
import OrderHistoryPanel from '@/components/trading/OrderHistoryPanel.vue';

const {
    fetchProfile,
    fetchOrderHistory,
    fetchOrderbook,
    cancelOrder,
    toastSuccess,
} = vi.hoisted(() => ({
    fetchProfile: vi.fn(),
    fetchOrderHistory: vi.fn(),
    fetchOrderbook: vi.fn(),
    cancelOrder: vi.fn(),
    toastSuccess: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
}));

vi.mock('@/routes', () => ({ wallet: () => '/wallet' }));
vi.mock('@/services/trading', () => ({
    fetchProfile,
    fetchOrderHistory,
    fetchOrderbook,
    cancelOrder,
}));
vi.mock('@/services/echo', () => ({ echo: null, isEchoConfigured: false }));
vi.mock('@/composables/useTradingRealtime', () => ({
    useTradingRealtime: () => ({ connectionStatus: ref('unconfigured') }),
}));
vi.mock('vue-sonner', () => ({ toast: { success: toastSuccess } }));

import Wallet from './Wallet.vue';

function profile(): ProfileResponse {
    return {
        user: {
            balance: '1000.00',
            locked_balance: '0.00',
            assets: [
                {
                    symbol: 'BTC',
                    amount: '1.00000000',
                    locked_amount: '0.00000000',
                },
            ],
        },
    };
}

function order(id: number): TradingOrder {
    return {
        id,
        user_id: 7,
        symbol: 'BTC',
        side: 'buy',
        price: '95000.00',
        amount: '0.01000000',
        status: 1,
        created_at: '2026-10-26T00:00:00.000000Z',
        updated_at: '2026-10-26T00:00:00.000000Z',
    };
}

function history(
    orders: TradingOrder[],
    currentPage = 1,
    total = orders.length,
): OrderHistoryResponse {
    return {
        orders,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: currentPage,
            from: orders.length > 0 ? 1 : null,
            last_page: Math.max(1, Math.ceil(total / 20)),
            links: [],
            path: '/api/profile/orders',
            per_page: 20,
            to: orders.length > 0 ? orders.length : null,
            total,
        },
    };
}

function orderbook(): OrderbookResponse {
    return { orders: [] };
}

describe('Wallet page', () => {
    let wrapper: VueWrapper;

    beforeEach(() => {
        fetchProfile.mockReset().mockResolvedValue(profile());
        fetchOrderHistory.mockReset().mockResolvedValue(history([order(1)]));
        fetchOrderbook.mockReset().mockResolvedValue(orderbook());
        cancelOrder.mockReset();
        toastSuccess.mockReset();
        wrapper = mount(Wallet, {
            attachTo: document.body,
            global: {
                stubs: {
                    PageHeader: { template: '<div><slot /></div>' },
                    ConnectionBadge: { template: '<div />' },
                },
            },
        });
    });

    afterEach(() => {
        wrapper.unmount();
        document.body.innerHTML = '';
        vi.useRealTimers();
    });

    it('confirms cancellation, refreshes the current page, and reports success', async () => {
        cancelOrder.mockResolvedValueOnce({
            order: { ...order(1), status: 3 },
        });
        await flushPromises();

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Cancel');
        await cancelButton?.trigger('click');
        document
            .querySelector<HTMLButtonElement>('[data-test="confirm-cancel"]')
            ?.click();
        await flushPromises();

        expect(cancelOrder).toHaveBeenCalledWith(1);
        expect(fetchOrderHistory).toHaveBeenLastCalledWith(1, {});
        expect(toastSuccess).toHaveBeenCalledWith('Order cancelled', {
            description: 'The reservation has been returned to your wallet.',
        });
    });

    it('refreshes the current history page after a rejected cancellation', async () => {
        await flushPromises();
        fetchOrderHistory.mockClear();
        fetchOrderHistory.mockResolvedValueOnce(history([order(2)], 2, 21));

        wrapper.findComponent(OrderHistoryPanel).vm.$emit('page', 2);
        await flushPromises();

        cancelOrder.mockRejectedValueOnce({
            response: {
                status: 422,
                data: { message: 'Order is no longer open.' },
            },
        });
        fetchOrderHistory.mockResolvedValueOnce(history([order(2)], 2, 21));

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Cancel');
        await cancelButton?.trigger('click');
        document
            .querySelector<HTMLButtonElement>('[data-test="confirm-cancel"]')
            ?.click();
        await flushPromises();

        expect(fetchOrderHistory).toHaveBeenLastCalledWith(2, {});
        expect(wrapper.text()).toContain('Order is no longer open.');
    });

    it('resets filtered history to page one and preserves filters on cancellation', async () => {
        await flushPromises();
        fetchOrderHistory.mockClear();
        fetchOrderHistory.mockResolvedValueOnce(history([order(1)]));

        wrapper.findComponent(OrderHistoryPanel).vm.$emit('filters', {
            symbol: 'BTC',
            side: 'buy',
        });
        await flushPromises();

        cancelOrder.mockResolvedValueOnce({
            order: { ...order(1), status: 3 },
        });
        fetchOrderHistory.mockResolvedValueOnce(history([order(1)]));

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Cancel');
        await cancelButton?.trigger('click');
        document
            .querySelector<HTMLButtonElement>('[data-test="confirm-cancel"]')
            ?.click();
        await flushPromises();

        expect(fetchOrderHistory).toHaveBeenLastCalledWith(1, {
            symbol: 'BTC',
            side: 'buy',
        });
    });

    it('pauses polling while hidden and removes polling and listeners on unmount', async () => {
        vi.useFakeTimers();
        await flushPromises();
        const initialCalls = fetchOrderbook.mock.calls.length;
        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            value: 'hidden',
        });

        await vi.advanceTimersByTimeAsync(15_000);
        expect(fetchOrderbook).toHaveBeenCalledTimes(initialCalls);

        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            value: 'visible',
        });
        document.dispatchEvent(new Event('visibilitychange'));
        await flushPromises();
        expect(fetchOrderbook).toHaveBeenCalledTimes(initialCalls + 1);

        wrapper.unmount();
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(15_000);
        expect(fetchOrderbook).toHaveBeenCalledTimes(initialCalls + 1);
    });
});
