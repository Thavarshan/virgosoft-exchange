<template>
    <section class="mx-auto max-w-7xl px-5 py-8 lg:px-8 lg:py-12">
        <div class="mb-8">
            <p class="eyebrow">Trading workspace</p>
            <h1 class="page-title">Limit order</h1>
            <p class="page-subtitle">
                Place an order when the market reaches your chosen price.
            </p>
        </div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="panel">
                <div
                    class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-slate-800"
                >
                    <div>
                        <h2 class="panel-title">Create order</h2>
                        <p class="panel-copy">
                            Trading actions are not connected yet.
                        </p>
                    </div>
                    <span class="status-badge">Coming soon</span>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <label class="field-label sm:col-span-2"
                        >Market<select class="field mt-2" disabled>
                            <option>BTC / USD</option>
                            <option>ETH / USD</option>
                        </select></label
                    >
                    <div class="flex gap-2 sm:col-span-2">
                        <button class="button-secondary flex-1" disabled>
                            Buy</button
                        ><button class="button-ghost flex-1" disabled>
                            Sell
                        </button>
                    </div>
                    <label class="field-label"
                        >Limit price<input
                            class="field mt-2"
                            placeholder="—"
                            disabled /></label
                    ><label class="field-label"
                        >Amount<input
                            class="field mt-2"
                            placeholder="—"
                            disabled
                    /></label>
                </div>
                <div
                    class="border-t border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950/50"
                >
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Estimated cost</span
                        ><span class="font-medium">—</span>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-slate-500">Commission</span
                        ><span class="font-medium">—</span>
                    </div>
                    <button class="button-primary mt-5 w-full" disabled>
                        Submit limit order
                    </button>
                </div>
            </div>
            <aside class="panel h-fit p-5">
                <p class="eyebrow">Market context</p>
                <h2 class="panel-title mt-2">No live market data</h2>
                <p class="panel-copy mt-2">
                    Orderbook and recent trade feeds will be connected as the
                    assessment features are implemented.
                </p>
                <div class="mt-6 grid gap-3">
                    <div class="skeleton-line w-2/3"></div>
                    <div class="skeleton-line w-full"></div>
                    <div class="skeleton-line w-5/6"></div>
                </div>
            </aside>
        </div>
    </section>
</template>
