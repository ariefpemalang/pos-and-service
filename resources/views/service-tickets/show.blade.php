@extends('layouts.app')

@section('title', 'Tiket '.$ticket->ticket_no)

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Tiket Servis', 'url' => route('service-tickets.index')],
            ['label' => $ticket->ticket_no],
        ],
        'title' => $ticket->ticket_no,
        'subtitle' => $ticket->deviceSummary().' — '.$ticket->customer?->name,
    ])

    <div class="row g-3 ticket-show">
        <div class="col-lg-8 d-flex flex-column gap-3">
            <div class="data-panel">
                <div class="data-panel-head data-panel-head-row">
                    <h2 class="data-panel-title"><i class="bi bi-phone me-1"></i> Perangkat</h2>
                    <span class="badge text-bg-{{ $ticket->statusColor() }}">{{ $ticket->statusLabel() }}</span>
                </div>
                <div class="data-panel-body">
                    <div class="ticket-facts">
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">Pelanggan</span>
                            <span class="ticket-fact-value">
                                {{ $ticket->customer?->name }}
                                @if ($ticket->customer?->phone)
                                    <span class="ticket-fact-sub">{{ $ticket->customer->phone }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">Teknisi</span>
                            <span class="ticket-fact-value">{{ $ticket->technician?->name ?? 'Belum ditugaskan' }}</span>
                        </div>
                        <div class="ticket-fact ticket-fact--sm">
                            <span class="ticket-fact-label">Jenis</span>
                            <span class="ticket-fact-value">{{ $ticket->deviceTypeLabel() }}</span>
                        </div>
                        <div class="ticket-fact ticket-fact--sm">
                            <span class="ticket-fact-label">Merk / tipe</span>
                            <span class="ticket-fact-value">{{ $ticket->brand }} {{ $ticket->model }}</span>
                        </div>
                        <div class="ticket-fact ticket-fact--sm">
                            <span class="ticket-fact-label">Warna</span>
                            <span class="ticket-fact-value">{{ $ticket->color ?: '-' }}</span>
                        </div>
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">IMEI / SN</span>
                            <span class="ticket-fact-value">{{ $ticket->serial_imei ?: '-' }}</span>
                        </div>
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">Spesifikasi</span>
                            <span class="ticket-fact-value">{{ $ticket->specs ?: '-' }}</span>
                        </div>
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">Kelengkapan</span>
                            <span class="ticket-fact-value">{{ $ticket->accessories ?: '-' }}</span>
                        </div>
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">Keluhan</span>
                            <span class="ticket-fact-value">{{ $ticket->complaint }}</span>
                        </div>
                        <div class="ticket-fact">
                            <span class="ticket-fact-label">Diagnosa</span>
                            <span class="ticket-fact-value">{{ $ticket->diagnosis ?: '-' }}</span>
                        </div>
                        @if (filled($ticket->internal_notes))
                            <div class="ticket-fact ticket-fact--wide">
                                <span class="ticket-fact-label">Catatan internal</span>
                                <span class="ticket-fact-value">{{ $ticket->internal_notes }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="ticket-costbar">
                        <div>
                            <span>Estimasi</span>
                            <strong>{{ $ticket->estimated_cost !== null ? 'Rp '.number_format((float) $ticket->estimated_cost, 0, ',', '.') : '-' }}</strong>
                        </div>
                        <div>
                            <span>DP / Uang muka</span>
                            <strong>
                                @if ($ticket->hasDownPayment())
                                    Rp {{ number_format($ticket->downPaymentAmount(), 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </strong>
                            @if ($ticket->hasDownPayment())
                                <em>
                                    {{ \App\Support\PaymentMethodResolver::label($ticket->down_payment_payment_method) }}
                                    @if ($ticket->downPaymentBankAccount)
                                        · {{ $ticket->downPaymentBankAccount->displayLabel() }}
                                    @endif
                                </em>
                            @endif
                        </div>
                        <div>
                            <span>Sisa estimasi</span>
                            <strong>
                                @if ($ticket->remainingEstimate() !== null && $ticket->hasDownPayment())
                                    Rp {{ number_format($ticket->remainingEstimate(), 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="data-panel">
                <div class="data-panel-head">
                    <h2 class="data-panel-title"><i class="bi bi-clock-history me-1"></i> Riwayat status</h2>
                </div>
                <div class="data-panel-body">
                    @forelse ($ticket->statusLogs as $log)
                        <div class="ticket-log">
                            <span class="badge text-bg-{{ config('service-tickets.status_colors.'.$log->to_status, 'secondary') }}">
                                {{ $log->toStatusLabel() }}
                            </span>
                            <div class="ticket-log-body">
                                <div class="ticket-log-top">
                                    <span class="ticket-log-meta">{{ $log->created_at?->format('d/m/Y H:i') }} · {{ $log->user?->name ?? 'Sistem' }}</span>
                                    <span class="ticket-log-wa {{ $log->notified ? 'is-sent' : '' }}">
                                        {{ $log->notified ? 'WA terkirim' : ($log->notify_error ?: 'WA tidak dikirim') }}
                                    </span>
                                </div>
                                @if ($log->note)
                                    <div class="ticket-log-note">{{ $log->note }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Belum ada riwayat.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex flex-column gap-3">
            <div class="data-panel">
                <div class="data-panel-head">
                    <h2 class="data-panel-title"><i class="bi bi-shield-check me-1"></i> Garansi</h2>
                </div>
                <div class="data-panel-body">
                    @include('service-tickets.partials.warranty-card', [
                        'ticket' => $ticket,
                        'canEditWarranty' => auth()->user()?->can('service ticket edit') && $ticket->status !== 'cancelled',
                        'warrantyPresets' => config('service-tickets.warranty_presets'),
                    ])
                </div>
            </div>

            <div class="data-panel">
                <div class="data-panel-head">
                    <h2 class="data-panel-title"><i class="bi bi-link-45deg me-1"></i> Lacak pelanggan</h2>
                </div>
                <div class="data-panel-body">
                    <input type="text" class="form-control form-control-clean mb-2" readonly value="{{ $ticket->trackingUrl() }}">
                    <div class="d-grid gap-2">
                        <a href="{{ $ticket->trackingUrl() }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
                            Buka halaman lacak
                        </a>
                        @can('service ticket edit')
                            <form method="POST" action="{{ route('service-tickets.resend', $ticket) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-whatsapp me-1"></i> Kirim ulang WA
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>

            @can('service ticket edit')
                @if (! $ticket->isClosed())
                    <div class="data-panel">
                        <div class="data-panel-head">
                            <h2 class="data-panel-title"><i class="bi bi-arrow-repeat me-1"></i> Ubah status</h2>
                        </div>
                        <div class="data-panel-body">
                            <form method="POST" action="{{ route('service-tickets.status', $ticket) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label">Status baru</label>
                                    <select name="status" class="form-control form-control-clean" required>
                                        @foreach ($statuses as $value => $label)
                                            @if ($value !== $ticket->status)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Catatan untuk pelanggan</label>
                                    <textarea name="note" class="form-control form-control-clean" rows="2"
                                        placeholder="Opsional, ikut terkirim di WhatsApp dan halaman lacak."></textarea>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="hidden" name="notify_customer" value="0">
                                    <input type="checkbox" name="notify_customer" value="1" class="form-check-input" id="notify_customer" checked>
                                    <label class="form-check-label" for="notify_customer">Kirim WhatsApp ke pelanggan</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Update status</button>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            <div class="ticket-show-actions">
                @can('transaction create')
                    @if ($ticket->canCreateInvoice())
                        <a href="{{ route('transactions.create', ['ticket' => $ticket->id]) }}" class="btn btn-primary w-100">
                            <i class="bi bi-receipt me-1"></i> Buat Nota Pembayaran
                        </a>
                    @endif
                @endcan
                @can('service ticket view')
                    <a href="{{ route('service-tickets.claim-slip', $ticket) }}" class="btn btn-outline-secondary w-100" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-1"></i> Cetak bukti ambil barang
                    </a>
                @endcan
                @if ($ticket->paidTransaction)
                    <a href="{{ route('transactions.invoice', $ticket->paidTransaction) }}" class="btn btn-outline-primary w-100" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-1"></i> Cetak nota {{ $ticket->paidTransaction->transaction_no }}
                    </a>
                @endif
                @can('service ticket edit')
                    @if (! $ticket->isClosed())
                        <a href="{{ route('service-tickets.edit', $ticket) }}" class="btn btn-outline-secondary w-100">Ubah data perangkat</a>
                    @endif
                @endcan
                @can('service ticket delete')
                    @if (in_array($ticket->status, ['received', 'cancelled'], true))
                        <form method="POST" action="{{ route('service-tickets.destroy', $ticket) }}"
                            onsubmit="return confirm('Hapus tiket ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">Hapus tiket</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
@endsection
