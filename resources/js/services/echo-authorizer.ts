import type {
    ChannelAuthorizerGenerator,
    ChannelAuthorizationCallback,
} from 'pusher-js';
import { apiClient } from './api';
import { csrfHeadersFromCookie } from './csrf';

interface PusherAuthorizationResponse {
    auth: string;
    channel_data?: string;
}

export const pusherAuthorizer: ChannelAuthorizerGenerator = (
    channel,
    options,
) => ({
    authorize(socketId: string, callback: ChannelAuthorizationCallback): void {
        void apiClient
            .post<PusherAuthorizationResponse>(
                options.authEndpoint,
                {
                    socket_id: socketId,
                    channel_name: channel.name,
                },
                {
                    baseURL: '/',
                    headers: csrfHeadersFromCookie(),
                },
            )
            .then(({ data }) => callback(null, data))
            .catch((error: unknown) => {
                callback(
                    error instanceof Error
                        ? error
                        : new Error('Channel authorization failed.'),
                    null,
                );
            });
    },
});
