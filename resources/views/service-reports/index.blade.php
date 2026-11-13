@extends('layouts.app')

@section('title', 'Laporan Servis')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Keuangan', 'url' => route('financial-reports.index')],
            ['label' => 'Laporan Servis'],
        ],
        'title' => 'Laporan Servis',
        'subtitle' => 'Rekapitulasi barang servis masuk, selesai ditangani, keluar, omzet, modal sparepart, dan profit.',
    ])

    @php
        $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $selectedMonthName = $monthsList[$month] ?? '';
    @endphp

    {{-- ═══════════════════════════════════════════════════
         TOOLBAR & FILTER PANEL
         ═══════════════════════════════════════════════════ --}}
    <div class="data-panel mb-4 no-print">
        <div class="data-panel-head">
            <div class="data-panel-icon">
                <i class="bi bi-funnel"></i>
            </div>
            <div class="flex-grow-1">
                <h3 class="data-panel-title">Filter Periode Laporan Servis</h3>
                <p class="data-panel-desc">
                    Tampilan aktif:
                    <strong>{{ $mode === 'monthly' ? 'Bulanan — ' . $selectedMonthName . ' ' . $year : 'Tahunan — Tahun ' . $year }}</strong>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('service-reports.export-excel', ['mode' => $mode, 'year' => $year, 'month' => $month]) }}"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </a>
                <a href="{{ route('technician-reports.index', ['mode' => $mode, 'year' => $year, 'month' => $month]) }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-people me-1"></i> Laporan Teknisi
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak
                </button>
            </div>
        </div>
        <div class="data-panel-body pt-3 pb-3">
            <form method="get" action="{{ route('service-reports.index') }}" class="row g-3 align-items-end" id="formReportFilter">
                {{-- Pilihan Mode --}}
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-layout-text-window-reverse me-1"></i> Mode Tampilan
                    </label>
                    <select name="mode" class="form-select form-control-clean" id="reportModeSelect" onchange="handleModeChange(this.value)">
                        <option value="monthly" {{ $mode === 'monthly' ? 'selected' : '' }}>Bulanan (Rincian Per Hari)</option>
                        <option value="yearly" {{ $mode === 'yearly' ? 'selected' : '' }}>Tahunan (Rincian Per Bulan)</option>
                    </select>
                </div>

                {{-- Pilihan Bulan (Bulanan saja) --}}
                <div class="col-md-3" id="wrapperMonthField" style="{{ $mode === 'yearly' ? 'display:none;' : '' }}">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-calendar-month me-1"></i> Bulan
                    </label>
                    <select name="month" class="form-select form-control-clean" id="reportMonthSelect">
                        @foreach ($monthsList as $mNum => $mName)
                            <option value="{{ $mNum }}" {{ (int) $month === (int) $mNum ? 'selected' : '' }}>
                                {{ $mName }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilihan Tahun --}}
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-calendar3 me-1"></i> Tahun
                    </label>
                    <select name="year" class="form-select form-control-clean">
                        @for ($y = now()->year + 1; $y >= 2024; $y--)
                            <option value="{{ $y }}" {{ (int) $year === (int) $y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                </div>

                {{-- Tombol Terapkan Filter --}}
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         KPI SUMMARY CARDS
         ═══════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        {{-- Barang Masuk --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="dash-kpi dash-kpi-primary">
                <div class="dash-kpi-icon"><i class="bi bi-box-arrow-in-right"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">SERVIS MASUK</div>
                    <div class="dash-kpi-value text-primary">{{ number_format($data['totals']['masuk']) }} <small class="fs-6 fw-normal">Unit</small></div>
                    <div class="dash-kpi-meta">{{ $mode === 'monthly' ? $selectedMonthName : 'Tahun ' . $year }}</div>
                </div>
            </div>
        </div>

        {{-- Selesai Ditangani --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="dash-kpi dash-kpi-info">
                <div class="dash-kpi-icon"><i class="bi bi-check2-circle"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">SELESAI</div>
                    <div class="dash-kpi-value text-info">{{ number_format($data['totals']['selesai']) }} <small class="fs-6 fw-normal">Unit</small></div>
                    <div class="dash-kpi-meta">Tuntas ditangani</div>
                </div>
            </div>
        </div>

        {{-- Barang Keluar --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="dash-kpi dash-kpi-warning">
                <div class="dash-kpi-icon"><i class="bi bi-box-arrow-right"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">SERVIS KELUAR</div>
                    <div class="dash-kpi-value text-warning">{{ number_format($data['totals']['keluar']) }} <small class="fs-6 fw-normal">Unit</small></div>
                    <div class="dash-kpi-meta">Diambil pelanggan</div>
                </div>
            </div>
        </div>

        {{-- Total Pemasukan --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="dash-kpi dash-kpi-success">
                <div class="dash-kpi-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">TOTAL OMZET</div>
                    <div class="dash-kpi-value" style="color:var(--accent);">{{ $rp($data['totals']['pemasukan']) }}</div>
                    <div class="dash-kpi-meta">Jasa & sparepart</div>
                </div>
            </div>
        </div>

        {{-- Modal Sparepart --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="dash-kpi">
                <div class="dash-kpi-icon" style="background:rgba(100,116,139,.12);color:#64748b;"><i class="bi bi-tools"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">MODAL PART</div>
                    <div class="dash-kpi-value text-muted">{{ $rp($data['totals']['modal']) }}</div>
                    <div class="dash-kpi-meta">HPP suku cadang</div>
                </div>
            </div>
        </div>

        {{-- Total Profit --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="dash-kpi dash-kpi-success">
                <div class="dash-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">ESTIMASI PROFIT</div>
                    <div class="dash-kpi-value text-success">{{ $rp($data['totals']['profit']) }}</div>
                    <div class="dash-kpi-meta">Omzet - Modal</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         MAIN REPORT TABLE
         ═══════════════════════════════════════════════════ --}}
    <div class="data-panel">
        <div class="data-panel-head">
            <div class="data-panel-icon">
                <i class="bi bi-tools"></i>
            </div>
            <div class="flex-grow-1">
                <h2 class="data-panel-title">
                    {{ $mode === 'monthly' ? 'Rincian Laporan Servis Harian — Bulan ' . $selectedMonthName . ' ' . $year : 'Rincian Laporan Servis Bulanan — Tahun ' . $year }}
                </h2>
                <p class="data-panel-desc">
                    Data akurat terintegrasi otomatis dari tiket servis dan pembayaran kasir.
                </p>
            </div>
            <div class="d-none d-md-flex flex-wrap gap-2 align-items-center">
                <span class="ar-summary-pill ar-summary-pill-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk: <strong>{{ number_format($data['totals']['masuk']) }} Unit</strong>
                </span>
                <span class="ar-summary-pill ar-summary-pill-info">
                    <i class="bi bi-check2-circle"></i> Selesai: <strong>{{ number_format($data['totals']['selesai']) }} Unit</strong>
                </span>
                <span class="ar-summary-pill ar-summary-pill-success">
                    <i class="bi bi-graph-up-arrow"></i> Profit: <strong>{{ $rp($data['totals']['profit']) }}</strong>
                </span>
            </div>
        </div>

        <div class="data-panel-body p-0">
            <div class="table-responsive">
                @if ($mode === 'yearly')
                    {{-- ─────────────────────────────────────────────
                         MODE TAHUNAN (PER BULAN)
                         ───────────────────────────────────────────── --}}
                    <table class="ar-report-table table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">Tahun</th>
                                <th style="width: 160px;">Bulan</th>
                                <th style="text-align: center;"><i class="bi bi-box-arrow-in-right text-primary me-1"></i> Servis Masuk</th>
                                <th style="text-align: center;"><i class="bi bi-check2-circle text-info me-1"></i> Selesai Ditangani</th>
                                <th style="text-align: center;"><i class="bi bi-box-arrow-right text-warning me-1"></i> Servis Keluar</th>
                                <th style="text-align: right;"><i class="bi bi-cash-stack text-success me-1"></i> Total Pemasukan</th>
                                <th style="text-align: right;"><i class="bi bi-tools text-muted me-1"></i> Modal Sparepart</th>
                                <th style="text-align: right;"><i class="bi bi-graph-up-arrow text-success me-1"></i> Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['rows'] as $row)
                                <tr class="{{ $row['is_current'] ? 'table-row-current' : '' }}">
                                    <td style="text-align: center;" class="text-muted">{{ $row['year'] }}</td>
                                    <td>
                                        <a href="{{ route('service-reports.index', ['mode' => 'monthly', 'year' => $row['year'], 'month' => $row['month_num']]) }}"
                                           class="text-decoration-none fw-semibold d-inline-flex align-items-center gap-1"
                                           style="color: var(--admin-text-heading);"
                                           title="Klik untuk melihat rincian per hari di bulan ini">
                                            <span>{{ $row['month_name'] }}</span>
                                            @if ($row['is_current'])
                                                <span class="badge bg-warning-subtle text-warning ms-1" style="font-size: 0.68rem;">Bulan Ini</span>
                                            @endif
                                            <i class="bi bi-arrow-right-short text-muted"></i>
                                        </a>
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($row['masuk'] > 0)
                                            <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                                                {{ $row['masuk'] }} Unit
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($row['selesai'] > 0)
                                            <span class="badge bg-info-subtle text-info fw-semibold px-2 py-1">
                                                {{ $row['selesai'] }} Unit
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($row['keluar'] > 0)
                                            <span class="badge bg-warning-subtle text-warning fw-semibold px-2 py-1">
                                                {{ $row['keluar'] }} Unit
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align: right;" class="fw-semibold text-heading">
                                        {{ $row['pemasukan'] > 0 ? $rp($row['pemasukan']) : 'Rp 0' }}
                                    </td>
                                    <td style="text-align: right;" class="text-muted">
                                        {{ $row['modal'] > 0 ? $rp($row['modal']) : 'Rp 0' }}
                                    </td>
                                    <td style="text-align: right;" class="fw-bold {{ $row['profit'] > 0 ? 'text-success' : 'text-muted' }}">
                                        {{ $row['profit'] > 0 ? $rp($row['profit']) : 'Rp 0' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="text-align: center;">Total</td>
                                <td>Laporan Servis Tahun {{ $year }}</td>
                                <td style="text-align: center;" class="text-primary">{{ number_format($data['totals']['masuk']) }} Unit</td>
                                <td style="text-align: center;" class="text-info">{{ number_format($data['totals']['selesai']) }} Unit</td>
                                <td style="text-align: center;" class="text-warning">{{ number_format($data['totals']['keluar']) }} Unit</td>
                                <td style="text-align: right;" class="text-heading">{{ $rp($data['totals']['pemasukan']) }}</td>
                                <td style="text-align: right;" class="text-muted">{{ $rp($data['totals']['modal']) }}</td>
                                <td style="text-align: right;" class="text-success">{{ $rp($data['totals']['profit']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    {{-- ─────────────────────────────────────────────
                         MODE BULANAN (PER HARI)
                         ───────────────────────────────────────────── --}}
                    <table class="ar-report-table table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 120px;"><i class="bi bi-calendar-event me-1"></i> Hari</th>
                                <th style="width: 180px;"><i class="bi bi-calendar-date me-1"></i> Tanggal</th>
                                <th style="text-align: center;"><i class="bi bi-box-arrow-in-right text-primary me-1"></i> Servis Masuk</th>
                                <th style="text-align: center;"><i class="bi bi-check2-circle text-info me-1"></i> Selesai Ditangani</th>
                                <th style="text-align: center;"><i class="bi bi-box-arrow-right text-warning me-1"></i> Servis Keluar</th>
                                <th style="text-align: right;"><i class="bi bi-cash-stack text-success me-1"></i> Total Pemasukan</th>
                                <th style="text-align: right;"><i class="bi bi-tools text-muted me-1"></i> Modal Sparepart</th>
                                <th style="text-align: right;"><i class="bi bi-graph-up-arrow text-success me-1"></i> Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['rows'] as $row)
                                <tr class="{{ $row['is_sunday'] ? 'table-row-sunday' : '' }}">
                                    <td>
                                        @if ($row['is_sunday'])
                                            <span class="badge bg-danger-subtle text-danger fw-bold px-2 py-1">
                                                <i class="bi bi-star-fill me-1" style="font-size:0.6rem;"></i> {{ $row['day_name'] }}
                                            </span>
                                        @else
                                            <span class="fw-medium">{{ $row['day_name'] }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="{{ $row['is_today'] ?? false ? 'fw-bold text-accent' : '' }}">
                                            {{ $row['date_formatted'] }}
                                        </span>
                                        @if ($row['is_today'] ?? false)
                                            <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:0.68rem;">Hari Ini</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($row['masuk'] > 0)
                                            <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                                                {{ $row['masuk'] }} Unit
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($row['selesai'] > 0)
                                            <span class="badge bg-info-subtle text-info fw-semibold px-2 py-1">
                                                {{ $row['selesai'] }} Unit
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($row['keluar'] > 0)
                                            <span class="badge bg-warning-subtle text-warning fw-semibold px-2 py-1">
                                                {{ $row['keluar'] }} Unit
                                            </span>
                                        @else
                                            <span class="text-muted opacity-50">-</span>
                                        @endif
                                    </td>
                                    <td style="text-align: right;" class="fw-semibold text-heading">
                                        {{ $row['pemasukan'] > 0 ? $rp($row['pemasukan']) : 'Rp 0' }}
                                    </td>
                                    <td style="text-align: right;" class="text-muted">
                                        {{ $row['modal'] > 0 ? $rp($row['modal']) : 'Rp 0' }}
                                    </td>
                                    <td style="text-align: right;" class="fw-bold {{ $row['profit'] > 0 ? 'text-success' : 'text-muted' }}">
                                        {{ $row['profit'] > 0 ? $rp($row['profit']) : 'Rp 0' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td>Laporan Servis Bulan Ini</td>
                                <td style="text-align: center;" class="text-primary">{{ number_format($data['totals']['masuk']) }} Unit</td>
                                <td style="text-align: center;" class="text-info">{{ number_format($data['totals']['selesai']) }} Unit</td>
                                <td style="text-align: center;" class="text-warning">{{ number_format($data['totals']['keluar']) }} Unit</td>
                                <td style="text-align: right;" class="text-heading">{{ $rp($data['totals']['pemasukan']) }}</td>
                                <td style="text-align: right;" class="text-muted">{{ $rp($data['totals']['modal']) }}</td>
                                <td style="text-align: right;" class="text-success">{{ $rp($data['totals']['profit']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/service-report.css') }}">
@endpush

@push('js')
    <script>
        function handleModeChange(mode) {
            const monthField = document.getElementById('wrapperMonthField');
            if (monthField) {
                monthField.style.display = (mode === 'yearly') ? 'none' : 'block';
            }
        }
    </script>
@endpush
