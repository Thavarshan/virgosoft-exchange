<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConnectionBadge from '@/components/trading/ConnectionBadge.vue';
import LimitOrderForm from '@/components/trading/LimitOrderForm.vue';
import PageHeader from '@/components/PageHeader.vue';
import TradingGuideDialog from '@/components/trading/TradingGuideDialog.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTradingProfile } from '@/composables/useTradingProfile';
import { useTradingRealtime } from '@/composables/useTradingRealtime';
import { calculateOrderPreview, formatMatchNotification } from '@/lib/trading';
import { toApiError } from '@/services/api';
import { placeOrder } from '@/services/trading';
import { trade } from '@/routes';
import type { Auth } from '@/types/auth';
import {
    TRADING_MARKETS,
    type AssetSymbol,
    type OrderSide,
} from '@/types/trading';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Limit Order', href: trade() }] },
});

const page = usePage<{ auth: Auth }>();
const userId = page.props.auth.user.id;
const profile = useTradingProfile();
const submitting = ref(false);
const pageError = ref<string | null>(null);
const validationErrors = ref<Record<string, string[]>>({});
const symbol = ref<AssetSymbol>('BTC');
const side = ref<OrderSide>('buy');
const price = ref('');
const amount = ref('');

const preview = computed(() =>
    calculateOrderPreview(price.value, amount.value),
);
const available = computed(() => {
    if (!profile.profile.value) {
        return 'Unavailable';
    }
    if (side.value === 'buy') {
        return `${profile.profile.value.balance} USD`;
    }
    return `${profile.profile.value.assets.find((asset) => asset.symbol === symbol.value)?.amount ?? '0.00000000'} ${symbol.value}`;
});

const { connectionStatus } = useTradingRealtime(
    userId,
    (payload) => {
        void profile.loadProfile();
        toast.success('Order matched', {
            description: formatMatchNotification(payload),
        });
    },
    () => {
        void profile.loadProfile();
    },
);

function updateSymbol(value: AssetSymbol): void {
    symbol.value = value;
}

function updateSide(value: OrderSide): void {
    side.value = value;
}

async function submitOrder(): Promise<void> {
    if (submitting.value) {
        return;
    }

    const submittedPrice = price.value;
    const submittedAmount = amount.value;
    submitting.value = true;
    validationErrors.value = {};
    pageError.value = null;

    try {
        const response = await placeOrder({
            symbol: symbol.value,
            side: side.value,
            price: submittedPrice,
            amount: submittedAmount,
        });

        toast.success(
            response.order.status === 2 ? 'Order filled' : 'Order placed',
            {
                description:
                    response.order.status === 2
                        ? `${response.order.amount} ${response.order.symbol} was filled.`
                        : 'Your order is open in the orderbook.',
            },
        );
        await profile.loadProfile();

        if (price.value === submittedPrice) {
            price.value = '';
        }

        if (amount.value === submittedAmount) {
            amount.value = '';
        }
    } catch (error) {
        const apiError = toApiError(error);
        validationErrors.value = apiError.validation;
        pageError.value = apiError.message;
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    void profile.loadProfile();
});
</script>

<template>
    <Head title="Limit Order" />
    <div class="flex flex-1 flex-col p-4 md:p-6">
        <PageHeader
            title="Limit Order"
            description="Place a full-match limit order with exact USD and asset precision."
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <TradingGuideDialog />
                    <ConnectionBadge :status="connectionStatus" />
                </div>
            </template>
        </PageHeader>

        <Alert
            v-if="pageError || profile.error.value"
            variant="destructive"
            class="mb-6"
        >
            <AlertTitle>Unable to place or load your order</AlertTitle>
            <AlertDescription>{{
                pageError ?? profile.error.value
            }}</AlertDescription>
        </Alert>

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)]"
        >
            <Card>
                <CardHeader class="gap-0">
                    <CardTitle class="mb-0.5 text-base leading-6 font-medium"
                        >New limit order</CardTitle
                    >
                    <CardDescription
                        >Orders reserve funds immediately. Commission is 1.5%
                        and is charged to the buyer.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <LimitOrderForm
                        :markets="TRADING_MARKETS"
                        :symbol="symbol"
                        :side="side"
                        :price="price"
                        :amount="amount"
                        :available="available"
                        :preview="preview"
                        :submitting="submitting"
                        :validation-errors="validationErrors"
                        @update:symbol="updateSymbol"
                        @update:side="updateSide"
                        @update:price="price = $event"
                        @update:amount="amount = $event"
                        @submit="submitOrder"
                    />
                </CardContent>
            </Card>

            <Card class="h-fit">
                <CardHeader class="gap-0">
                    <CardTitle class="mb-0.5 text-base leading-6 font-medium"
                        >How it works</CardTitle
                    >
                    <CardDescription
                        >Simple, predictable execution rules.</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4 text-sm text-muted-foreground">
                    <p>
                        Only equal-sized orders match. The oldest compatible
                        counter-order is selected.
                    </p>
                    <p>
                        Trades execute at the existing counter-order’s price, so
                        a match can return a price improvement.
                    </p>
                    <p>
                        Buyers reserve the rounded-up trade value plus the
                        rounded-up 1.5% commission.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
