<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { OrderPreview as Preview } from '@/lib/trading';
import type { AssetSymbol, OrderSide } from '@/types/trading';

defineProps<{
    side: OrderSide;
    symbol: AssetSymbol;
    available: string;
    amount: string;
    preview: Preview | null;
}>();
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4">
        <div class="flex items-center justify-between gap-4 text-sm">
            <span class="text-muted-foreground">Available</span>
            <span class="font-medium tabular-nums">{{ available }}</span>
        </div>
        <div class="mt-3 flex items-center justify-between gap-4 text-sm">
            <span class="text-muted-foreground">
                {{
                    side === 'buy'
                        ? 'Estimated trade value'
                        : 'Estimated proceeds'
                }}
            </span>
            <span class="tabular-nums">{{ preview?.cost ?? '—' }} USD</span>
        </div>
        <div class="mt-2 flex items-center justify-between gap-4 text-sm">
            <span class="flex items-center gap-1 text-muted-foreground">
                {{ side === 'buy' ? 'Buyer commission' : 'Buyer commission' }}
                <Popover>
                    <PopoverTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-6 rounded-full"
                            aria-label="Explain commission"
                        >
                            <CircleHelp class="size-3.5" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent>
                        <p class="font-medium">Commission policy</p>
                        <p class="mt-1 text-muted-foreground">
                            Buyers pay 1.5%, rounded up to the nearest cent. For
                            example, 950.00 USD + 14.25 USD commission = 964.25
                            USD reserved.
                        </p>
                    </PopoverContent>
                </Popover>
            </span>
            <span class="tabular-nums">
                {{ preview?.commission ?? '—' }} USD
                <span
                    v-if="side === 'sell'"
                    class="text-xs text-muted-foreground"
                >
                    (paid by buyer)
                </span>
            </span>
        </div>
        <div
            class="mt-3 flex items-center justify-between gap-4 border-t border-border pt-3 font-medium"
        >
            <span>{{
                side === 'buy' ? 'USD reserved' : `${symbol} reserved`
            }}</span>
            <span class="tabular-nums">
                {{
                    side === 'buy'
                        ? (preview?.reservation ?? '—')
                        : amount || '—'
                }}
                {{ side === 'buy' ? 'USD' : symbol }}
            </span>
        </div>
    </div>
</template>
