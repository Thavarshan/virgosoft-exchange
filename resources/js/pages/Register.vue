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
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const error = ref('');
const loading = ref(false);

async function register(): Promise<void> {
    loading.value = true;
    error.value = '';

    try {
        await initializeCsrf();
        await apiClient.post(
            '/register',
            {
                name: name.value,
                email: email.value,
                password: password.value,
                password_confirmation: passwordConfirmation.value,
            },
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
                <CardTitle class="text-2xl">Create an account</CardTitle>
                <CardDescription>
                    Set up your trading workspace in a few seconds.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-5" @submit.prevent="register">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="name"
                            autocomplete="name"
                            required
                        />
                    </div>
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
                            autocomplete="new-password"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Confirm password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            v-model="passwordConfirmation"
                            autocomplete="new-password"
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
                        {{ loading ? 'Creating account…' : 'Create account' }}
                    </Button>
                    <p class="text-center text-sm text-muted-foreground">
                        Already have an account?
                        <RouterLink
                            to="/login"
                            class="font-medium text-foreground underline underline-offset-4"
                        >
                            Sign in
                        </RouterLink>
                    </p>
                </form>
            </CardContent>
        </Card>
    </section>
</template>
