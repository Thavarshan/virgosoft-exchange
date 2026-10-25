<script setup lang="ts">
import { BarChart3, Moon, Sun, WalletCards } from '@lucide/vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useTheme } from '@/composables/useTheme';
import { apiClient, initializeCsrf } from '@/services/api';

const route = useRoute();
const router = useRouter();
const { isDark, toggleTheme } = useTheme();

async function logout(): Promise<void> {
    await initializeCsrf();
    await apiClient.post('/logout', undefined, { baseURL: '/' });
    await router.push('/login');
}
</script>

<template>
    <div
        class="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50"
    >
        <header
            v-if="route.path !== '/login'"
            class="border-b border-slate-200/80 bg-white/90 dark:border-slate-800 dark:bg-slate-950/90"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8"
            >
                <RouterLink
                    to="/trade"
                    class="flex items-center gap-3 font-semibold tracking-tight"
                    ><span
                        class="grid size-9 place-items-center rounded-xl bg-emerald-500 text-white"
                        ><BarChart3 :size="19" /></span
                    ><span
                        >Virgosoft
                        <span class="text-emerald-500">Exchange</span></span
                    ></RouterLink
                >
                <nav
                    class="hidden items-center gap-1 text-sm font-medium md:flex"
                >
                    <RouterLink
                        to="/trade"
                        class="rounded-lg px-3 py-2"
                        :class="
                            route.path === '/trade'
                                ? 'bg-slate-100 dark:bg-slate-800'
                                : 'text-slate-500 hover:text-slate-950 dark:hover:text-white'
                        "
                        >Trade</RouterLink
                    ><RouterLink
                        to="/wallet"
                        class="rounded-lg px-3 py-2"
                        :class="
                            route.path === '/wallet'
                                ? 'bg-slate-100 dark:bg-slate-800'
                                : 'text-slate-500 hover:text-slate-950 dark:hover:text-white'
                        "
                        ><span class="flex items-center gap-2"
                            ><WalletCards :size="16" /> Wallet & Orders</span
                        ></RouterLink
                    >
                </nav>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Toggle theme"
                        @click="toggleTheme"
                    >
                        <Sun v-if="isDark" :size="18" /><Moon
                            v-else
                            :size="18"
                        /></button
                    ><button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300"
                        @click="logout"
                    >
                        Log out
                    </button>
                </div>
            </div>
        </header>
        <main><RouterView /></main>
    </div>
</template>
