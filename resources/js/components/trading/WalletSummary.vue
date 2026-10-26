<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import BalanceCard from '@/components/trading/BalanceCard.vue';
import type { AssetSymbol, Profile } from '@/types/trading';

withDefaults(
    defineProps<{
        profile: Profile | null;
        loading: boolean;
        refreshing?: boolean;
        error: string | null;
    }>(),
    { refreshing: false },
);

const emit = defineEmits<{
    retry: [];
}>();

const assetSymbols: AssetSymbol[] = ['BTC', 'ETH'];

function assetFor(profile: Profile | null, symbol: AssetSymbol) {
    return profile?.assets.find((asset) => asset.symbol === symbol);
}
</script>

<template>
    <Card>
        <CardHeader class="gap-0">
            <CardTitle class="mb-0.5 text-base leading-6 font-medium">
                Wallet
            </CardTitle>
        </CardHeader>
        <CardContent
            v-if="!loading && !profile"
            class="flex min-h-36 flex-col items-start justify-center gap-3"
        >
            <div>
                <p class="font-medium">Wallet unavailable</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ error ?? 'We could not load your balances.' }}
                </p>
            </div>
            <Button variant="outline" @click="emit('retry')">Retry</Button>
        </CardContent>

        <CardContent v-else>
            <Alert v-if="error" class="mb-4">
                <AlertDescription
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <span>
                        Showing your last known balances. Refresh failed:
                        {{ error }}
                    </span>
                    <Button size="sm" variant="outline" @click="emit('retry')">
                        Retry
                    </Button>
                </AlertDescription>
            </Alert>

            <div class="grid gap-3 sm:grid-cols-3">
                <BalanceCard
                    label="USD"
                    symbol="USD"
                    :available="profile?.balance ?? ''"
                    :locked="profile?.locked_balance ?? ''"
                    :loading="loading"
                    :refreshing="refreshing"
                />
                <BalanceCard
                    v-for="symbol in assetSymbols"
                    :key="symbol"
                    :label="symbol"
                    :symbol="symbol"
                    :available="
                        assetFor(profile, symbol)?.amount ?? '0.00000000'
                    "
                    :locked="
                        assetFor(profile, symbol)?.locked_amount ?? '0.00000000'
                    "
                    :loading="loading"
                    :refreshing="refreshing"
                />
            </div>
        </CardContent>
    </Card>
</template>
