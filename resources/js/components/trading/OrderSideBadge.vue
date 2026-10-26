<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { OrderSide } from '@/types/trading';

defineProps<{
    side: OrderSide;
}>();
</script>

<template>
    <Badge
        variant="outline"
        :class="
            side === 'buy'
                ? 'border-buy/40 bg-buy/10 text-buy-foreground'
                : 'border-sell/40 bg-sell/10 text-sell-foreground'
        "
    >
        {{ side === 'buy' ? 'Buy' : 'Sell' }}
    </Badge>
</template>
