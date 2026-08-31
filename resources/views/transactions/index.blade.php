@extends('layouts.app')

@section('title', 'Transaksi Penjualan')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Transaksi Penjualan'],
        ],
        'title' => 'Transaksi Penjualan',
        'subtitle' => 'Penjualan barang, servis jasa, dan transaksi gabungan.',
    ])

    <div class="data-panel">
        <div class="data-panel-head data-panel-head-row">
            <h2 class="data-panel-title">Riwayat Penjualan</h2>
            @can('transaction create')
                <a href="{{ route('transactions.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Transaksi Baru
                </a>
            @endcan
        </div>
        <div class="data-panel-body">
            <div class="list-filter mb-3">
                <div class="ticket-filter-extras">
                    <div>
                        <label class="form-label small mb-1" for="filter-from">Dari tanggal</label>
                        <input type="date" id="filter-from" class="form-control form-control-clean"
                            value="{{ $filters['from'] }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="filter-to">Sampai tanggal</label>
                        <input type="date" id="filter-to" class="form-control form-control-clean"
                            value="{{ $filters['to'] }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="filter-technician">Teknisi</label>
                        <select id="filter-technician" class="form-select form-control-clean">
                            <option value="">Semua teknisi</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ticket-filter-reset-wrap">
                        <button type="button" class="btn btn-light-action w-100" id="btn-reset-list-filter">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="data-table" width="100%">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No. Transaksi</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th>Pelanggan</th>
                            <th>Teknisi</th>
                            <th>Total</th>
                            <th>Metode Bayar</th>
                            <th>Komisi Teknisi</th>
                            <th>Kasir</th>
                            <th>Waktu</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" data-bs-backdrop="static" id="show-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content tx-detail-modal">
                <div class="modal-header tx-detail-modal__header">
                    <div>
                        <p class="tx-detail-modal__eyebrow mb-0">Detail Transaksi</p>
                        <h5 class="modal-title tx-detail-modal__title mb-0" id="tx-detail-modal-title">—</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body tx-detail-modal__body"></div>
                <div class="modal-footer tx-detail-modal__footer">
                    <a href="#" id="btn-print-invoice" class="btn btn-primary d-none" target="_blank">
                        <i class="bi bi-printer"></i> Cetak Invoice
                    </a>
                    <button type="button" class="btn btn-light-action" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.1.8/datatables.min.css">
@endpush

@push('js')
    <script src="{{ asset('js/transaction-detail.js') }}?v={{ filemtime(public_path('js/transaction-detail.js')) }}"></script>
    <script>
        const txDefaultFrom = @json($filters['from']);
        const txDefaultTo = @json($filters['to']);

        const txTable = $('#data-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('transactions.index') }}',
                data: function (d) {
                    d.from = $('#filter-from').val();
                    d.to = $('#filter-to').val();
                    d.technician_id = $('#filter-technician').val();
                },
            },
            order: [[10, 'desc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'transaction_no', name: 'transaction_no' },
                { data: 'type_label', name: 'type', orderable: false },
                { data: 'status_label', name: 'status', orderable: false, searchable: false },
                { data: 'customer_name', name: 'customer.name', orderable: false },
                { data: 'technician_name', name: 'technician.name', orderable: false },
                { data: 'total_fmt', name: 'total', orderable: false, searchable: false },
                { data: 'payment_label', name: 'payment_method', orderable: false },
                { data: 'commission_fmt', name: 'technician_commission', orderable: false, searchable: false },
                { data: 'user_name', name: 'user.name', orderable: false },
                { data: 'created_at', name: 'created_at' },
                { data: 'action', orderable: false, searchable: false, className: 'text-end' },
            ],
        });

        $('#filter-from, #filter-to, #filter-technician').on('change', function () {
            txTable.ajax.reload();
        });

        $('#btn-reset-list-filter').on('click', function () {
            $('#filter-from').val(txDefaultFrom);
            $('#filter-to').val(txDefaultTo);
            $('#filter-technician').val('');
            txTable.ajax.reload();
        });

        AthaTransactionDetail.init({
            table: '#data-table',
            showModal: '#show-modal',
            showUrl: '{{ route('transactions.show', '__ID__') }}',
            invoiceUrl: '{{ route('transactions.invoice', '__ID__') }}',
            cancelUrlTemplate: '{{ route('transactions.destroy', '__ID__') }}',
            canCancel: @json(auth()->user()->can('transaction delete')),
            techPercent: {{ (int) config('workshop.default_technician_commission_percent', 20) }},
            ownerPercent: {{ 100 - (int) config('workshop.default_technician_commission_percent', 20) }},
        });
    </script>
@endpush
