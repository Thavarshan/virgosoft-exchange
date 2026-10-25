<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PasswordInput from '@/components/PasswordInput.vue';
import { Spinner } from '@/components/ui/spinner';
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
        class="flex min-h-svh items-center justify-center bg-muted p-6 md:p-10"
    >
        <Card class="w-full max-w-md">
            <CardHeader class="text-center">
                <p class="text-sm font-semibold tracking-wide text-primary">
                    Virgosoft Exchange
                </p>
                <CardTitle class="text-2xl">Welcome back</CardTitle>
                <CardDescription>
                    Sign in to your trading workspace.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-5" @submit.prevent="login">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="email"
                            type="email"
                            autocomplete="email"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            v-model="password"
                            autocomplete="current-password"
                            required
                        />
                    </div>
                    <p
                        v-if="error"
                        class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                        role="alert"
                    >
                        {{ error }}
                    </p>
                    <Button type="submit" class="w-full" :disabled="loading">
                        <Spinner v-if="loading" />
                        {{ loading ? 'Signing in…' : 'Sign in' }}
                    </Button>
                    <p class="text-center text-sm text-muted-foreground">
                        Don’t have an account?
                        <RouterLink
                            to="/register"
                            class="font-medium text-foreground underline underline-offset-4"
                        >
                            Create one
                        </RouterLink>
                    </p>
                </form>
            </CardContent>
        </Card>
    </section>
</template>
