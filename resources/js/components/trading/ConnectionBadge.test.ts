import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ConnectionBadge from './ConnectionBadge.vue';

describe('ConnectionBadge', () => {
    it('uses polling-only wording when the wallet fallback is active', () => {
        const wrapper = mount(ConnectionBadge, {
            props: { status: 'unconfigured', pollingActive: true },
        });

        expect(wrapper.text()).toBe('Polling only');
    });

    it('does not imply polling on screens without a polling fallback', () => {
        const wrapper = mount(ConnectionBadge, {
            props: { status: 'unconfigured' },
        });

        expect(wrapper.text()).toBe('Live updates unavailable');
    });
});
