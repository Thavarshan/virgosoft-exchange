<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverClose,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

type DemoAccount = {
    name: string;
    email: string;
    password: string;
};

withDefaults(
    defineProps<{
        status?: string;
        canResetPassword: boolean;
        demoAccounts?: DemoAccount[];
    }>(),
    { demoAccounts: () => [] },
);

function setField(id: string, value: string): void {
    const field = document.getElementById(id);

    if (field instanceof HTMLInputElement) {
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function fillCredentials(account: DemoAccount): void {
    setField('email', account.email);
    setField('password', account.password);
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Alert v-if="demoAccounts.length > 0" data-test="demo-accounts-notice">
        <Info />
        <AlertTitle>Reviewing this demo?</AlertTitle>
        <AlertDescription>
            <p>
                {{ demoAccounts.length }} pre-funded demo accounts are ready,
                so you can try trading right away.
            </p>
            <Popover>
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-2"
                        data-test="demo-accounts-trigger"
                    >
                        Show demo credentials
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    align="start"
                    class="w-80"
                    data-test="demo-accounts-popover"
                >
                    <p class="font-medium">Demo accounts</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Sign in as both in separate browser profiles to place
                        a buy and a sell that match each other.
                    </p>
                    <ul class="mt-3 space-y-3">
                        <li
                            v-for="account in demoAccounts"
                            :key="account.email"
                            class="rounded-md border border-border p-3"
                        >
                            <p class="font-medium">{{ account.name }}</p>
                            <dl class="mt-1 space-y-0.5 text-xs">
                                <div class="flex gap-2">
                                    <dt class="w-16 text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd class="font-mono break-all">
                                        {{ account.email }}
                                    </dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="w-16 text-muted-foreground">
                                        Password
                                    </dt>
                                    <dd class="font-mono">
                                        {{ account.password }}
                                    </dd>
                                </div>
                            </dl>
                            <PopoverClose as-child>
                                <Button
                                    type="button"
                                    size="sm"
                                    class="mt-3 w-full"
                                    :data-test="`demo-fill-${account.email}`"
                                    @click="fillCredentials(account)"
                                >
                                    Fill in these credentials
                                </Button>
                            </PopoverClose>
                        </li>
                    </ul>
                </PopoverContent>
            </Popover>
        </AlertDescription>
    </Alert>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>
</template>
