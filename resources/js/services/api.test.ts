import { describe, expect, it } from 'vitest';
import { toApiError } from './api';
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
});
