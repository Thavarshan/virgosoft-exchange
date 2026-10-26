<?php

namespace App\Actions\Dashboard;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildDashboard
{
    private const REPORTING_TIMEZONE = 'UTC';

    /**
     * Build dashboard data without loading the user's complete history.
     *
     * @return array{
     *     period: int,
     *     timezone: string,
     *     start_date: string,
     *     end_date: string,
     *     summary: array{available_usd: string, reserved_usd: string, open_orders: int, executed_trade_value: string},
     *     daily_executed_value: array<int, array{date: string, label: string, value: string}>,
     *     order_status_breakdown: array<int, array{status: int, label: string, count: int}>,
     *     recent_executions: array<int, array{id: int, date: string, market: string, side: string, price: string, amount: string, trade_value: string}>
     * }
     */
    public function execute(User $user, int $period): array
    {
        $today = CarbonImmutable::now(self::REPORTING_TIMEZONE)->startOfDay();
        $start = $today->subDays($period - 1);
        $end = $today->addDay();

        return [
            'period' => $period,
            'timezone' => self::REPORTING_TIMEZONE,
            'start_date' => $start->toDateString(),
            'end_date' => $today->toDateString(),
            'summary' => [
                'available_usd' => (string) $user->balance,
                'reserved_usd' => (string) $user->locked_balance,
                'open_orders' => $user->orders()->open()->count(),
                'executed_trade_value' => $this->executedTradeValue($user, $start, $end),
            ],
            'daily_executed_value' => $this->dailyExecutedValues($user, $start, $end, $period),
            'order_status_breakdown' => $this->orderStatusBreakdown($user, $start, $end),
            'recent_executions' => $this->recentExecutions($user, $start, $end),
        ];
    }

    /**
     * Start a query containing each trade involving the authenticated user.
     *
     * @return Builder<Trade>
     */
    protected function personalTrades(User $user): Builder
    {
        return Trade::query()->where(function (Builder $query) use ($user): void {
            $query
                ->whereHas(
                    'buyOrder',
                    fn (Builder $orders) => $orders->where('user_id', $user->id),
                )
                ->orWhereHas(
                    'sellOrder',
                    fn (Builder $orders) => $orders->where('user_id', $user->id),
                );
        });
    }

    /**
     * Sum executed trade value in PostgreSQL so decimal precision is retained.
     */
    protected function executedTradeValue(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): string {
        $value = $this->personalTrades($user)
            ->whereBetween('trades.created_at', [$start, $end->subMicrosecond()])
            ->selectRaw('COALESCE(SUM(trade_value), 0)::numeric(20, 2) AS total')
            ->value('total');

        return $this->numericString($value ?? '0.00');
    }

    /**
     * Return every date in the period, including days without executions.
     *
     * @return array<int, array{date: string, label: string, value: string}>
     */
    protected function dailyExecutedValues(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $period,
    ): array {
        $rows = $this->personalTrades($user)
            ->whereBetween('trades.created_at', [$start, $end->subMicrosecond()])
            ->selectRaw(
                'DATE(trades.created_at) AS activity_date, '
                .'COALESCE(SUM(trade_value), 0)::numeric(20, 2) AS total',
            )
            ->groupByRaw('DATE(trades.created_at)')
            ->get();
        $values = $rows->pluck('total', 'activity_date');

        return collect(range(0, $period - 1))
            ->map(fn (int $offset): array => $this->dailyValue(
                $start->addDays($offset),
                $values,
            ))
            ->all();
    }

    /**
     * Format one daily chart entry while retaining its decimal string.
     *
     * @param  Collection<int|string, mixed>  $values
     * @return array{date: string, label: string, value: string}
     */
    protected function dailyValue(
        CarbonImmutable $date,
        Collection $values,
    ): array {
        $dateString = $date->toDateString();

        return [
            'date' => $dateString,
            'label' => $date->format('M j'),
            'value' => $this->numericString(
                $values->get($dateString) ?? '0.00',
            ),
        ];
    }

    /**
     * Group orders created in the period by their current lifecycle state.
     *
     * @return array<int, array{status: int, label: string, count: int}>
     */
    protected function orderStatusBreakdown(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $counts = $user->orders()
            ->whereBetween('created_at', [$start, $end->subMicrosecond()])
            ->selectRaw('status, COUNT(*) AS order_count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function (Order $order): array {
                return [
                    $order->status->value => (int) $order->getAttribute(
                        'order_count',
                    ),
                ];
            });

        return collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): array => [
                'status' => $status->value,
                'label' => ucfirst(strtolower($status->name)),
                'count' => $counts->get($status->value, 0),
            ])
            ->all();
    }

    /**
     * Return the latest five executions with the user's side of each trade.
     *
     * @return array<int, array{id: int, date: string, market: string, side: string, price: string, amount: string, trade_value: string}>
     */
    protected function recentExecutions(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        return $this->personalTrades($user)
            ->whereBetween('trades.created_at', [$start, $end->subMicrosecond()])
            ->with([
                'buyOrder:id,user_id',
                'sellOrder:id,user_id',
            ])
            ->select([
                'trades.id',
                'trades.buy_order_id',
                'trades.sell_order_id',
                'trades.symbol',
                'trades.price',
                'trades.amount',
                'trades.trade_value',
                'trades.created_at',
            ])
            ->orderByDesc('trades.created_at')
            ->orderByDesc('trades.id')
            ->limit(5)
            ->get()
            ->map(fn (Trade $trade): array => $this->recentExecution($trade, $user))
            ->values()
            ->all();
    }

    /**
     * Format one execution row for the dashboard.
     *
     * @return array{id: int, date: string, market: string, side: string, price: string, amount: string, trade_value: string}
     */
    protected function recentExecution(Trade $trade, User $user): array
    {
        $side = $trade->buyOrder->user_id === $user->id
            ? OrderSide::BUY->value
            : OrderSide::SELL->value;

        return [
            'id' => $trade->id,
            'date' => $trade->created_at?->timezone(self::REPORTING_TIMEZONE)->toIso8601String() ?? '',
            'market' => $trade->symbol->value,
            'side' => $side,
            'price' => $this->numericString($trade->price),
            'amount' => $this->numericString($trade->amount),
            'trade_value' => $this->numericString($trade->trade_value),
        ];
    }

    /**
     * Convert a database decimal to the numeric-string type used by the API.
     *
     * @return numeric-string
     */
    protected function numericString(mixed $value): string
    {
        if (! is_string($value) || ! is_numeric($value)) {
            throw new \LogicException('The dashboard returned an invalid decimal.');
        }

        return $value;
    }
}
