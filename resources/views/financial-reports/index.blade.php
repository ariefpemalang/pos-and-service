@extends('layouts.app')

@section('title', 'Laporan Keuangan')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Laporan Keuangan'],
        ],
        'title' => 'Laporan Keuangan',
        'subtitle' => 'Pemasukan penjualan, komisi teknisi, dan pengeluaran pembelian.',
    ])

    <div class="data-panel mb-4">
        <div class="data-panel-head">
            <div class="data-panel-icon"><i class="bi bi-funnel"></i></div>
            <div class="flex-grow-1">
                <h3 class="data-panel-title">Filter Periode</h3>
                <p class="data-panel-desc">
                    Periode aktif:
                    <strong>{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</strong>
                    —
                    <strong>{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</strong>
                </p>
            </div>
        </div>
        <div class="data-panel-body pt-3">
            @php
                $today = now()->toDateString();
                $startMonth = now()->startOfMonth()->toDateString();
                $endMonth = now()->endOfMonth()->toDateString();
                $isToday = ($from === $today && $to === $today);
                $isMonth = ($from === $startMonth && ($to === $endMonth || $to === $today));
            @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="small fw-semibold text-muted"><i class="bi bi-lightning-charge me-1"></i>Filter Cepat:</span>
                <a href="{{ route('financial-reports.index', ['from' => $today, 'to' => $today]) }}" 
                   class="btn btn-sm {{ $isToday ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi bi-calendar-event me-1"></i> Hari Ini
                </a>
                <a href="{{ route('financial-reports.index', ['from' => $startMonth, 'to' => $endMonth]) }}" 
                   class="btn btn-sm {{ $isMonth ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi bi-calendar-month me-1"></i> Bulan Ini
                </a>
            </div>
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="from" class="form-control form-control-clean" value="{{ $from }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="to" class="form-control form-control-clean" value="{{ $to }}" required>
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i> Terapkan Filter
                    </button>
                    <a href="{{ route('financial-reports.export-pdf', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                    </a>
                    <a href="{{ route('technician-reports.index') }}" class="btn btn-outline-dark">
                        <i class="bi bi-person-badge me-1"></i> Laporan Teknisi
                    </a>
                </div>
            </form>
        </div>
    </div>

    @include('financial-reports.partials.report-content-web')
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/financial-report.css') }}">
@endpush
