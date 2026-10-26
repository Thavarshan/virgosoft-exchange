import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WalletSummary from './WalletSummary.vue';

describe('WalletSummary', () => {
    it('shows unavailable state and emits retry after a failed load', async () => {
        const wrapper = mount(WalletSummary, {
            props: {
                profile: null,
                loading: false,
                error: 'Profile request failed',
            },
        });

        expect(wrapper.text()).toContain('Wallet unavailable');
        expect(wrapper.text()).toContain('Profile request failed');

        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('retry')).toEqual([[]]);
    });

    it('labels loaded balances as available and locked', () => {
        const wrapper = mount(WalletSummary, {
            props: {
                profile: {
                    balance: '1000.00',
                    locked_balance: '200.00',
                    assets: [
                        {
                            symbol: 'BTC',
                            amount: '1.00000000',
                            locked_amount: '0.25000000',
                        },
                    ],
                },
                loading: false,
                error: null,
            },
        });

        expect(wrapper.text()).toContain('Available USD');
        expect(wrapper.text()).toContain('Locked 200.00');
        expect(wrapper.text()).toContain('Available BTC');
        expect(wrapper.text()).toContain('Locked 0.25000000');
    });

    it('keeps showing balances with a stale-data warning after refresh fails', async () => {
        const wrapper = mount(WalletSummary, {
            props: {
                profile: {
                    balance: '1000.00',
                    locked_balance: '0.00',
                    assets: [],
                },
                loading: false,
                error: 'Profile request failed',
            },
        });

        expect(wrapper.text()).toContain('Showing your last known balances');
        expect(wrapper.text()).toContain('1000.00');

        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('retry')).toEqual([[]]);
    });
});
