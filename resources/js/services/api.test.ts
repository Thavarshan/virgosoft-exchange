import { describe, expect, it, vi } from 'vitest';
import { apiClient, toApiError } from './api';
import { pusherAuthorizer } from './echo-authorizer';
import { csrfHeaders } from './csrf';

describe('API error normalization', () => {
    it('preserves Laravel validation errors', () => {
        const error = toApiError({
            response: {
                status: 422,
                data: {
                    message: 'Invalid input',
                    errors: { email: ['Invalid email'] },
                },
            },
        });

        expect(error).toEqual({
            message: 'Invalid input',
            validation: { email: ['Invalid email'] },
            status: 422,
        });
    });
});

describe('broadcast authentication headers', () => {
    it('includes the Laravel CSRF token from the document', () => {
        document.head.innerHTML =
            '<meta name="csrf-token" content="test-csrf-token">';

        expect(csrfHeaders()).toEqual({
            'X-CSRF-TOKEN': 'test-csrf-token',
            'X-Requested-With': 'XMLHttpRequest',
        });
    });

    it('reads the current XSRF cookie for every channel authorization', async () => {
        const post = vi.spyOn(apiClient, 'post').mockResolvedValue({
            data: { auth: 'signed-channel' },
        } as never);
        const authorize = pusherAuthorizer(
            { name: 'private-user.1' } as never,
            { authEndpoint: '/broadcasting/auth' } as never,
        );
        const callback = vi.fn();

        document.cookie = 'XSRF-TOKEN=before-login';
        authorize.authorize('socket-1', callback);
        await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(1));

        document.cookie = 'XSRF-TOKEN=after-login';
        authorize.authorize('socket-2', callback);
        await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(2));

        expect(post.mock.calls[0]?.[2]).toMatchObject({
            headers: {
                'X-XSRF-TOKEN': 'before-login',
            },
        });
        expect(post.mock.calls[1]?.[2]).toMatchObject({
            headers: {
                'X-XSRF-TOKEN': 'after-login',
            },
        });
        post.mockRestore();
    });
});
