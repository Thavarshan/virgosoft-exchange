<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" class="h-10">Trading guide</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>How matching works</DialogTitle>
                <DialogDescription>
                    These rules keep this assessment’s full-match engine
                    predictable.
                </DialogDescription>
            </DialogHeader>
            <ul class="space-y-3 text-sm text-muted-foreground">
                <li>
                    Orders match only when the quantities are exactly equal.
                </li>
                <li>The oldest compatible counter-order is selected first.</li>
                <li>Execution uses the existing counter-order’s price.</li>
                <li>
                    Funds and assets are reserved immediately and released on
                    cancellation.
                </li>
                <li>Buyers pay a 1.5% USD commission, rounded up to cents.</li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
