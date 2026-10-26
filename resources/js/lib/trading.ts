import type { DecimalString, OrderMatchedPayload } from '@/types/trading';

export type OrderPreview = {
    cost: DecimalString;
    commission: DecimalString;
    reservation: DecimalString;
};

const CURRENCY_SCALE = 2;
const ASSET_SCALE = 8;
const COMMISSION_NUMERATOR = 15n;
const COMMISSION_DENOMINATOR = 1000n;

function decimalUnits(value: string, scale: number): bigint | null {
    if (!/^\d+(?:\.\d+)?$/.test(value)) {
        return null;
    }

    const [whole, fraction = ''] = value.split('.');

    if (fraction.length > scale) {
        return null;
    }

    return (
        BigInt(whole) * 10n ** BigInt(scale) +
        BigInt(fraction.padEnd(scale, '0') || '0')
    );
}

function roundUp(value: bigint, divisor: bigint): bigint {
    return (value + divisor - 1n) / divisor;
}

function formatDecimal(value: bigint, scale: number): DecimalString {
    const factor = 10n ** BigInt(scale);
    const whole = value / factor;
    const fraction = (value % factor).toString().padStart(scale, '0');

    return `${whole}.${fraction}`;
}

/**
 * Mirror the backend's exact cost and commission calculation for display only.
 * The API remains authoritative for validation and reservation decisions.
 */
export function calculateOrderPreview(
    price: string,
    amount: string,
): OrderPreview | null {
    const priceUnits = decimalUnits(price, CURRENCY_SCALE);
    const amountUnits = decimalUnits(amount, ASSET_SCALE);

    if (priceUnits === null || amountUnits === null) {
        return null;
    }

    const rawCostInTenDecimalPlaces = priceUnits * amountUnits;
    const costInCents = roundUp(rawCostInTenDecimalPlaces, 10n ** 8n);
    const commissionInCents = roundUp(
        costInCents * COMMISSION_NUMERATOR,
        COMMISSION_DENOMINATOR,
    );

    return {
        cost: formatDecimal(costInCents, CURRENCY_SCALE),
        commission: formatDecimal(commissionInCents, CURRENCY_SCALE),
        reservation: formatDecimal(
            costInCents + commissionInCents,
            CURRENCY_SCALE,
        ),
    };
}

export function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function formatTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value));
}

export function formatDecimalValue(value: string, fractionDigits: number): string {
    const [whole, fraction = ''] = value.split('.');

    return `${whole}.${fraction.padEnd(fractionDigits, '0').slice(0, fractionDigits)}`;
}

export function formatMatchNotification(payload: OrderMatchedPayload): string {
    return `${payload.amount} ${payload.symbol} executed at ${payload.execution_price} USD.`;
}
