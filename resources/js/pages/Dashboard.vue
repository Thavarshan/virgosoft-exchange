<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import ConnectionBadge from '@/components/trading/ConnectionBadge.vue';
import DashboardActivityChart from '@/components/dashboard/DashboardActivityChart.vue';
import DashboardOrderBreakdown from '@/components/dashboard/DashboardOrderBreakdown.vue';
import DashboardRecentExecutions from '@/components/dashboard/DashboardRecentExecutions.vue';
import DashboardSummaryCards from '@/components/dashboard/DashboardSummaryCards.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTradingDashboard } from '@/composables/useTradingDashboard';
import { useTradingRealtime } from '@/composables/useTradingRealtime';
import { dashboard, trade, wallet } from '@/routes';
import type { Auth } from '@/types/auth';
import type { DashboardPeriod } from '@/types/dashboard';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage<{ auth: Auth }>();
const userId = page.props.auth.user.id;
const selectedPeriod = ref<DashboardPeriod>(7);
const dashboardState = useTradingDashboard();

const { connectionStatus } = useTradingRealtime(
    userId,
    () => {
        void dashboardState.loadDashboard(selectedPeriod.value);
    },
    () => {
        void dashboardState.loadDashboard(selectedPeriod.value);
    },
);

function selectPeriod(period: DashboardPeriod): void {
    selectedPeriod.value = period;
    void dashboardState.loadDashboard(period);
}

function retry(): void {
    void dashboardState.loadDashboard(selectedPeriod.value);
}

onMounted(() => {
    void dashboardState.loadDashboard(selectedPeriod.value);
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col p-4 md:p-6">
        <PageHeader
            title="Dashboard"
            description="A focused view of your balances, order activity, and completed executions."
        >
            <template #actions>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Button variant="outline" as-child class="h-10">
                        <Link :href="trade()">Place order</Link>
                    </Button>
                    <Button variant="outline" as-child class="h-10">
                        <Link :href="wallet()">View wallet</Link>
                    </Button>
                    <ConnectionBadge :status="connectionStatus" />
                </div>
            </template>
        </PageHeader>

        <Alert
            v-if="dashboardState.error.value"
            :variant="dashboardState.dashboard.value ? 'default' : 'destructive'"
            class="mb-6"
        >
            <AlertTitle>
                {{ dashboardState.dashboard.value ? 'Dashboard data may be stale' : 'Dashboard unavailable' }}
            </AlertTitle>
            <AlertDescription class="flex flex-wrap items-center justify-between gap-3">
                <span>
                    {{
                        dashboardState.dashboard.value
                            ? 'Showing the last successful dashboard response. '
                            : ''
                    }}
                    {{ dashboardState.error.value }}
                </span>
                <Button variant="outline" size="sm" @click="retry">Retry</Button>
            </AlertDescription>
        </Alert>

        <DashboardSummaryCards
            :summary="dashboardState.dashboard.value?.summary ?? null"
            :loading="dashboardState.loading.value"
            :refreshing="dashboardState.refreshing.value"
        />

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.55fr)]">
            <DashboardActivityChart
                :values="dashboardState.dashboard.value?.daily_executed_value ?? []"
                :period="selectedPeriod"
                :loading="dashboardState.loading.value"
                :refreshing="dashboardState.refreshing.value"
                @update:period="selectPeriod"
            />
            <DashboardOrderBreakdown
                :statuses="dashboardState.dashboard.value?.order_status_breakdown ?? []"
                :loading="dashboardState.loading.value"
            />
        </div>

        <div class="mt-6">
            <DashboardRecentExecutions
                :executions="dashboardState.dashboard.value?.recent_executions ?? []"
                :loading="dashboardState.loading.value"
            />
        </div>
    </div>
</template>
