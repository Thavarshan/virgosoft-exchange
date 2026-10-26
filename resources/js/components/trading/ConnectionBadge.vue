<script setup lang="ts">
import { computed } from 'vue';
import { CircleHelp } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type { TradingConnectionStatus } from '@/composables/useTradingRealtime';

const props = withDefaults(
    defineProps<{
        status: TradingConnectionStatus;
        pollingActive?: boolean;
    }>(),
    {
        pollingActive: false,
    },
);

const statusLabel = computed(() => {
    switch (props.status) {
        case 'connected':
            return 'Live';
        case 'reconnecting':
            return 'Reconnecting';
        case 'connecting':
            return 'Connecting';
        case 'unconfigured':
            return props.pollingActive
                ? 'Polling only'
                : 'Live updates unavailable';
        default:
            return 'Offline';
    }
});

const statusVariant = computed(() => {
    if (props.status === 'disconnected') {
        return 'destructive';
    }

    return props.status === 'connected' ? 'secondary' : 'outline';
});

const statusIndicatorClass = computed(() => {
    if (props.status === 'connected') {
        return 'bg-status-filled';
    }

    if (props.status === 'connecting' || props.status === 'reconnecting') {
        return 'bg-status-open';
    }

    if (props.status === 'disconnected') {
        return 'bg-destructive';
    }

    return 'bg-muted-foreground';
});

const statusDescription = computed(() => {
    if (props.status === 'unconfigured') {
        return props.pollingActive
            ? 'Live updates are not configured; visible orderbook polling remains active.'
            : 'Live updates are not configured; refresh to sync changes.';
    }

    if (props.status === 'disconnected') {
        return props.pollingActive
            ? 'The live connection is unavailable; visible orderbook polling remains active.'
            : 'The live connection is unavailable; refresh to sync changes.';
    }

    return `Realtime updates: ${statusLabel.value}`;
});
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="h-10 gap-1.5 px-2"
                aria-label="Explain connection status"
            >
                <Badge
                    :variant="statusVariant"
                    class="gap-1.5"
                    aria-live="polite"
                    :aria-label="statusDescription"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :class="statusIndicatorClass"
                        aria-hidden="true"
                    />
                    {{ statusLabel }}
                </Badge>
                <CircleHelp
                    class="size-3.5 text-muted-foreground"
                    aria-hidden="true"
                />
            </Button>
        </PopoverTrigger>
        <PopoverContent align="end">
            <p class="font-medium">{{ statusLabel }}</p>
            <p class="mt-1 text-muted-foreground">{{ statusDescription }}</p>
            <p class="mt-2 text-xs text-muted-foreground">
                Wallet orderbooks refresh while this tab is visible. Personal
                match notifications use the live private channel.
            </p>
        </PopoverContent>
    </Popover>
</template>
