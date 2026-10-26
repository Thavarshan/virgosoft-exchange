import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrderHistoryPanel from './OrderHistoryPanel.vue';
import type { OrderHistoryResponse, TradingOrder } from '@/types/trading';

function order(id: number, status: TradingOrder['status'] = 1): TradingOrder {
    return {
        id,
        user_id: 1,
        symbol: 'BTC',
        side: 'buy',
        price: '95000.00',
        amount: '0.01000000',
        status,
        created_at: '2026-10-26T00:00:00.000000Z',
        updated_at: '2026-10-26T00:00:00.000000Z',
    };
}

function pagination(
    total: number,
    currentPage = 1,
): OrderHistoryResponse['meta'] {
    return {
        current_page: currentPage,
        from: total ? 1 : null,
        last_page: Math.ceil(total / 20),
        links: [],
        path: '/api/profile/orders',
        per_page: 20,
        to: total ? Math.min(total, 20) : null,
        total,
    };
}

describe('OrderHistoryPanel', () => {
    it('shows an empty state without order history', () => {
        const wrapper = mount(OrderHistoryPanel, {
            props: {
                orders: [],
                pagination: pagination(0),
                loading: false,
                cancellingOrderIds: new Set<number>(),
                error: null,
            },
        });

        expect(wrapper.text()).toContain('No orders yet');
        expect(wrapper.text()).toContain('0 total');
    });

    it('emits cancellation and pagination events', async () => {
        const wrapper = mount(OrderHistoryPanel, {
            props: {
                orders: [order(1)],
                pagination: pagination(21),
                loading: false,
                cancellingOrderIds: new Set<number>(),
                error: null,
            },
        });

        const cancelButton = wrapper
            .findAll('button')
            .find((button) => button.text().trim() === 'Cancel');

        await cancelButton?.trigger('click');
        expect(document.body.textContent).toContain(
            'This returns the reservation',
        );
        document
            .querySelector('[data-test="confirm-cancel"]')
            ?.dispatchEvent(new Event('click'));
        await wrapper.vm.$nextTick();
        expect(wrapper.emitted('cancel')).toEqual([[1]]);

        const pageTwo = wrapper
            .findAll('[data-slot="pagination-link"]')
            .find((link) => link.text() === '2');

        expect(pageTwo).toBeDefined();
        await pageTwo?.trigger('click');
        expect(wrapper.emitted('page')).toEqual([[2]]);
    });

    it('does not offer cancellation for completed orders', () => {
        const wrapper = mount(OrderHistoryPanel, {
            props: {
                orders: [order(1, 2), order(2, 3)],
                pagination: pagination(2),
                loading: false,
                cancellingOrderIds: new Set<number>(),
                error: null,
            },
        });

        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text().trim() === 'Cancel'),
        ).toBe(false);
    });

    it('does not claim there are no orders when history loading fails', () => {
        const wrapper = mount(OrderHistoryPanel, {
            props: {
                orders: [],
                pagination: null,
                loading: false,
                cancellingOrderIds: new Set<number>(),
                error: 'History request failed',
            },
        });

        expect(wrapper.text()).toContain('History request failed');
        expect(wrapper.text()).not.toContain('No orders yet');
    });

    it('distinguishes an empty filtered result from an empty history', () => {
        const wrapper = mount(OrderHistoryPanel, {
            props: {
                orders: [],
                pagination: pagination(0),
                loading: false,
                cancellingOrderIds: new Set<number>(),
                error: null,
                filters: { symbol: 'ETH' },
            },
        });

        expect(wrapper.text()).toContain('No orders match these filters');
        expect(wrapper.text()).not.toContain('No orders yet');
        expect(wrapper.text()).toContain('Clear filters');
    });
});
