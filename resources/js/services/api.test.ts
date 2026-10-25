import { describe, expect, it } from 'vitest';
import { toApiError } from './api';

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
