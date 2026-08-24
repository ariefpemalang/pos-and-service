@extends('layouts.print')

@section('title', 'Nota Service '.$ticket->ticket_no)

@section('content')
    @php
        $ticket->loadMissing(['customer', 'downPaymentTransaction']);
        $dp = $transaction ?? $ticket->downPaymentTransaction;
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $receivedAt = $ticket->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $device = trim($ticket->brand.' '.$ticket->model);
        if ($ticket->color) {
            $device .= ' · '.$ticket->color;
        }
        $dpAmount = $dp && ! $dp->isRefunded()
            ? (float) $dp->total
            : $ticket->downPaymentAmount();
        $hasDp = $dpAmount > 0;
        $sisa = $ticket->estimated_cost !== null
            ? round(max(0, (float) $ticket->estimated_cost - $dpAmount), 2)
            : null;
    @endphp

    <div class="receipt-toolbar no-print">
        <button type="button" class="btn-receipt" onclick="window.print()">Cetak</button>
        <button type="button" class="btn-receipt btn-receipt--light" onclick="window.close()">Tutup</button>
    </div>

    <article class="receipt-thermal">
        @if (brand_has_custom_logo())
            <div class="receipt-center receipt-logo-wrap">
                <img src="{{ brand_logo_url() }}" alt="{{ brand_name() }}" class="receipt-logo">
            </div>
        @endif
        <div class="receipt-center receipt-brand">{{ brand_name() }}</div>
        <div class="receipt-center receipt-title">NOTA SERVICE</div>
        <div class="receipt-center receipt-meta">{{ $ticket->ticket_no }} · {{ $receivedAt }}</div>

        <div class="receipt-sep"></div>

        <div class="receipt-text receipt-text--strong">{{ $ticket->customer?->name }}</div>
        @if ($ticket->customer?->phone)
            <div class="receipt-text receipt-text--muted">{{ $ticket->customer->phone }}</div>
        @endif

        <div class="receipt-sep"></div>

        <div class="receipt-text receipt-text--strong">{{ $device }}</div>
        @if ($ticket->serial_imei)
            <div class="receipt-text">IMEI {{ $ticket->serial_imei }}</div>
        @endif
        @if ($ticket->accessories)
            <div class="receipt-text receipt-text--muted">Kelengkapan: {{ $ticket->accessories }}</div>
        @endif

        @if ($hasDp || $ticket->estimated_cost !== null)
            <div class="receipt-sep"></div>
            @if ($hasDp)
                <div class="receipt-row receipt-row--tight">
                    <span>Uang muka</span>
                    <span>{{ $rp($dpAmount) }}</span>
                </div>
            @endif
            @if ($ticket->estimated_cost !== null)
                <div class="receipt-row receipt-row--total">
                    <span>{{ $hasDp ? 'Sisa' : 'Estimasi' }}</span>
                    <span>{{ $rp($hasDp ? $sisa : $ticket->estimated_cost) }}</span>
                </div>
            @endif
        @endif

        <div class="receipt-sep"></div>
        <div class="receipt-center receipt-footer">Tunjukkan struk ini saat ambil barang</div>
    </article>
@endsection

@push('js')
    @if (request()->boolean('print'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
@endpush
