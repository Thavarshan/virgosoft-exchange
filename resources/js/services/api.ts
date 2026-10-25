import axios, { type AxiosError } from 'axios';

export interface SessionUser { id: number; name: string; email: string }
export interface SessionResponse { user: SessionUser }
export interface ApiError { message: string; validation: Record<string, string[]>; status?: number }

export const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    withCredentials: true,
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

export async function initializeCsrf(): Promise<void> {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export function toApiError(error: unknown): ApiError {
    const response = (error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>).response;
    return { message: response?.data?.message || 'Something went wrong. Please try again.', validation: response?.data?.errors || {}, status: response?.status };
}

export const api = {
    async session(): Promise<SessionResponse> {
        const response = await apiClient.get<SessionResponse>('/session');
        return response.data;
    },
};
