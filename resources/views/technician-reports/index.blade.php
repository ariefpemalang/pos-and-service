@extends('layouts.app')

@section('title', 'Laporan Teknisi')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Keuangan', 'url' => route('financial-reports.index')],
            ['label' => 'Laporan Teknisi'],
        ],
        'title' => 'Laporan Teknisi',
        'subtitle' => 'Performa teknisi: jumlah unit servis ditangani, pemasukan, dan kontribusi profit per teknisi.',
    ])

    @php
        $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $selectedMonthName = $monthsList[$month] ?? '';

        // Hitung Grand Total dari seluruh teknisi
        $grandTotalKali = 0;
        $grandTotalPemasukan = 0;
        $grandTotalProfit = 0;
        foreach ($data['technicians'] as $tech) {
            $grandTotalKali += $data['totals'][$tech->id]['kali'] ?? 0;
            $grandTotalPemasukan += $data['totals'][$tech->id]['pemasukan'] ?? 0;
            $grandTotalProfit += $data['totals'][$tech->id]['profit'] ?? 0;
        }
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
                <h3 class="data-panel-title">Filter Periode Laporan Teknisi</h3>
                <p class="data-panel-desc">
                    Tampilan aktif:
                    <strong>{{ $mode === 'monthly' ? 'Bulanan — ' . $selectedMonthName . ' ' . $year : 'Tahunan — Tahun ' . $year }}</strong>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('technician-reports.export-excel', ['mode' => $mode, 'year' => $year, 'month' => $month]) }}"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </a>
                <a href="{{ route('service-reports.index', ['mode' => $mode, 'year' => $year, 'month' => $month]) }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-tools me-1"></i> Laporan Servis
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak
                </button>
            </div>
        </div>
        <div class="data-panel-body pt-3 pb-3">
            <form method="get" action="{{ route('technician-reports.index') }}" class="row g-3 align-items-end" id="formTechReportFilter">
                {{-- Pilihan Mode --}}
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-layout-text-window-reverse me-1"></i> Mode Tampilan
                    </label>
                    <select name="mode" class="form-select form-control-clean" id="techReportModeSelect" onchange="handleTechModeChange(this.value)">
                        <option value="monthly" {{ $mode === 'monthly' ? 'selected' : '' }}>Bulanan (Rincian Per Hari)</option>
                        <option value="yearly" {{ $mode === 'yearly' ? 'selected' : '' }}>Tahunan (Rincian Per Bulan)</option>
                    </select>
                </div>

                {{-- Pilihan Bulan (Bulanan saja) --}}
                <div class="col-md-3" id="wrapperTechMonthField" style="{{ $mode === 'yearly' ? 'display:none;' : '' }}">
                    <label class="form-label small fw-semibold mb-1">
                        <i class="bi bi-calendar-month me-1"></i> Bulan
                    </label>
                    <select name="month" class="form-select form-control-clean" id="techReportMonthSelect">
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
        {{-- Total Servis Ditangani --}}
        <div class="col-6 col-xl-3">
            <div class="dash-kpi dash-kpi-primary">
                <div class="dash-kpi-icon"><i class="bi bi-tools"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">TOTAL SERVIS DITANGANI</div>
                    <div class="dash-kpi-value text-primary">{{ number_format($grandTotalKali) }} <small class="fs-6 fw-normal">Kali</small></div>
                    <div class="dash-kpi-meta">{{ $mode === 'monthly' ? 'Bulan ' . $selectedMonthName : 'Tahun ' . $year }}</div>
                </div>
            </div>
        </div>

        {{-- Total Pemasukan --}}
        <div class="col-6 col-xl-3">
            <div class="dash-kpi dash-kpi-success">
                <div class="dash-kpi-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">TOTAL PEMASUKAN TEKNISI</div>
                    <div class="dash-kpi-value" style="color:var(--accent);">{{ $rp($grandTotalPemasukan) }}</div>
                    <div class="dash-kpi-meta">Akumulasi seluruh teknisi</div>
                </div>
            </div>
        </div>

        {{-- Total Profit --}}
        <div class="col-6 col-xl-3">
            <div class="dash-kpi dash-kpi-info">
                <div class="dash-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">TOTAL PROFIT TEKNISI</div>
                    <div class="dash-kpi-value text-success">{{ $rp($grandTotalProfit) }}</div>
                    <div class="dash-kpi-meta">Margin kotor teknisi</div>
                </div>
            </div>
        </div>

        {{-- Jumlah Teknisi --}}
        <div class="col-6 col-xl-3">
            <div class="dash-kpi dash-kpi-warning">
                <div class="dash-kpi-icon"><i class="bi bi-people"></i></div>
                <div class="dash-kpi-body">
                    <div class="dash-kpi-label">TEKNISI TERDAFTAR</div>
                    <div class="dash-kpi-value text-warning">{{ $data['technicians']->count() }} <small class="fs-6 fw-normal">Orang</small></div>
                    <div class="dash-kpi-meta">Tim teknisi aktif</div>
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
                <i class="bi bi-people"></i>
            </div>
            <div class="flex-grow-1">
                <h2 class="data-panel-title">
                    {{ $mode === 'monthly' ? 'Performa Teknisi Harian — Bulan ' . $selectedMonthName . ' ' . $year : 'Performa Teknisi Bulanan — Tahun ' . $year }}
                </h2>
                <p class="data-panel-desc">
                    Frekuensi penanganan servis, omzet yang dihasilkan, dan profit per teknisi.
                </p>
            </div>
            <div class="d-none d-md-flex flex-wrap gap-2 align-items-center">
                <span class="ar-summary-pill ar-summary-pill-primary">
                    <i class="bi bi-tools"></i> Total: <strong>{{ number_format($grandTotalKali) }} Kali</strong>
                </span>
                <span class="ar-summary-pill ar-summary-pill-success">
                    <i class="bi bi-cash-stack"></i> Omzet: <strong>{{ $rp($grandTotalPemasukan) }}</strong>
                </span>
                <span class="ar-summary-pill ar-summary-pill-info">
                    <i class="bi bi-graph-up-arrow"></i> Profit: <strong>{{ $rp($grandTotalProfit) }}</strong>
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
                                @foreach ($data['technicians'] as $tech)
                                    <th style="text-align: center;">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-person-circle text-muted"></i>
                                            <span>{{ $tech->name }}</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['rows'] as $row)
                                <tr class="{{ $row['is_current'] ? 'table-row-current' : '' }}">
                                    <td style="text-align: center;" class="text-muted">{{ $row['year'] }}</td>
                                    <td>
                                        <a href="{{ route('technician-reports.index', ['mode' => 'monthly', 'year' => $row['year'], 'month' => $row['month_num']]) }}"
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
                                    @foreach ($data['technicians'] as $tech)
                                        @php
                                            $count = $row['tech_data'][$tech->id]['count'] ?? 0;
                                        @endphp
                                        <td style="text-align: center;">
                                            @if ($count > 0)
                                                <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                                                    {{ $count }} Kali
                                                </span>
                                            @else
                                                <span class="text-muted opacity-50">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- Baris 1: Menangani Servis --}}
                            <tr>
                                <td style="text-align: center;">Total</td>
                                <td>Menangani Servis Tahun {{ $year }}</td>
                                @foreach ($data['technicians'] as $tech)
                                    <td style="text-align: center;" class="text-primary fw-bold">
                                        {{ number_format($data['totals'][$tech->id]['kali'] ?? 0) }} Kali
                                    </td>
                                @endforeach
                            </tr>
                            {{-- Baris 2: Pemasukan --}}
                            <tr>
                                <td style="text-align: center;">Total</td>
                                <td>Pemasukan Oleh Teknisi</td>
                                @foreach ($data['technicians'] as $tech)
                                    <td style="text-align: right;" class="text-heading fw-semibold">
                                        {{ $rp($data['totals'][$tech->id]['pemasukan'] ?? 0) }}
                                    </td>
                                @endforeach
                            </tr>
                            {{-- Baris 3: Profit --}}
                            <tr class="tfoot-profit">
                                <td style="text-align: center;">Total</td>
                                <td>Profit Oleh Teknisi</td>
                                @foreach ($data['technicians'] as $tech)
                                    <td style="text-align: right;" class="text-success fw-bold">
                                        {{ $rp($data['totals'][$tech->id]['profit'] ?? 0) }}
                                    </td>
                                @endforeach
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
                                @foreach ($data['technicians'] as $tech)
                                    <th style="text-align: center;">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-person-circle text-muted"></i>
                                            <span>{{ $tech->name }}</span>
                                        </div>
                                    </th>
                                @endforeach
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
                                    @foreach ($data['technicians'] as $tech)
                                        @php
                                            $count = $row['tech_data'][$tech->id]['count'] ?? 0;
                                        @endphp
                                        <td style="text-align: center;">
                                            @if ($count > 0)
                                                <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                                                    {{ $count }} Kali
                                                </span>
                                            @else
                                                <span class="text-muted opacity-50">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- Baris 1: Menangani Servis --}}
                            <tr>
                                <td>Total</td>
                                <td>Menangani Servis Bulan Ini</td>
                                @foreach ($data['technicians'] as $tech)
                                    <td style="text-align: center;" class="text-primary fw-bold">
                                        {{ number_format($data['totals'][$tech->id]['kali'] ?? 0) }} Kali
                                    </td>
                                @endforeach
                            </tr>
                            {{-- Baris 2: Pemasukan --}}
                            <tr>
                                <td>Total</td>
                                <td>Pemasukan Oleh Teknisi</td>
                                @foreach ($data['technicians'] as $tech)
                                    <td style="text-align: right;" class="text-heading fw-semibold">
                                        {{ $rp($data['totals'][$tech->id]['pemasukan'] ?? 0) }}
                                    </td>
                                @endforeach
                            </tr>
                            {{-- Baris 3: Profit --}}
                            <tr class="tfoot-profit">
                                <td>Total</td>
                                <td>Profit Oleh Teknisi</td>
                                @foreach ($data['technicians'] as $tech)
                                    <td style="text-align: right;" class="text-success fw-bold">
                                        {{ $rp($data['totals'][$tech->id]['profit'] ?? 0) }}
                                    </td>
                                @endforeach
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
        function handleTechModeChange(mode) {
            const monthField = document.getElementById('wrapperTechMonthField');
            if (monthField) {
                monthField.style.display = (mode === 'yearly') ? 'none' : 'block';
            }
        }
    </script>
@endpush
