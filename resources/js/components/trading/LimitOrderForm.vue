<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import MarketSelect from '@/components/trading/MarketSelect.vue';
import OrderPreview from '@/components/trading/OrderPreview.vue';
import type { OrderPreview as Preview } from '@/lib/trading';
import type { AssetSymbol, OrderSide, TradingMarket } from '@/types/trading';

const props = defineProps<{
    markets: readonly TradingMarket[];
    symbol: AssetSymbol;
    side: OrderSide;
    price: string;
    amount: string;
    available: string;
    preview: Preview | null;
    submitting: boolean;
    validationErrors: Record<string, string[]>;
}>();

const emit = defineEmits<{
    'update:symbol': [value: AssetSymbol];
    'update:side': [value: OrderSide];
    'update:price': [value: string];
    'update:amount': [value: string];
    submit: [];
}>();

function errorFor(field: string): string | undefined {
    return props.validationErrors[field]?.[0];
}

function updateSide(value: unknown): void {
    if (value === 'buy' || value === 'sell') {
        emit('update:side', value);
    }
}

function describedBy(hintId: string, field: string): string | undefined {
    return (
        [hintId, errorFor(field) ? field + '-error' : null]
            .filter(Boolean)
            .join(' ') || undefined
    );
}

const priceHint = computed(() =>
    props.side === 'buy'
        ? 'Maximum USD price you will pay per unit.'
        : 'Minimum USD price you will accept per unit.',
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="order-symbol">Market</Label>
                <MarketSelect
                    id="order-symbol"
                    :model-value="symbol"
                    :markets="markets"
                    aria-label="Order market"
                    :described-by="
                        errorFor('symbol') ? 'order-symbol-error' : undefined
                    "
                    @update:model-value="emit('update:symbol', $event)"
                />
                <p
                    v-if="errorFor('symbol')"
                    id="order-symbol-error"
                    class="text-sm text-destructive"
                >
                    {{ errorFor('symbol') }}
                </p>
            </div>
            <div class="space-y-2">
                <Label>Side</Label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="lg"
                    :spacing="2"
                    :model-value="side"
                    aria-label="Order side"
                    :aria-describedby="
                        errorFor('side') ? 'order-side-error' : undefined
                    "
                    class="grid w-full grid-cols-2"
                    @update:model-value="updateSide"
                >
                    <ToggleGroupItem
                        value="buy"
                        class="h-10 w-full text-sm data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                    >
                        Buy {{ symbol }}
                    </ToggleGroupItem>
                    <ToggleGroupItem
                        value="sell"
                        class="h-10 w-full text-sm data-[state=on]:bg-primary data-[state=on]:text-primary-foreground"
                    >
                        Sell {{ symbol }}
                    </ToggleGroupItem>
                </ToggleGroup>
                <p
                    v-if="errorFor('side')"
                    id="order-side-error"
                    class="text-sm text-destructive"
                >
                    {{ errorFor('side') }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="order-price">Limit price (USD)</Label>
                <Input
                    id="order-price"
                    :model-value="price"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="95000.00"
                    :aria-invalid="Boolean(errorFor('price'))"
                    :aria-describedby="describedBy('order-price-hint', 'price')"
                    @update:model-value="emit('update:price', String($event))"
                />
                <p id="order-price-hint" class="text-xs text-muted-foreground">
                    {{ priceHint }}
                </p>
                <p
                    v-if="errorFor('price')"
                    id="price-error"
                    class="text-sm text-destructive"
                >
                    {{ errorFor('price') }}
                </p>
            </div>
            <div class="space-y-2">
                <Label for="order-amount">Amount ({{ symbol }})</Label>
                <Input
                    id="order-amount"
                    :model-value="amount"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="0.01000000"
                    :aria-invalid="Boolean(errorFor('amount'))"
                    :aria-describedby="
                        describedBy('order-amount-hint', 'amount')
                    "
                    @update:model-value="emit('update:amount', String($event))"
                />
                <p id="order-amount-hint" class="text-xs text-muted-foreground">
                    Up to 8 decimal places. Full matches require equal
                    quantities.
                </p>
                <p
                    v-if="errorFor('amount')"
                    id="amount-error"
                    class="text-sm text-destructive"
                >
                    {{ errorFor('amount') }}
                </p>
            </div>
        </div>

        <OrderPreview
            :side="side"
            :symbol="symbol"
            :available="available"
            :amount="amount"
            :preview="preview"
        />

        <Button type="submit" class="h-10 w-full" :disabled="submitting">
            <Spinner v-if="submitting" class="mr-2" />
            {{ submitting ? 'Submitting…' : `Place ${side} order` }}
        </Button>
    </form>
</template>
