import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ProfileResponse } from '@/types/trading';

const { fetchProfile, placeOrder, toastSuccess } = vi.hoisted(() => ({
    fetchProfile: vi.fn(),
    placeOrder: vi.fn(),
    toastSuccess: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    usePage: () => ({ props: { auth: { user: { id: 7 } } } }),
}));

vi.mock('@/routes', () => ({ trade: () => '/trade' }));
vi.mock('@/services/trading', () => ({ fetchProfile, placeOrder }));
vi.mock('@/services/echo', () => ({ echo: null, isEchoConfigured: false }));
vi.mock('@/composables/useTradingRealtime', () => ({
    useTradingRealtime: () => ({ connectionStatus: ref('unconfigured') }),
}));
vi.mock('vue-sonner', () => ({ toast: { success: toastSuccess } }));

import Trade from './Trade.vue';

function profile(balance = '1000.00'): ProfileResponse {
    return {
        user: {
            balance,
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

function placedOrder(status = 1): { order: Record<string, unknown> } {
    return {
        order: {
            id: 1,
            user_id: 7,
            symbol: 'BTC',
            side: 'buy',
            price: '90000.00',
            amount: '0.01000000',
            status,
        },
    };
}

describe('Trade page', () => {
    let wrapper: VueWrapper;

    beforeEach(() => {
        fetchProfile.mockReset();
        placeOrder.mockReset();
        toastSuccess.mockReset();
        fetchProfile.mockResolvedValue(profile());
        wrapper = mount(Trade, {
            global: {
                stubs: {
                    PageHeader: { template: '<div><slot /></div>' },
                    ConnectionBadge: { template: '<div />' },
                    TradingGuideDialog: { template: '<div />' },
                },
            },
        });
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('sends exact decimal-string values and blocks duplicate submissions', async () => {
        let resolveOrder: (
            value: ReturnType<typeof placedOrder>,
        ) => void = () => {};
        placeOrder.mockReturnValueOnce(
            new Promise<ReturnType<typeof placedOrder>>((resolve) => {
                resolveOrder = resolve;
            }),
        );
        await flushPromises();

        await wrapper.get('#order-price').setValue('95000.00');
        await wrapper.get('#order-amount').setValue('0.01000000');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');

        expect(placeOrder).toHaveBeenCalledTimes(1);
        expect(placeOrder).toHaveBeenCalledWith({
            symbol: 'BTC',
            side: 'buy',
            price: '95000.00',
            amount: '0.01000000',
        });

        resolveOrder(placedOrder());
        await flushPromises();
    });

    it('shows field validation while preserving failed input', async () => {
        placeOrder.mockRejectedValueOnce({
            response: {
                status: 422,
                data: {
                    message: 'The given data was invalid.',
                    errors: {
                        price: ['The price format is invalid.'],
                        amount: ['The amount format is invalid.'],
                    },
                },
            },
        });
        await flushPromises();
        await wrapper.get('#order-price').setValue('1.001');
        await wrapper.get('#order-amount').setValue('0.010000000');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#price-error').text()).toContain(
            'format is invalid',
        );
        expect(wrapper.get('#amount-error').text()).toContain(
            'format is invalid',
        );
        expect(wrapper.get('#order-price').element).toHaveProperty(
            'value',
            '1.001',
        );
        expect(wrapper.get('#order-amount').element).toHaveProperty(
            'value',
            '0.010000000',
        );
    });

    it('refreshes the profile and clears only unchanged submitted inputs', async () => {
        fetchProfile
            .mockResolvedValueOnce(profile())
            .mockResolvedValueOnce(profile('86.50'));
        placeOrder.mockResolvedValueOnce(placedOrder());
        await flushPromises();
        await wrapper.get('#order-price').setValue('90000.00');
        await wrapper.get('#order-amount').setValue('0.01000000');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(fetchProfile).toHaveBeenCalledTimes(2);
        expect(wrapper.get('#order-price').element).toHaveProperty('value', '');
        expect(wrapper.get('#order-amount').element).toHaveProperty(
            'value',
            '',
        );

        await wrapper.get('#order-price').setValue('90000.00');
        await wrapper.get('#order-amount').setValue('0.01000000');
        let resolveOrder: (
            value: ReturnType<typeof placedOrder>,
        ) => void = () => {};
        placeOrder.mockReturnValueOnce(
            new Promise<ReturnType<typeof placedOrder>>((resolve) => {
                resolveOrder = resolve;
            }),
        );
        await wrapper.get('form').trigger('submit');
        await wrapper.get('#order-price').setValue('91000.00');
        resolveOrder(placedOrder());
        await flushPromises();

        expect(wrapper.get('#order-price').element).toHaveProperty(
            'value',
            '91000.00',
        );
        expect(wrapper.get('#order-amount').element).toHaveProperty(
            'value',
            '',
        );
    });

    it('describes a filled order without claiming its limit price was executed', async () => {
        placeOrder.mockResolvedValueOnce(placedOrder(2));
        await flushPromises();

        await wrapper.get('#order-price').setValue('100000.00');
        await wrapper.get('#order-amount').setValue('0.01000000');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(toastSuccess).toHaveBeenCalledWith('Order filled', {
            description: '0.01000000 BTC was filled.',
        });
    });
});
