@extends('layouts.app')

@section('title', $isSuperAdmin ? 'Stock Opname & Audit Kehilangan' : 'Lembar Hitung Stok Fisik')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Inventory', 'url' => route('items.index')],
            ['label' => 'Stock Opname'],
        ],
        'title' => $isSuperAdmin ? 'Stock Opname (Audit Stok & Kehilangan)' : 'Lembar Input Hitung Fisik Toko',
        'subtitle' => $isSuperAdmin 
            ? 'Cocokkan stok fisik nyata dengan sistem, deteksi barang hilang/bocor, dan sesuaikan stok dengan persetujuan Owner.' 
            : 'Hitung jumlah fisik nyata barang di etalase/rak, masukkan angkanya, dan simpan untuk ditinjau oleh Owner.',
    ])

    @php
        $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    @endphp

    {{-- BANNER NOTIFIKASI --}}
    @if ($isSuperAdmin)
        {{-- BANNER KHUSUS SUPER ADMIN --}}
        @if ($pendingOpname)
            <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print" style="background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#fde047;border-radius:10px;">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-clock-history fs-1 text-warning"></i>
                    <div>
                        <div class="fw-bold fs-6 text-warning">
                            Pengajuan Stock Opname Menunggu Persetujuan Owner ({{ $pendingOpname->opname_number }})
                        </div>
                        <div class="small text-light">
                            Diajukan oleh: <strong>{{ $pendingOpname->creator?->name ?? 'Kasir' }}</strong> pada {{ $pendingOpname->created_at->format('d M Y, H:i') }}.
                            Hasil hitungan fisik: <strong class="text-danger">{{ $pendingOpname->total_loss_units }} unit terindikasi hilang (Estimasi Kerugian: {{ $rp($pendingOpname->total_loss_cost) }})</strong>, {{ $pendingOpname->total_gain_units }} unit lebih.
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn-reject-draft" data-id="{{ $pendingOpname->id }}">
                        <i class="bi bi-x-circle me-1"></i> Tolak & Minta Hitung Ulang
                    </button>
                </div>
            </div>
        @endif
    @else
        {{-- BANNER KHUSUS KASIR (TIDAK ADA DATA HILANG / KERUGIAN) --}}
        <div class="alert alert-info border-info d-flex align-items-center mb-4 flex-wrap gap-2 no-print" style="background:rgba(6,182,212,0.1);border:1px solid rgba(6,182,212,0.25);color:#67e8f9;border-radius:10px;">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-clipboard2-check fs-2 text-info"></i>
                <div>
                    <div class="fw-bold fs-6 text-info">Lembar Input Penghitungan Stok Fisik</div>
                    <div class="small text-light">
                        Silakan hitung barang secara riil di toko/rak. Masukkan jumlah fisik pada kolom <strong>Stok Fisik Nyata</strong>, lalu klik tombol <strong>Simpan Hitungan Fisik</strong> di bawah.
                        @if ($pendingOpname)
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-check-circle me-1"></i>Draft hitungan sebelumnya telah tersimpan</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- KPI CARDS AUDIT (HANYA BISA DILIHAT SUPER ADMIN / OWNER) --}}
    @if ($isSuperAdmin)
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="dash-kpi dash-kpi-primary">
                    <div class="dash-kpi-icon"><i class="bi bi-boxes"></i></div>
                    <div class="dash-kpi-body">
                        <div class="dash-kpi-label">TOTAL BARANG TERDAFTAR</div>
                        <div class="dash-kpi-value text-primary">{{ number_format($totalItems) }}</div>
                        <div class="dash-kpi-meta">{{ number_format($totalSystemStock) }} total unit fisik tercatat</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="dash-kpi dash-kpi-warning">
                    <div class="dash-kpi-icon"><i class="bi bi-wallet2"></i></div>
                    <div class="dash-kpi-body">
                        <div class="dash-kpi-label">TOTAL NILAI ASET (HPP)</div>
                        <div class="dash-kpi-value text-warning">{{ $rp($totalAssetCost) }}</div>
                        <div class="dash-kpi-meta">Berdasarkan stok sistem saat ini</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="dash-kpi dash-kpi-danger">
                    <div class="dash-kpi-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="dash-kpi-body">
                        <div class="dash-kpi-label">BARANG HILANG / SELISIH (-)</div>
                        <div class="dash-kpi-value text-danger" id="summary-loss-units">0 unit</div>
                        <div class="dash-kpi-meta" id="summary-loss-items">0 jenis barang terindikasi hilang</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="dash-kpi dash-kpi-danger">
                    <div class="dash-kpi-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;"><i class="bi bi-cash-stack"></i></div>
                    <div class="dash-kpi-body">
                        <div class="dash-kpi-label">ESTIMASI KERUGIAN (HPP)</div>
                        <div class="dash-kpi-value text-danger" id="summary-loss-cost">Rp 0</div>
                        <div class="dash-kpi-meta" id="summary-gain-info">Kelebihan: 0 unit (+Rp 0)</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- FILTER & TOOLBAR --}}
    <div class="data-panel mb-4 no-print">
        <div class="data-panel-head">
            <div class="data-panel-icon"><i class="bi bi-clipboard-check"></i></div>
            <div class="flex-grow-1">
                <h3 class="data-panel-title">
                    {{ $isSuperAdmin ? 'Lembar Kerja Stock Opname' : 'Form Input Fisik Barang' }}
                </h3>
                <p class="data-panel-desc">
                    {{ $isSuperAdmin ? 'Ketik jumlah fisik nyata di kolom Stok Fisik. Sistem otomatis menghitung selisih dan kerugian modal.' : 'Isi jumlah barang fisik yang tersedia di rak/gudang pada kolom Stok Fisik Nyata.' }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('stock-opnames.export', request()->query()) }}" class="btn btn-sm btn-outline-success" id="btn-export-opname" title="Cetak / Unduh Format Excel .xlsx">
                    <i class="bi bi-file-earmark-excel me-1"></i> Cetak Lembar (.xlsx)
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Cetak ke Printer">
                    <i class="bi bi-printer"></i>
                </button>
            </div>
        </div>
        <div class="data-panel-body">
            <div class="row g-2 align-items-center">
                {{-- Filter Kategori --}}
                <div class="col-md-3">
                    <select id="filter-category" class="form-select form-select-sm form-control-clean">
                        <option value="">-- Semua Kategori --</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Cari Barang --}}
                <div class="col-md-{{ $isSuperAdmin ? '4' : '9' }}">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-secondary-subtle" style="color:var(--admin-text-muted)"><i class="bi bi-search"></i></span>
                        <input type="text" id="filter-search" class="form-control form-control-clean" placeholder="Cari nama atau kode barang..." value="{{ request('search') }}">
                    </div>
                </div>

                {{-- Filter Tab Tampilan (HANYA MUNCUL DI SUPER ADMIN) --}}
                @if ($isSuperAdmin)
                    <div class="col-md-5 d-flex justify-content-md-end gap-1 flex-wrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-sm ar-status-filter active" data-filter="all">Semua (<span id="count-all">{{ $items->count() }}</span>)</button>
                            <button type="button" class="btn btn-sm ar-status-filter text-danger" data-filter="loss">🔴 Hilang (<span id="count-loss">0</span>)</button>
                            <button type="button" class="btn btn-sm ar-status-filter text-primary" data-filter="gain">🔵 Lebih (<span id="count-gain">0</span>)</button>
                            <button type="button" class="btn btn-sm ar-status-filter text-success" data-filter="match">🟢 Klop (<span id="count-match">{{ $items->count() }}</span>)</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- TABEL OPNAME --}}
    <div class="data-panel mb-4">
        <div class="data-panel-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 ar-table" id="opname-table">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 40px;">No</th>
                            <th style="min-width: 160px;">Nama & Kode Barang</th>
                            <th style="width: 140px;">Kategori</th>
                            
                            {{-- KOLOM HPP & STOK SISTEM (HANYA SUPER ADMIN) --}}
                            @if ($isSuperAdmin)
                                <th class="text-end" style="width: 120px;">HPP Modal</th>
                                <th class="text-center" style="width: 110px;">Stok Sistem</th>
                            @endif

                            {{-- KOLOM INPUT FISIK (DIISI KASIR / OWNER) --}}
                            <th class="text-center" style="width: 140px;">Stok Fisik Nyata</th>

                            {{-- KOLOM SELISIH & KERUGIAN (HANYA SUPER ADMIN) --}}
                            @if ($isSuperAdmin)
                                <th class="text-center" style="width: 120px;">Selisih</th>
                                <th class="text-end" style="width: 140px;">Nilai Kerugian / Lebih</th>
                            @endif

                            <th class="pe-3 no-print" style="min-width: 160px;">Catatan Kondisi Fisik</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $idx => $it)
                            @php
                                $sysStock = (int) $it->stock;
                                $cost = (float) $it->purchase_price;

                                // Nilai input awal:
                                // Untuk Super Admin: jika ada draft pakai draft, jika tidak pakai sistem.
                                // Untuk Kasir (Blind Audit): jika sudah pernah simpan draft, pakai draft. Jika belum, biarkan kosong agar kasir wajib menghitung nyata!
                                if (isset($draftItemsMap[$it->id])) {
                                    $initialVal = $draftItemsMap[$it->id]['physical_stock'];
                                    $initialNotes = $draftItemsMap[$it->id]['notes'];
                                } else {
                                    $initialVal = $isSuperAdmin ? $sysStock : '';
                                    $initialNotes = '';
                                }
                            @endphp
                            <tr class="opname-row"
                                data-id="{{ $it->id }}"
                                data-name="{{ strtolower($it->name) }}"
                                data-code="{{ strtolower($it->code) }}"
                                data-category-id="{{ $it->category_id }}"
                                data-system-stock="{{ $sysStock }}"
                                data-cost="{{ $cost }}">
                                
                                <td class="ps-3 text-muted row-number">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-bold" style="color:var(--admin-text-heading);">{{ $it->name }}</div>
                                    <span class="badge font-monospace text-muted py-0 px-1" style="background:var(--admin-surface-2);font-size:0.7rem;border:1px solid var(--admin-border)">{{ $it->code }}</span>
                                </td>
                                <td>
                                    <span class="badge py-1 px-2" style="background:rgba(99,102,241,0.12);color:#818cf8;border:1px solid rgba(99,102,241,0.25);font-size:0.72rem;">
                                        {{ $it->category?->name ?? 'Tanpa Kategori' }}
                                    </span>
                                </td>

                                {{-- DATA RAHASIA: HPP & STOK SISTEM (HANYA SUPER ADMIN) --}}
                                @if ($isSuperAdmin)
                                    <td class="text-end font-monospace" style="color:var(--admin-text-heading);">
                                        {{ $rp($cost) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge py-1 px-2 fs-6 fw-bold text-light" style="background:var(--admin-surface-2);border:1px solid var(--admin-border);">
                                            {{ number_format($sysStock) }} <small class="fw-normal text-muted">{{ $it->unit?->abbreviation ?? 'pcs' }}</small>
                                        </span>
                                    </td>
                                @endif

                                {{-- KOLOM INPUT FISIK --}}
                                <td class="text-center">
                                    <div class="input-group input-group-sm justify-content-center mx-auto" style="max-width: 110px;">
                                        <input type="number" 
                                            class="form-control form-control-sm text-center fw-bold physical-input"
                                            value="{{ $initialVal }}" 
                                            placeholder="0"
                                            min="0"
                                            style="background:var(--admin-surface);border:1px solid var(--admin-border);color:var(--accent-bright);font-size:0.95rem;">
                                    </div>
                                </td>

                                {{-- DATA RAHASIA: SELISIH & KERUGIAN (HANYA SUPER ADMIN) --}}
                                @if ($isSuperAdmin)
                                    <td class="text-center diff-cell">
                                        <span class="badge bg-secondary-subtle text-secondary py-1 px-2">✓ Klop (0)</span>
                                    </td>
                                    <td class="text-end font-monospace diff-cost-cell" style="color:var(--admin-text-muted);">
                                        Rp 0
                                    </td>
                                @endif

                                <td class="pe-3 no-print">
                                    <input type="text" class="form-control form-control-sm notes-input form-control-clean" placeholder="Misal: Dus sobek, rusak..." value="{{ $initialNotes }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isSuperAdmin ? '9' : '5' }}" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Tidak ada data barang aktif ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        {{-- BOTTOM BAR TOMBOL SIMPAN & APPROVAL --}}
        <div class="data-panel-head d-flex justify-content-between align-items-center py-3 flex-wrap gap-2 no-print" style="border-top:1px solid var(--admin-border);">
            <div class="d-flex align-items-center gap-2">
                @if ($isSuperAdmin)
                    <span class="badge bg-danger-subtle text-danger py-2 px-3 fw-bold" id="badge-diff-alert" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Terdeteksi <strong id="alert-loss-count">0</strong> barang tidak sesuai!
                    </span>
                    <span class="text-muted small d-none d-md-inline">
                        Sebagai Super Admin / Owner, Anda memiliki wewenang penuh untuk memeriksa dan menyetujui penyesuaian stok.
                    </span>
                @else
                    <span class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i> Data yang Anda input akan ditinjau dan diverifikasi oleh Owner sebelum stok diperbarui.
                    </span>
                @endif
            </div>
            
            <div class="d-flex align-items-center gap-2">
                {{-- TOMBOL SIMPAN DRAFT --}}
                <button type="button" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm" id="btn-save-draft">
                    <i class="bi bi-floppy-fill me-1"></i> Simpan Hitungan Fisik
                </button>

                {{-- TOMBOL SETUJUI & TERAPKAN (HANYA SUPER ADMIN) --}}
                @if ($isSuperAdmin)
                    <button type="button" class="btn btn-warning btn-sm px-4 fw-bold shadow-sm" id="btn-save-opname" style="background:var(--accent);color:#000;border:none;">
                        <i class="bi bi-check-circle-fill me-1"></i> Setujui & Terapkan Stok
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- RIWAYAT AUDIT STOCK OPNAME SEBELUMNYA (HANYA BISA DILIHAT SUPER ADMIN) --}}
    @if ($isSuperAdmin && $recentOpnames->isNotEmpty())
        <div class="data-panel mb-4 no-print">
            <div class="data-panel-head">
                <div class="data-panel-icon"><i class="bi bi-clock-history"></i></div>
                <div class="flex-grow-1">
                    <h3 class="data-panel-title">Riwayat Stock Opname Terakhir</h3>
                    <p class="data-panel-desc">Rekam jejak audit stok fisik dan keputusan penyesuaian oleh Owner.</p>
                </div>
            </div>
            <div class="data-panel-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 ar-table">
                        <thead>
                            <tr>
                                <th class="ps-3">No. Opname</th>
                                <th>Tanggal</th>
                                <th>Dihitung Oleh (Kasir)</th>
                                <th>Disetujui Oleh (Owner)</th>
                                <th class="text-center">Barang Hilang</th>
                                <th class="text-end">Nilai Kerugian</th>
                                <th class="text-center pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentOpnames as $ro)
                                <tr>
                                    <td class="ps-3 font-monospace fw-bold">{{ $ro->opname_number }}</td>
                                    <td>{{ $ro->created_at->format('d/m/Y H:i') }}</td>
                                    <td><i class="bi bi-person me-1 text-muted"></i>{{ $ro->creator?->name ?? '-' }}</td>
                                    <td>
                                        @if ($ro->approver)
                                            <i class="bi bi-shield-check text-warning me-1"></i>{{ $ro->approver->name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($ro->total_loss_units > 0)
                                            <span class="badge bg-danger-subtle text-danger">-{{ $ro->total_loss_units }} unit</span>
                                        @else
                                            <span class="badge bg-success-subtle text-success">Klop (0)</span>
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace {{ $ro->total_loss_cost > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                        {{ $rp($ro->total_loss_cost) }}
                                    </td>
                                    <td class="text-center pe-3">
                                        @if ($ro->status === 'approved')
                                            <span class="badge bg-success text-white py-1 px-2"><i class="bi bi-check-all me-1"></i>Disetujui</span>
                                        @elseif ($ro->status === 'rejected')
                                            <span class="badge bg-danger text-white py-1 px-2"><i class="bi bi-x me-1"></i>Ditolak</span>
                                        @else
                                            <span class="badge bg-warning text-dark py-1 px-2">Menunggu</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL KONFIRMASI SIMPAN PENYESUAIAN (KHUSUS SUPER ADMIN) --}}
    @if ($isSuperAdmin)
        <div class="modal fade" id="modal-confirm-opname" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background:var(--admin-surface);border:1px solid var(--admin-border);">
                    <div class="modal-header" style="background:var(--admin-surface-2);border-bottom:1px solid var(--admin-border);">
                        <h5 class="modal-title fw-bold" style="color:var(--admin-text-heading);">
                            <i class="bi bi-shield-exclamation text-warning me-1"></i> Persetujuan Penyesuaian Stok (Owner)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p style="color:var(--admin-text);">Anda akan menyetujui dan menerapkan hasil hitung fisik ke stok sistem live:</p>
                        <div class="p-3 rounded mb-3" style="background:var(--admin-surface-2);border:1px solid var(--admin-border);">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Barang Hilang / Kurang:</span>
                                <strong class="text-danger" id="modal-loss-units">0 unit (0 jenis)</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Estimasi Kerugian (HPP):</span>
                                <strong class="text-danger fs-6" id="modal-loss-cost">Rp 0</strong>
                            </div>
                            <hr class="my-2 border-secondary-subtle">
                            <div class="d-flex justify-content-between">
                                <span>Barang Lebih / Temuan:</span>
                                <strong class="text-primary" id="modal-gain-units">0 unit (0 jenis)</strong>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 small mb-0">
                            <i class="bi bi-info-circle me-1"></i> Sistem akan otomatis mencatat riwayat <strong>Stok Keluar</strong> untuk barang yang hilang dan <strong>Stok Masuk</strong> untuk barang yang lebih.
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid var(--admin-border);">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="btn-submit-adjust" style="background:var(--accent);color:#000;border:none;">
                            Ya, Setujui & Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('css')
    <style>
        .ar-status-filter {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            color: var(--admin-text-muted);
            font-size: 0.78rem;
            font-weight: 500;
        }
        .ar-status-filter.active {
            background: var(--admin-surface-2) !important;
            border-color: var(--accent) !important;
            color: var(--accent-bright) !important;
            font-weight: 700;
        }
        .row-loss {
            background: rgba(239, 68, 68, 0.08) !important;
        }
        .row-gain {
            background: rgba(99, 102, 241, 0.08) !important;
        }
        @media print {
            .no-print, .admin-sidebar, .admin-topbar, .dash-kpi { display: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .data-panel { border: none !important; box-shadow: none !important; }
            .table { font-size: 11px !important; }
            .physical-input { border: 1px solid #000 !important; color: #000 !important; }
        }
    </style>
@endpush

@push('js')
    <script>
        $(function () {
            const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};
            const rupiah = (n) => 'Rp ' + Number(n).toLocaleString('id-ID');
            const pendingOpnameId = {{ $pendingOpname ? $pendingOpname->id : 'null' }};

            function recalculate() {
                if (!isSuperAdmin) {
                    // Kasir: tidak perlu menghitung kerugian atau menampilkan status selisih
                    applyFilters();
                    return;
                }

                let totalLossUnits = 0;
                let totalLossItems = 0;
                let totalLossCost = 0;

                let totalGainUnits = 0;
                let totalGainItems = 0;
                let totalGainCost = 0;

                let countMatch = 0;
                let countLoss = 0;
                let countGain = 0;

                $('.opname-row').each(function () {
                    const $row = $(this);
                    const sys = parseInt($row.data('system-stock'), 10) || 0;
                    const physVal = $row.find('.physical-input').val();
                    const phys = physVal === '' ? 0 : (parseInt(physVal, 10) || 0);
                    const cost = parseFloat($row.data('cost')) || 0;
                    const diff = phys - sys;
                    const diffCost = diff * cost;

                    const $diffCell = $row.find('.diff-cell');
                    const $costCell = $row.find('.diff-cost-cell');

                    $row.removeClass('row-loss row-gain');
                    $row.attr('data-status', 'match');

                    if (diff === 0) {
                        countMatch++;
                        $diffCell.html('<span class="badge bg-secondary-subtle text-secondary py-1 px-2">✓ Klop (0)</span>');
                        $costCell.text('Rp 0').css('color', 'var(--admin-text-muted)');
                    } else if (diff < 0) {
                        countLoss++;
                        const lossQty = Math.abs(diff);
                        const lossVal = Math.abs(diffCost);
                        totalLossUnits += lossQty;
                        totalLossItems++;
                        totalLossCost += lossVal;

                        $row.addClass('row-loss');
                        $row.attr('data-status', 'loss');
                        $diffCell.html(`<span class="badge bg-danger text-white py-1 px-2 fw-bold">-${lossQty} (HILANG)</span>`);
                        $costCell.html(`<span class="text-danger fw-bold">-${rupiah(lossVal)}</span>`);
                    } else {
                        countGain++;
                        const gainQty = diff;
                        const gainVal = diffCost;
                        totalGainUnits += gainQty;
                        totalGainItems++;
                        totalGainCost += gainVal;

                        $row.addClass('row-gain');
                        $row.attr('data-status', 'gain');
                        $diffCell.html(`<span class="badge bg-primary text-white py-1 px-2 fw-bold">+${gainQty} (LEBIH)</span>`);
                        $costCell.html(`<span class="text-primary fw-bold">+${rupiah(gainVal)}</span>`);
                    }
                });

                // Update KPI Cards
                $('#summary-loss-units').text(totalLossUnits.toLocaleString('id-ID') + ' unit');
                $('#summary-loss-items').text(totalLossItems + ' jenis barang terindikasi hilang');
                $('#summary-loss-cost').text(rupiah(totalLossCost));
                $('#summary-gain-info').text(`Kelebihan: ${totalGainUnits} unit (+${rupiah(totalGainCost)})`);

                // Update Filter Badge Counters
                $('#count-loss').text(countLoss);
                $('#count-gain').text(countGain);
                $('#count-match').text(countMatch);

                // Update Alert Banner
                if (countLoss > 0 || countGain > 0) {
                    $('#badge-diff-alert').show();
                    $('#alert-loss-count').text(countLoss + countGain);
                } else {
                    $('#badge-diff-alert').hide();
                }

                // Modal Summary Updates
                $('#modal-loss-units').text(`${totalLossUnits} unit (${totalLossItems} jenis)`);
                $('#modal-loss-cost').text(rupiah(totalLossCost));
                $('#modal-gain-units').text(`${totalGainUnits} unit (${totalGainItems} jenis)`);

                applyFilters();
            }

            function applyFilters() {
                const activeStatus = isSuperAdmin ? $('.ar-status-filter.active').data('filter') : 'all';
                const catId = $('#filter-category').val();
                const search = ($('#filter-search').val() || '').toLowerCase().trim();

                let visibleIndex = 1;

                $('.opname-row').each(function () {
                    const $row = $(this);
                    const rowStatus = $row.attr('data-status');
                    const rowCat = String($row.data('category-id') || '');
                    const rowName = String($row.data('name') || '');
                    const rowCode = String($row.data('code') || '');

                    let matchStatus = (activeStatus === 'all') || (rowStatus === activeStatus);
                    let matchCat = !catId || (rowCat === catId);
                    let matchSearch = !search || rowName.includes(search) || rowCode.includes(search);

                    if (matchStatus && matchCat && matchSearch) {
                        $row.show();
                        $row.find('.row-number').text(visibleIndex++);
                    } else {
                        $row.hide();
                    }
                });
            }

            // Realtime changes
            $(document).on('input', '.physical-input', function () {
                if ($(this).val() < 0) $(this).val(0);
                if (isSuperAdmin) {
                    recalculate();
                }
            });

            if (isSuperAdmin) {
                $('.ar-status-filter').on('click', function () {
                    $('.ar-status-filter').removeClass('active');
                    $(this).addClass('active');
                    applyFilters();
                });
            }

            function updateExportUrl() {
                const cat = $('#filter-category').val();
                const q = $('#filter-search').val();
                let url = '{{ route("stock-opnames.export") }}';
                const params = new URLSearchParams();
                if (cat) params.append('category_id', cat);
                if (q) params.append('search', q);
                const qs = params.toString();
                $('#btn-export-opname').attr('href', qs ? (url + '?' + qs) : url);
            }

            $('#filter-category').on('change', function () {
                applyFilters();
                updateExportUrl();
            });
            $('#filter-search').on('input', function () {
                applyFilters();
                updateExportUrl();
            });

            function collectItemsData() {
                const items = [];
                $('.opname-row').each(function () {
                    const id = $(this).data('id');
                    const physVal = $(this).find('.physical-input').val();
                    const physical_stock = physVal === '' ? 0 : (parseInt(physVal, 10) || 0);
                    const notes = $(this).find('.notes-input').val() || null;
                    items.push({ id, physical_stock, notes });
                });
                return items;
            }

            // 1. SIMPAN DRAFT (KASIR & OWNER)
            $('#btn-save-draft').on('click', function () {
                const $btn = $(this);
                const items = collectItemsData();

                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');

                $.ajax({
                    url: '{{ route("stock-opnames.draft") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        items: items
                    },
                    success: function (res) {
                        alert(res.message || 'Hitungan fisik berhasil disimpan.');
                        location.reload();
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html('<i class="bi bi-floppy-fill me-1"></i> Simpan Hitungan Fisik');
                        const msg = xhr.responseJSON?.message || 'Gagal menyimpan hitungan fisik.';
                        alert('Error: ' + msg);
                    }
                });
            });

            // 2. APPROVAL & TERAPKAN STOK (KHUSUS SUPER ADMIN)
            if (isSuperAdmin) {
                $('#btn-save-opname').on('click', function () {
                    $('#modal-confirm-opname').modal('show');
                });

                $('#btn-submit-adjust').on('click', function () {
                    const $btn = $(this);
                    const items = collectItemsData();

                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Memproses...');

                    $.ajax({
                        url: '{{ route("stock-opnames.approve") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            stock_opname_id: pendingOpnameId,
                            items: items
                        },
                        success: function (res) {
                            alert(res.message || 'Penyesuaian stok berhasil diterapkan!');
                            window.location.href = '{{ route("stock-opnames.index") }}';
                        },
                        error: function (xhr) {
                            $btn.prop('disabled', false).html('Ya, Setujui & Terapkan');
                            const msg = xhr.responseJSON?.message || 'Gagal menerapkan penyesuaian stok.';
                            alert('Error: ' + msg);
                        }
                    });
                });

                // 3. TOLAK DRAFT (KHUSUS SUPER ADMIN)
                $('#btn-reject-draft').on('click', function () {
                    const opnameId = $(this).data('id');
                    if (!confirm('Apakah Anda yakin ingin menolak draft pengajuan ini dan meminta kasir menghitung ulang?')) {
                        return;
                    }

                    $.ajax({
                        url: '/stock-opnames/' + opnameId + '/reject',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            alert(res.message || 'Draft berhasil ditolak.');
                            location.reload();
                        },
                        error: function (xhr) {
                            const msg = xhr.responseJSON?.message || 'Gagal menolak draft.';
                            alert('Error: ' + msg);
                        }
                    });
                });
            }

            // Inisialisasi awal
            recalculate();
        });
    </script>
@endpush
