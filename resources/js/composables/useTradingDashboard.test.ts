import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useTradingDashboard } from './useTradingDashboard';
import type { DashboardData, DashboardResponse } from '@/types/dashboard';

const { fetchDashboard } = vi.hoisted(() => ({
    fetchDashboard: vi.fn(),
}));

vi.mock('@/services/dashboard', () => ({ fetchDashboard }));

function dashboard(
    period: DashboardData['period'],
    value: string,
): DashboardResponse {
    return {
        dashboard: {
            period,
            timezone: 'UTC',
            start_date: '2026-10-20',
            end_date: '2026-10-26',
            summary: {
                available_usd: value,
                reserved_usd: '0.00',
                open_orders: 0,
                executed_trade_value: value,
            },
            daily_executed_value: [],
            order_status_breakdown: [],
            recent_executions: [],
        },
    };
}

describe('useTradingDashboard', () => {
    let state: ReturnType<typeof useTradingDashboard>;

    beforeEach(() => {
        fetchDashboard.mockReset();
        const Host = defineComponent({
            setup() {
                state = useTradingDashboard();

                return () => h('div');
            },
        });

        mount(Host);
    });

    it('keeps the current period visible while refreshing it', async () => {
        fetchDashboard.mockResolvedValueOnce(dashboard(7, '100.00'));
        await state.loadDashboard(7);

        let resolveRefresh: (value: DashboardResponse) => void = () => {};
        fetchDashboard.mockReturnValueOnce(
            new Promise<DashboardResponse>((resolve) => {
                resolveRefresh = resolve;
            }),
        );

        const pending = state.loadDashboard(7);

        expect(state.dashboard.value?.summary.available_usd).toBe('100.00');
        expect(state.refreshing.value).toBe(true);

        resolveRefresh(dashboard(7, '200.00'));
        await pending;

        expect(state.dashboard.value?.summary.available_usd).toBe('200.00');
    });

    it('clears the old period and ignores stale period responses', async () => {
        fetchDashboard.mockResolvedValueOnce(dashboard(7, '100.00'));
        await state.loadDashboard(7);

        let resolveThirty: (value: DashboardResponse) => void = () => {};
        let resolveSeven: (value: DashboardResponse) => void = () => {};
        fetchDashboard.mockReturnValueOnce(
            new Promise<DashboardResponse>((resolve) => {
                resolveThirty = resolve;
            }),
        );
        fetchDashboard.mockReturnValueOnce(
            new Promise<DashboardResponse>((resolve) => {
                resolveSeven = resolve;
            }),
        );

        const thirtyRequest = state.loadDashboard(30);
        expect(state.dashboard.value).toBeNull();
        expect(state.loading.value).toBe(true);

        const sevenRequest = state.loadDashboard(7);
        resolveThirty(dashboard(30, '300.00'));
        resolveSeven(dashboard(7, '700.00'));
        await Promise.all([thirtyRequest, sevenRequest]);

        expect(state.dashboard.value?.period).toBe(7);
        expect(state.dashboard.value?.summary.available_usd).toBe('700.00');
    });

    it('retains previous data and exposes a retryable stale error', async () => {
        fetchDashboard.mockResolvedValueOnce(dashboard(7, '100.00'));
        await state.loadDashboard(7);
        fetchDashboard.mockRejectedValueOnce({
            response: {
                status: 503,
                data: { message: 'Dashboard request failed' },
            },
        });

        await state.loadDashboard(7);

        expect(state.dashboard.value?.summary.available_usd).toBe('100.00');
        expect(state.error.value).toBe('Dashboard request failed');
        expect(state.refreshing.value).toBe(false);
    });
});
