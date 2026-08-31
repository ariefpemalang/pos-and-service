@extends('layouts.app')

@section('title', 'Pembelian Barang')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Pembelian Barang'],
        ],
        'title' => 'Pembelian Barang',
        'subtitle' => 'Catat pembelian sparepart HP & laptop — stok masuk & pengeluaran otomatis.',
    ])

    <div class="data-panel">
        <div class="data-panel-head data-panel-head-row">
            <h2 class="data-panel-title">Riwayat Pembelian</h2>
            @can('purchase create')
                <a href="{{ route('purchases.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Pembelian Baru
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
                        <label class="form-label small mb-1" for="filter-supplier">Supplier</label>
                        <select id="filter-supplier" class="form-select form-control-clean">
                            <option value="">Semua supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
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
                            <th>No. Pembelian</th>
                            <th>Status</th>
                            <th>Supplier</th>
                            <th>Total Pengeluaran</th>
                            <th>Metode Bayar</th>
                            <th>Petugas</th>
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
            <div class="modal-content modal-content-clean">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pembelian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.1.8/datatables.min.css">
@endpush

@push('js')
    <script src="{{ asset('js/purchase-detail.js') }}"></script>
    <script>
        const purchaseDefaultFrom = @json($filters['from']);
        const purchaseDefaultTo = @json($filters['to']);

        const purchaseTable = $('#data-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('purchases.index') }}',
                data: function (d) {
                    d.from = $('#filter-from').val();
                    d.to = $('#filter-to').val();
                    d.supplier_id = $('#filter-supplier').val();
                },
            },
            order: [[7, 'desc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'purchase_no', name: 'purchase_no' },
                { data: 'status_label', name: 'status', orderable: false, searchable: false },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
                { data: 'total_fmt', name: 'total', orderable: false, searchable: false },
                { data: 'payment_label', name: 'payment_method', orderable: false },
                { data: 'user_name', name: 'user.name', orderable: false },
                { data: 'created_at', name: 'created_at' },
                { data: 'action', orderable: false, searchable: false, className: 'text-end' },
            ],
        });

        $('#filter-from, #filter-to, #filter-supplier').on('change', function () {
            purchaseTable.ajax.reload();
        });

        $('#btn-reset-list-filter').on('click', function () {
            $('#filter-from').val(purchaseDefaultFrom);
            $('#filter-to').val(purchaseDefaultTo);
            $('#filter-supplier').val('');
            purchaseTable.ajax.reload();
        });

        AthaPurchaseDetail.init({
            table: '#data-table',
            showModal: '#show-modal',
            showUrl: '{{ route('purchases.show', '__ID__') }}',
            cancelUrlTemplate: '{{ route('purchases.destroy', '__ID__') }}',
            canCancel: @json(auth()->user()->can('purchase delete')),
        });
    </script>
@endpush
