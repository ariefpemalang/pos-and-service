<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lacak {{ $ticket->ticket_no }} - {{ brand_name() }}</title>
    <meta name="theme-color" content="#D4A017">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tracking.css') }}">
</head>
<body>
    <div class="auth-page track-page">
        <div class="track-card">
            <div class="track-hero">
                <div class="track-brand">
                    <img src="{{ brand_logo_url() }}" alt="{{ brand_name() }}" class="auth-logo">
                </div>
                <p class="track-kicker">Lacak servis</p>
                <div class="track-hero-row">
                    <h1 class="track-title">{{ $ticket->ticket_no }}</h1>
                    <div class="track-status badge text-bg-{{ $ticket->statusColor() }}">{{ $ticket->statusLabel() }}</div>
                </div>
                <p class="track-device">{{ $ticket->deviceSummary() }}</p>
            </div>

            <div class="track-layout">
                <div class="track-main">
                    <dl class="track-meta">
                        <div>
                            <dt>Pelanggan</dt>
                            <dd>{{ $ticket->customer?->name }}</dd>
                        </div>
                        @if ($ticket->color)
                            <div>
                                <dt>Warna</dt>
                                <dd>{{ $ticket->color }}</dd>
                            </div>
                        @endif
                        @if ($ticket->serial_imei)
                            <div>
                                <dt>IMEI / SN</dt>
                                <dd>{{ $ticket->serial_imei }}</dd>
                            </div>
                        @endif
                        @if ($ticket->specs)
                            <div class="track-meta--wide">
                                <dt>Spesifikasi</dt>
                                <dd>{{ $ticket->specs }}</dd>
                            </div>
                        @endif
                        <div class="track-meta--wide">
                            <dt>Keluhan</dt>
                            <dd>{{ $ticket->complaint }}</dd>
                        </div>
                        @if ($ticket->accessories)
                            <div>
                                <dt>Kelengkapan</dt>
                                <dd>{{ $ticket->accessories }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($ticket->estimated_cost !== null || $ticket->hasDownPayment())
                        <div class="track-costs">
                            @if ($ticket->estimated_cost !== null)
                                <div>
                                    <span>Estimasi</span>
                                    <strong>Rp {{ number_format((float) $ticket->estimated_cost, 0, ',', '.') }}</strong>
                                </div>
                            @endif
                            @if ($ticket->hasDownPayment())
                                <div>
                                    <span>Uang muka</span>
                                    <strong>Rp {{ number_format($ticket->downPaymentAmount(), 0, ',', '.') }}</strong>
                                </div>
                            @endif
                            @if ($ticket->remainingEstimate() !== null && $ticket->hasDownPayment())
                                <div>
                                    <span>Sisa</span>
                                    <strong>Rp {{ number_format($ticket->remainingEstimate(), 0, ',', '.') }}</strong>
                                </div>
                            @endif
                        </div>
                    @endif

                    <h2 class="track-timeline-title">Riwayat</h2>
                    <ol class="track-timeline">
                        @foreach ($ticket->statusLogs as $log)
                            <li>
                                <div class="track-timeline-status">{{ $log->toStatusLabel() }}</div>
                                <div class="track-timeline-time">{{ $log->created_at?->format('d/m/Y H:i') }}</div>
                                @if ($log->note)
                                    <div class="track-timeline-note">{{ $log->note }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                <aside class="track-aside">
                    <div class="track-warranty track-warranty--{{ $ticket->warrantyStatus() }}">
                        <div class="track-warranty-head">
                            <i class="bi bi-shield-check"></i>
                            <div>
                                <div class="track-warranty-kicker">Garansi</div>
                                <div class="track-warranty-title">{{ $ticket->warrantyPeriodLabel() }}</div>
                            </div>
                        </div>
                        <span class="badge text-bg-{{ $ticket->warrantyStatusColor() }}">{{ $ticket->warrantyStatusLabel() }}</span>
                        @if ($ticket->warrantyStatus() === 'pending')
                            <p class="track-warranty-copy">Berlaku setelah servis selesai dan lunas.</p>
                        @elseif ($ticket->warrantyStatus() === 'active')
                            <p class="track-warranty-copy">Berlaku s/d {{ $ticket->warrantyEndsAt()?->timezone(config('app.timezone'))->format('d/m/Y') }} · sisa {{ $ticket->warrantyDaysRemaining() }} hari.</p>
                        @elseif ($ticket->warrantyStatus() === 'expired')
                            <p class="track-warranty-copy">Berakhir {{ $ticket->warrantyEndsAt()?->timezone(config('app.timezone'))->format('d/m/Y') }}.</p>
                        @else
                            <p class="track-warranty-copy">Tidak ada garansi untuk servis ini.</p>
                        @endif
                        @if (filled($ticket->warranty_notes))
                            <p class="track-warranty-notes">{{ $ticket->warranty_notes }}</p>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
        <p class="auth-footer">&copy; {{ date('Y') }} {{ brand_name() }}</p>
    </div>
</body>
</html>
