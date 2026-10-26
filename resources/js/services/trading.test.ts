import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from './api';
import { cancelOrder, placeOrder } from './trading';

describe('trading API service', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('sends order values as decimal strings', async () => {
        const request = vi.spyOn(apiClient, 'post').mockResolvedValue({
            data: { order: { id: 1 } },
        } as never);

        await placeOrder({
            symbol: 'BTC',
            side: 'buy',
            price: '95000.00',
            amount: '0.01000000',
        });

        expect(request).toHaveBeenCalledWith('/orders', {
            symbol: 'BTC',
            side: 'buy',
            price: '95000.00',
            amount: '0.01000000',
        });
    });

    it('posts cancellation to the order endpoint', async () => {
        const request = vi.spyOn(apiClient, 'post').mockResolvedValue({
            data: { order: { id: 42, status: 3 } },
        } as never);

        await cancelOrder(42);

        expect(request).toHaveBeenCalledWith('/orders/42/cancel');
    });
});
