<x-mail::message>
# {{ ucfirst($sideValue) }} {{ $symbolValue }} order is open

Hello {{ $accountName }},

Your limit order is resting in the {{ $symbolValue }}/USD market. The required
funds or assets have been reserved.

<x-mail::table>
| Order detail | Value |
|:-------------|------:|
| Limit price | {{ $price }} USD |
| Quantity | {{ $amount }} {{ $symbolValue }} |
| Reserved | {{ $reservedAmount }} {{ $reservationCurrency }} |
</x-mail::table>

@if ($isDemo)
This message reports activity for the demo account **{{ $accountName }}**
(`{{ $accountEmail }}`).
@endif

You will receive another email if this order is filled or cancelled.
</x-mail::message>
