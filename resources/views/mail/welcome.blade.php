<x-mail::message>
# Welcome, {{ $accountName }}

Your account is ready to use. It starts with **{{ $usdBalance }} USD** and:

@foreach ($assetBalances as $symbol => $amount)
- **{{ $amount }} {{ $symbol }}**
@endforeach

## Getting started

1. Choose the **BTC / USD** or **ETH / USD** market.
2. Choose whether to buy or sell, then enter a limit price and quantity.
3. Place the order. Its required USD or crypto is reserved while it is open.
4. An order fills when a compatible order for the same quantity is available.
   You can cancel an unfilled order to release its reservation.

The buyer pays a **1.5% commission** on a completed trade. Your wallet and
order history show available and reserved balances, open orders, and completed
orders. The orderbook and personal match updates refresh live when connected.

<x-mail::button :url="url('/trade')">
Open the trading screen
</x-mail::button>

Thanks,<br />
{{ config('app.name') }}
</x-mail::message>
