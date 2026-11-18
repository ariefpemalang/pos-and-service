<div class="list-toolbar mb-3">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div class="d-flex flex-wrap align-items-end gap-2 flex-grow-1">
            <div>
                <label class="form-label small mb-1" for="filter-from">Dari</label>
                <input type="date" id="filter-from" class="form-control form-control-clean" value="{{ now()->startOfMonth()->toDateString() }}">
            </div>
            <div>
                <label class="form-label small mb-1" for="filter-to">Sampai</label>
                <input type="date" id="filter-to" class="form-control form-control-clean" value="{{ now()->toDateString() }}">
            </div>
            <div class="flex-grow-1" style="min-width: 200px; max-width: 320px;">
                <label class="form-label small mb-1" for="filter-category">Kategori</label>
                <select id="filter-category" class="form-select form-control-clean">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" class="btn btn-outline-primary" id="btn-apply-filter">
                <i class="bi bi-funnel"></i> Terapkan
            </button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="form-label small fw-semibold text-muted mb-0 text-nowrap" for="filter-sort">
                <i class="bi bi-sort-down me-1"></i> Urutkan Berdasarkan:
            </label>
            <select id="filter-sort" name="sort" class="form-select form-select-sm form-control-clean" style="width: auto; min-width: 190px;">
                <option value="tanggal_desc" @selected(request('sort', 'tanggal_desc') === 'tanggal_desc')>Tanggal Terbaru</option>
                <option value="tanggal_asc" @selected(request('sort') === 'tanggal_asc')>Tanggal Terlama</option>
                <option value="nominal_desc" @selected(request('sort') === 'nominal_desc')>Nominal Tertinggi</option>
                <option value="nominal_asc" @selected(request('sort') === 'nominal_asc')>Nominal Terendah</option>
            </select>
        </div>
    </div>
</div>
