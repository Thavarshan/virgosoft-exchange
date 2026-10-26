import { ref, type Ref } from 'vue';
import { toApiError } from '@/services/api';
import { fetchProfile } from '@/services/trading';
import type { Profile } from '@/types/trading';

/**
 * Own the profile request lifecycle shared by the trading screens.
 */
export function useTradingProfile(): {
    profile: Ref<Profile | null>;
    loading: Ref<boolean>;
    refreshing: Ref<boolean>;
    error: Ref<string | null>;
    loadProfile: () => Promise<void>;
} {
    const profile = ref<Profile | null>(null);
    const loading = ref(true);
    const refreshing = ref(false);
    const error = ref<string | null>(null);
    let requestId = 0;

    async function loadProfile(): Promise<void> {
        const request = ++requestId;
        const hasExistingProfile = profile.value !== null;

        if (hasExistingProfile) {
            refreshing.value = true;
        } else {
            loading.value = true;
        }

        try {
            const response = await fetchProfile();

            if (request === requestId) {
                profile.value = response.user;
                error.value = null;
            }
        } catch (requestError) {
            if (request === requestId) {
                error.value = toApiError(requestError).message;
            }
        } finally {
            if (request === requestId) {
                loading.value = false;
                refreshing.value = false;
            }
        }
    }

    return { profile, loading, refreshing, error, loadProfile };
}
