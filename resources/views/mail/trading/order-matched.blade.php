<x-mail::message>
# {{ $symbolValue }} order filled

Hello {{ $accountName }},

Your {{ $sideValue }} order has been fully matched and settled.

<x-mail::table>
| Trade detail | Value |
|:-------------|------:|
| Quantity | {{ $amount }} {{ $symbolValue }} |
| Your limit price | {{ $orderPrice }} USD |
| Execution price | {{ $executionPrice }} USD |
| Trade value | {{ $tradeValue }} USD |
| Buyer commission | {{ $commission }} USD |
</x-mail::table>

The buyer pays the commission. If you are the seller, this fee was not
deducted from your proceeds.

@if ($isDemo)
This message reports activity for the demo account **{{ $accountName }}**
(`{{ $accountEmail }}`).
@endif

{{ config('app.name') }}
</x-mail::message>
