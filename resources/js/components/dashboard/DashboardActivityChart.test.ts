import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DashboardActivityChart from './DashboardActivityChart.vue';

function values(count: number, firstValue = '0.00') {
    return Array.from({ length: count }, (_, index) => ({
        date: '2026-10-' + String(index + 1).padStart(2, '0'),
        label: 'Oct ' + (index + 1),
        value: index === 0 ? firstValue : '0.00',
    }));
}

describe('DashboardActivityChart', () => {
    it('resolves percentage heights against a definite plot height', () => {
        const wrapper = mount(DashboardActivityChart, {
            props: {
                values: values(7, '900.00'),
                period: 7,
                loading: false,
                refreshing: false,
            },
        });

        const bars = wrapper.findAll('[data-test="activity-bar"]');

        expect(bars[0]?.attributes('style')).toContain('height: 100%');
        expect(bars[1]?.attributes('style')).toContain('height: 0%');
    });

    it('keeps small positive values proportional to the maximum', () => {
        const wrapper = mount(DashboardActivityChart, {
            props: {
                values: [
                    {
                        date: '2026-10-01',
                        label: 'Oct 1',
                        value: '100.00',
                    },
                    {
                        date: '2026-10-02',
                        label: 'Oct 2',
                        value: '1.00',
                    },
                ],
                period: 7,
                loading: false,
                refreshing: false,
            },
        });

        const bars = wrapper.findAll('[data-test="activity-bar"]');

        expect(bars[1]?.attributes('style')).toContain('height: 1%');
    });

    it('reduces narrow labels for a thirty-day period', () => {
        const wrapper = mount(DashboardActivityChart, {
            props: {
                values: values(30),
                period: 30,
                loading: false,
                refreshing: false,
            },
        });

        expect(
            wrapper.findAll('[data-test="activity-label"].sm\\:hidden'),
        ).toHaveLength(3);
        expect(
            wrapper.findAll('[data-test="activity-label"].sm\\:inline'),
        ).toHaveLength(7);
        expect(
            wrapper.findAll('[data-test="activity-label-row"]'),
        ).toHaveLength(30);
    });
});
