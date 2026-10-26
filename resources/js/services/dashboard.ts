import { apiClient } from './api';
import type { DashboardPeriod, DashboardResponse } from '@/types/dashboard';

export async function fetchDashboard(
    period: DashboardPeriod,
): Promise<DashboardResponse> {
    const response = await apiClient.get<DashboardResponse>('/dashboard', {
        params: { period },
    });

    return response.data;
}
