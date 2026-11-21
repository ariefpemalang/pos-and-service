@extends('layouts.app')

@section('title', 'Tiket Servis')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Tiket Servis'],
        ],
        'title' => 'Tiket Servis',
        'subtitle' => 'Penerimaan HP/laptop nginep, update status, dan lacak oleh pelanggan.',
    ])

    <div class="data-panel">
        <div class="data-panel-head data-panel-head-row">
            <h2 class="data-panel-title">Daftar Tiket</h2>
            @can('service ticket create')
                <a href="{{ route('service-tickets.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Terima Perangkat
                </a>
            @endcan
        </div>
        <div class="data-panel-body">
            <div class="ticket-filter">
                <div class="ticket-filter-chips" role="tablist" aria-label="Filter status tiket">
                    @foreach ($statusFilters as $item)
                        @php
                            $count = $item['key'] === ''
                                ? $summary['all']
                                : ($summary['statuses'][$item['key']] ?? 0);
                            $isActive = ($filters['status'] ?? '') === $item['key'];
                        @endphp
                        <button type="button"
                            class="ticket-chip ticket-chip--{{ $item['color'] }} {{ $isActive ? 'is-active' : '' }}"
                            data-status="{{ $item['key'] }}"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}">
                            <span class="ticket-chip-dot"></span>
                            <span class="ticket-chip-label">{{ $item['label'] }}</span>
                            <span class="ticket-chip-count" data-count-for="{{ $item['key'] === '' ? 'all' : $item['key'] }}">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="ticket-filter-extras">
                    <div>
                        <label class="form-label small mb-1" for="filter-technician">Teknisi</label>
                        <select id="filter-technician" class="form-select form-control-clean">
                            <option value="">Semua teknisi</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected((string) ($filters['technician_id'] ?? '') === (string) $technician->id)>
                                    {{ $technician->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="filter-device">Jenis perangkat</label>
                        <select id="filter-device" class="form-select form-control-clean">
                            <option value="">Semua jenis</option>
                            @foreach ($deviceTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['device_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1" for="filter-warranty">Garansi</label>
                        <select id="filter-warranty" class="form-select form-control-clean">
                            <option value="" @selected(($filters['warranty'] ?? '') === '')>Semua garansi</option>
                            <option value="pending" @selected(($filters['warranty'] ?? '') === 'pending')>Menunggu selesai</option>
                            <option value="active" @selected(($filters['warranty'] ?? '') === 'active')>Aktif</option>
                            <option value="expired" @selected(($filters['warranty'] ?? '') === 'expired')>Habis</option>
                            <option value="none" @selected(($filters['warranty'] ?? '') === 'none')>Tanpa garansi</option>
                        </select>
                    </div>
                    <div class="ticket-filter-reset-wrap">
                        <button type="button" class="btn btn-light-action w-100" id="btn-reset-ticket-filter">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                <label class="form-label small fw-semibold text-muted mb-0 text-nowrap" for="filter-sort">
                    <i class="bi bi-sort-down me-1"></i> Urutkan Berdasarkan:
                </label>
                <select id="filter-sort" name="sort" class="form-select form-select-sm form-control-clean" style="width: auto; min-width: 190px;">
                    <option value="tanggal_desc" @selected(($filters['sort'] ?? request('sort', 'tanggal_desc')) === 'tanggal_desc')>Tanggal Terbaru</option>
                    <option value="tanggal_asc" @selected(($filters['sort'] ?? request('sort')) === 'tanggal_asc')>Tanggal Terlama</option>
                    <option value="status" @selected(($filters['sort'] ?? request('sort')) === 'status')>Status</option>
                    <option value="pelanggan_asc" @selected(($filters['sort'] ?? request('sort')) === 'pelanggan_asc')>Nama Pelanggan A-Z</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="data-table" width="100%">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No. Tiket</th>
                            <th>Pelanggan</th>
                            <th>Perangkat</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th>Teknisi</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.1.8/datatables.min.css">
@endpush

@push('js')
    <script>
        (function ($) {
            const table = $('#data-table').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: '{{ route('service-tickets.index', [], false) }}',
                    data: function (d) {
                        d.status = $('.ticket-chip.is-active').data('status') || '';
                        d.technician_id = $('#filter-technician').val();
                        d.device_type = $('#filter-device').val();
                        d.warranty = $('#filter-warranty').val();
                        d.sort = $('#filter-sort').val();
                    },
                    dataSrc: function (json) {
                        if (json.summary) {
                            updateChipCounts(json.summary);
                        }
                        return json.data;
                    },
                },
                order: [],
                columns: [
                    { data: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'ticket_no', name: 'ticket_no' },
                    { data: 'customer_name', name: 'customer.name', orderable: false },
                    { data: 'device', orderable: false, searchable: false },
                    { data: 'complaint', name: 'complaint', orderable: false },
                    { data: 'status_label', name: 'status', orderable: false, searchable: false },
                    { data: 'technician_name', name: 'technician.name', orderable: false },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'completed_at', name: 'completed_at', orderable: false, visible: ('{{ $filters['status'] }}' === 'completed') },
                    { data: 'action', orderable: false, searchable: false, className: 'text-end' },
                ],
            });

            function updateChipCounts(summary) {
                $('[data-count-for="all"]').text(summary.all ?? 0);
                $('[data-count-for="open"]').text(summary.open ?? 0);
                $.each(summary.statuses || {}, function (key, total) {
                    $('[data-count-for="' + key + '"]').text(total);
                });
            }

            function updateKeluarColumn(status) {
                const isCompleted = (status === 'completed');
                table.column(8).visible(isCompleted, false);
            }

            function setActiveChip(status) {
                $('.ticket-chip').removeClass('is-active').attr('aria-pressed', 'false');
                const $chip = $('.ticket-chip[data-status="' + status + '"]');
                if ($chip.length) {
                    $chip.addClass('is-active').attr('aria-pressed', 'true');
                } else {
                    $('.ticket-chip').first().addClass('is-active').attr('aria-pressed', 'true');
                }
                updateKeluarColumn(status);
            }

            $('.ticket-chip').on('click', function () {
                setActiveChip($(this).data('status') || '');
                table.ajax.reload();
            });

            $('#filter-technician, #filter-device, #filter-warranty, #filter-sort').on('change', function () {
                table.ajax.reload();
            });

            $('#btn-reset-ticket-filter').on('click', function () {
                setActiveChip('received');
                $('#filter-technician, #filter-device, #filter-warranty').val('');
                $('#filter-sort').val('tanggal_desc');
                table.ajax.reload();
            });
        })(jQuery);
    </script>
@endpush
