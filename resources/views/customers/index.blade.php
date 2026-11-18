@extends('layouts.app')

@section('title', 'Data Pelanggan')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Pelanggan'],
        ],
        'title' => 'Data Pelanggan',
        'subtitle' => 'Kelola data pelanggan service smartphone & laptop.',
    ])

    <div class="data-panel">
        <div class="data-panel-head data-panel-head-row">
            <h2 class="data-panel-title">Daftar Pelanggan</h2>
            @can('customer create')
                <button type="button" class="btn btn-primary btn-add" data-action="create">
                    <i class="bi bi-plus-lg"></i> Tambah
                </button>
            @endcan
        </div>
        <div class="data-panel-body">
            <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                <label class="form-label small fw-semibold text-muted mb-0 text-nowrap" for="filter-sort">
                    <i class="bi bi-sort-down me-1"></i> Urutkan Berdasarkan:
                </label>
                <select id="filter-sort" name="sort" class="form-select form-select-sm form-control-clean" style="width: auto; min-width: 190px;">
                    <option value="nama_asc" @selected(($sort ?? request('sort')) === 'nama_asc')>Nama A-Z</option>
                    <option value="nama_desc" @selected(($sort ?? request('sort')) === 'nama_desc')>Nama Z-A</option>
                    <option value="tanggal_desc" @selected(($sort ?? request('sort', 'tanggal_desc')) === 'tanggal_desc')>Tanggal Terbaru</option>
                    <option value="tanggal_asc" @selected(($sort ?? request('sort')) === 'tanggal_asc')>Tanggal Terlama</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="data-table" width="100%">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Telepon</th>
                            <th>Email</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" data-bs-backdrop="static" id="form-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-clean">
                <form id="crud-form" method="post" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" data-modal-title>Tambah Pelanggan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Kode Pelanggan <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control form-control-clean" maxlength="30" required>
                                <div class="form-hint-sm">Otomatis terisi saat tambah baru; bisa diubah. Kode harus unik.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-clean" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telepon</label>
                                <input type="text" name="phone" class="form-control form-control-clean">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control form-control-clean">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" class="form-control form-control-clean" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan</label>
                                <textarea name="notes" class="form-control form-control-clean" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" data-bs-backdrop="static" id="show-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-clean">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pelanggan</h5>
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
    <script>
        const customerTable = $('#data-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('customers.index') }}',
                data: function (d) {
                    d.sort = $('#filter-sort').val();
                }
            },
            order: [],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'code', name: 'code' },
                { data: 'name', name: 'name' },
                { data: 'phone', name: 'phone' },
                { data: 'email', name: 'email' },
                { data: 'created_at', name: 'created_at' },
                { data: 'action', orderable: false, searchable: false, className: 'text-end' },
            ],
        });

        $('#filter-sort').on('change', function () {
            customerTable.ajax.reload();
        });

        AthaModalCrud.init({
            storeUrl: '{{ route('customers.store') }}',
            updateUrlTemplate: '{{ route('customers.update', '__ID__') }}',
            showUrlTemplate: '{{ route('customers.show', '__ID__') }}',
            destroyUrlTemplate: '{{ route('customers.destroy', '__ID__') }}',
            entityName: 'Pelanggan',
            onFormReset: function (mode) {
                if (mode === 'create') {
                    $.get('{{ route('entity-codes.suggest') }}', { type: 'customer' }, function (res) {
                        $('input[name="code"]').val(res.code);
                    });
                }
            },
            renderShow: function (d) {
                return `
                    <dl class="detail-list mb-0">
                        <dt>Kode</dt><dd>${d.code}</dd>
                        <dt>Nama</dt><dd>${d.name}</dd>
                        <dt>Telepon</dt><dd>${d.phone || '-'}</dd>
                        <dt>Email</dt><dd>${d.email || '-'}</dd>
                        <dt>Alamat</dt><dd>${d.address || '-'}</dd>
                        <dt>Catatan</dt><dd>${d.notes || '-'}</dd>
                        <dt>Dibuat</dt><dd>${new Date(d.created_at).toLocaleString('id-ID')}</dd>
                    </dl>`;
            },
        });
    </script>
@endpush
