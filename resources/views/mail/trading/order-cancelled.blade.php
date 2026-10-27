<x-mail::message>
# {{ ucfirst($sideValue) }} {{ $symbolValue }} order cancelled

Hello {{ $accountName }},

Your open limit order has been cancelled and its reservation released.

<x-mail::table>
| Order detail | Value |
|:-------------|------:|
| Limit price | {{ $price }} USD |
| Quantity | {{ $amount }} {{ $symbolValue }} |
| Released | {{ $releasedReservation }} {{ $reservationCurrency }} |
</x-mail::table>

@if ($isDemo)
This message reports activity for the demo account **{{ $accountName }}**
(`{{ $accountEmail }}`).
@endif

{{ config('app.name') }}
</x-mail::message>
