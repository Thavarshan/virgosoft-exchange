<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiClient, initializeCsrf, toApiError } from '@/services/api';

const router = useRouter();
const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function login(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        await initializeCsrf();
        await apiClient.post(
            '/login',
            { email: email.value, password: password.value },
            { baseURL: '/' },
        );
        await router.push('/trade');
    } catch (exception) {
        error.value = toApiError(exception).message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section
        class="mx-auto flex min-h-[calc(100vh-1px)] max-w-md items-center px-5 py-12"
    >
        <div
            class="w-full rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="mb-8">
                <p class="eyebrow">Virgosoft Exchange</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">
                    Welcome back
                </h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Sign in to your trading workspace.
                </p>
            </div>
            <form class="grid gap-5" @submit.prevent="login">
                <div>
                    <label for="email" class="field-label">Email</label
                    ><input
                        id="email"
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        required
                        class="field mt-2"
                    />
                </div>
                <div>
                    <label for="password" class="field-label">Password</label
                    ><input
                        id="password"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="field mt-2"
                    />
                </div>
                <p
                    v-if="error"
                    class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
                    role="alert"
                >
                    {{ error }}
                </p>
                <button
                    type="submit"
                    class="button-primary"
                    :disabled="loading"
                >
                    {{ loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </div>
    </section>
</template>
