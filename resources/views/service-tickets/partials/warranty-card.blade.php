@php
    $status = $ticket->warrantyStatus();
    $canEditWarranty = $canEditWarranty ?? false;
@endphp

<div class="warranty-card warranty-card--{{ $status }}">
    <div class="warranty-card-head">
        <div class="warranty-card-icon"><i class="bi bi-shield-check"></i></div>
        <div>
            <div class="warranty-card-kicker">Garansi servis</div>
            <div class="warranty-card-title">{{ $ticket->warrantyPeriodLabel() }}</div>
        </div>
        <span class="badge text-bg-{{ $ticket->warrantyStatusColor() }}">{{ $ticket->warrantyStatusLabel() }}</span>
    </div>

    @if ($status === 'pending')
        <p class="warranty-card-copy mb-0">Berlaku {{ $ticket->warrantyPeriodLabel() }} setelah perangkat selesai dan lunas.</p>
    @elseif ($status === 'active')
        <div class="warranty-card-meta">
            <div>
                <span>Mulai</span>
                <strong>{{ $ticket->warrantyStartsAt()?->timezone(config('app.timezone'))->format('d/m/Y') }}</strong>
            </div>
            <div>
                <span>Berlaku s/d</span>
                <strong>{{ $ticket->warrantyEndsAt()?->timezone(config('app.timezone'))->format('d/m/Y') }}</strong>
            </div>
            <div>
                <span>Sisa</span>
                <strong>{{ $ticket->warrantyDaysRemaining() }} hari</strong>
            </div>
        </div>
    @elseif ($status === 'expired')
        <p class="warranty-card-copy mb-0">Berakhir {{ $ticket->warrantyEndsAt()?->timezone(config('app.timezone'))->format('d/m/Y') }}.</p>
    @else
        <p class="warranty-card-copy mb-0">Tiket ini tidak memiliki garansi.</p>
    @endif

    @if (filled($ticket->warranty_notes))
        <p class="warranty-card-notes mb-0">{{ $ticket->warranty_notes }}</p>
    @endif

    @if ($canEditWarranty)
        <details class="warranty-card-edit mt-3">
            <summary>Ubah garansi</summary>
            <form method="POST" action="{{ route('service-tickets.warranty', $ticket) }}" class="mt-3">
                @csrf
                @include('service-tickets.partials.warranty-fields', [
                    'ticket' => $ticket,
                    'warrantyPresets' => $warrantyPresets ?? config('service-tickets.warranty_presets'),
                    'fieldId' => 'show_warranty',
                ])
                <button type="submit" class="btn btn-outline-primary btn-sm mt-2">Simpan garansi</button>
            </form>
        </details>
    @endif
</div>
