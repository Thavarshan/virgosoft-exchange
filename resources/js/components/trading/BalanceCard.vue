<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { AssetSymbol } from '@/types/trading';

defineProps<{
    label: string;
    symbol: 'USD' | AssetSymbol;
    available: string;
    locked: string;
    loading: boolean;
    refreshing: boolean;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-border bg-card p-4 transition-opacity"
        :class="refreshing ? 'opacity-80' : ''"
    >
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            class="text-muted-foreground"
                            :aria-label="`Explain ${symbol} balances`"
                        >
                            <CircleHelp class="size-3.5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        Available funds can be used for new orders. Locked funds
                        are reserved by open orders.
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
        <template v-if="loading">
            <div class="mt-3 h-7 w-28 animate-pulse rounded bg-muted" />
            <div class="mt-2 h-4 w-24 animate-pulse rounded bg-muted" />
        </template>
        <template v-else>
            <p class="mt-2 text-xl font-semibold tabular-nums">
                {{ available }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                Available {{ symbol }} · Locked {{ locked }}
            </p>
        </template>
    </div>
</template>
