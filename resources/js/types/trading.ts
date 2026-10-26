export type DecimalString = string;

export type AssetSymbol = 'BTC' | 'ETH';

export type TradingMarket = {
    symbol: AssetSymbol;
    label: string;
    amountScale: number;
};

export const TRADING_MARKETS: readonly TradingMarket[] = [
    { symbol: 'BTC', label: 'BTC / USD', amountScale: 8 },
    { symbol: 'ETH', label: 'ETH / USD', amountScale: 8 },
];

export type OrderSide = 'buy' | 'sell';

export type OrderStatus = 1 | 2 | 3;

export type AssetBalance = {
    symbol: AssetSymbol;
    amount: DecimalString;
    locked_amount: DecimalString;
};

export type Profile = {
    balance: DecimalString;
    locked_balance: DecimalString;
    assets: AssetBalance[];
};

export type ProfileResponse = {
    user: Profile;
};

export type TradingOrder = {
    id: number;
    user_id: number;
    symbol: AssetSymbol;
    side: OrderSide;
    price: DecimalString;
    amount: DecimalString;
    status: OrderStatus;
    created_at: string;
    updated_at: string;
};

export type OrderResponse = {
    order: TradingOrder;
};

export type OrderbookResponse = {
    orders: TradingOrder[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type OrderHistoryResponse = {
    orders: TradingOrder[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type PlaceOrderPayload = {
    symbol: AssetSymbol;
    side: OrderSide;
    price: DecimalString;
    amount: DecimalString;
};

export type OrderMatchedPayload = {
    buy_order_id: number;
    sell_order_id: number;
    symbol: AssetSymbol;
    execution_price: DecimalString;
    amount: DecimalString;
    trade_value: DecimalString;
    commission: DecimalString;
};

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
    1: 'Open',
    2: 'Filled',
    3: 'Cancelled',
};
