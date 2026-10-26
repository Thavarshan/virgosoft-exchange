import { apiClient } from './api';
import type {
    OrderHistoryResponse,
    OrderResponse,
    OrderbookResponse,
    PlaceOrderPayload,
    ProfileResponse,
} from '@/types/trading';

export async function fetchProfile(): Promise<ProfileResponse> {
    const response = await apiClient.get<ProfileResponse>('/profile');

    return response.data;
}

export async function fetchOrderbook(
    symbol: PlaceOrderPayload['symbol'],
): Promise<OrderbookResponse> {
    const response = await apiClient.get<OrderbookResponse>('/orders', {
        params: { symbol },
    });

    return response.data;
}

export async function fetchOrderHistory(
    page = 1,
): Promise<OrderHistoryResponse> {
    const response = await apiClient.get<OrderHistoryResponse>(
        '/profile/orders',
        { params: { page } },
    );

    return response.data;
}

export async function placeOrder(
    payload: PlaceOrderPayload,
): Promise<OrderResponse> {
    const response = await apiClient.post<OrderResponse>('/orders', payload);

    return response.data;
}

export async function cancelOrder(orderId: number): Promise<OrderResponse> {
    const response = await apiClient.post<OrderResponse>(
        `/orders/${orderId}/cancel`,
    );

    return response.data;
}
