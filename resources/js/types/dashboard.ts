import type {
    AssetSymbol,
    DecimalString,
    OrderSide,
    OrderStatus,
} from './trading';

export type DashboardPeriod = 7 | 30;

export type DashboardSummary = {
    available_usd: DecimalString;
    reserved_usd: DecimalString;
    open_orders: number;
    executed_trade_value: DecimalString;
};

export type DashboardDailyValue = {
    date: string;
    label: string;
    value: DecimalString;
};

export type DashboardStatusCount = {
    status: OrderStatus;
    label: string;
    count: number;
};

export type DashboardExecution = {
    id: number;
    date: string;
    market: AssetSymbol;
    side: OrderSide;
    price: DecimalString;
    amount: DecimalString;
    trade_value: DecimalString;
};

export type DashboardData = {
    period: DashboardPeriod;
    timezone: string;
    start_date: string;
    end_date: string;
    summary: DashboardSummary;
    daily_executed_value: DashboardDailyValue[];
    order_status_breakdown: DashboardStatusCount[];
    recent_executions: DashboardExecution[];
};

export type DashboardResponse = {
    dashboard: DashboardData;
};
