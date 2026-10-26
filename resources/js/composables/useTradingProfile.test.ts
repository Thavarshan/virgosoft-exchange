import { defineComponent, h } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTradingProfile } from './useTradingProfile';
import type { ProfileResponse } from '@/types/trading';

const { fetchProfile } = vi.hoisted(() => ({
    fetchProfile: vi.fn(),
}));

vi.mock('@/services/trading', () => ({ fetchProfile }));

function profile(balance: string): ProfileResponse {
    return {
        user: {
            balance,
            locked_balance: '0.00',
            assets: [],
        },
    };
}

describe('useTradingProfile', () => {
    let state: ReturnType<typeof useTradingProfile>;
    let wrapper: VueWrapper;

    beforeEach(() => {
        fetchProfile.mockReset();
        const Host = defineComponent({
            setup() {
                state = useTradingProfile();

                return () => h('div');
            },
        });

        wrapper = mount(Host);
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('exposes an initial failure and recovers on retry', async () => {
        fetchProfile.mockRejectedValueOnce({
            response: { status: 503, data: { message: 'Profile unavailable' } },
        });

        await state.loadProfile();

        expect(state.profile.value).toBeNull();
        expect(state.error.value).toBe('Profile unavailable');
        expect(state.loading.value).toBe(false);

        fetchProfile.mockResolvedValueOnce(profile('1000.00'));
        await state.loadProfile();

        expect(state.profile.value?.balance).toBe('1000.00');
        expect(state.error.value).toBeNull();
    });

    it('retains the last profile after a background refresh fails', async () => {
        fetchProfile.mockResolvedValueOnce(profile('1000.00'));
        await state.loadProfile();
        fetchProfile.mockRejectedValueOnce({
            response: { status: 503, data: { message: 'Refresh failed' } },
        });

        await state.loadProfile();

        expect(state.profile.value?.balance).toBe('1000.00');
        expect(state.error.value).toBe('Refresh failed');
        expect(state.refreshing.value).toBe(false);
    });

    it('ignores an older response after a newer profile request completes', async () => {
        let resolveOld: (value: ProfileResponse) => void = () => {};
        let resolveNew: (value: ProfileResponse) => void = () => {};
        fetchProfile.mockReturnValueOnce(
            new Promise<ProfileResponse>((resolve) => {
                resolveOld = resolve;
            }),
        );
        fetchProfile.mockReturnValueOnce(
            new Promise<ProfileResponse>((resolve) => {
                resolveNew = resolve;
            }),
        );

        const oldRequest = state.loadProfile();
        const newRequest = state.loadProfile();
        resolveNew(profile('900.00'));
        await newRequest;
        resolveOld(profile('1000.00'));
        await oldRequest;

        expect(state.profile.value?.balance).toBe('900.00');
    });
});
