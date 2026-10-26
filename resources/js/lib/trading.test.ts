import { describe, expect, it } from 'vitest';
import { calculateOrderPreview } from './trading';

describe('calculateOrderPreview', () => {
    it('mirrors the backend reservation for a normal buy', () => {
        expect(calculateOrderPreview('95000.00', '0.01000000')).toEqual({
            cost: '950.00',
            commission: '14.25',
            reservation: '964.25',
        });
    });

    it('rounds trade value before calculating commission', () => {
        expect(calculateOrderPreview('100.10', '0.01000000')).toEqual({
            cost: '1.01',
            commission: '0.02',
            reservation: '1.03',
        });
    });

    it('returns no preview for invalid precision', () => {
        expect(calculateOrderPreview('100.001', '0.01000000')).toBeNull();
        expect(calculateOrderPreview('100.00', '0.000000001')).toBeNull();
    });
});
