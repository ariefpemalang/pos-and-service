@php
    $entityFieldId = $entityFieldId ?? 'filter-entity';
    $entityLabel = $entityLabel ?? 'Filter';
    $entityEmpty = $entityEmpty ?? 'Semua';
    $entityIcon = $entityIcon ?? 'bi-person-badge';

    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();
    $startMonth = now()->startOfMonth()->toDateString();
    $endMonth = now()->endOfMonth()->toDateString();

    $currentFrom = $filters['from'] ?? '';
    $currentTo = $filters['to'] ?? '';

    $isToday = ($currentFrom === $today && $currentTo === $today);
    $isYesterday = ($currentFrom === $yesterday && $currentTo === $yesterday);
    $isThisMonth = ($currentFrom === $startMonth && ($currentTo === $endMonth || $currentTo === $today));
@endphp
<div class="history-filter" id="history-list-filter">
    <div class="history-filter__head">
        <div class="history-filter__icon" aria-hidden="true">
            <i class="bi bi-funnel-fill"></i>
        </div>
        <div class="history-filter__copy">
            <p class="history-filter__title">Filter data</p>
            <p class="history-filter__hint">Periode default: bulan berjalan</p>
        </div>
        <button type="button" class="btn btn-light-action history-filter__reset" id="btn-reset-list-filter">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Reset</span>
        </button>
    </div>

    {{-- Filter Cepat: Hari Ini, Kemaren, Bulan Ini --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 pb-2 border-bottom" style="border-color: var(--admin-border, rgba(255,255,255,0.08)) !important;">
        <span class="small fw-semibold text-muted me-1">
            <i class="bi bi-lightning-charge me-1"></i>Filter Cepat:
        </span>
        <button type="button" class="btn btn-sm btn-quick-filter {{ $isToday ? 'btn-primary' : 'btn-outline-secondary' }}"
            data-from="{{ $today }}" data-to="{{ $today }}">
            <i class="bi bi-calendar-event me-1"></i> Hari Ini
        </button>
        <button type="button" class="btn btn-sm btn-quick-filter {{ $isYesterday ? 'btn-primary' : 'btn-outline-secondary' }}"
            data-from="{{ $yesterday }}" data-to="{{ $yesterday }}">
            <i class="bi bi-calendar-event me-1"></i> Kemaren
        </button>
        <button type="button" class="btn btn-sm btn-quick-filter {{ $isThisMonth ? 'btn-primary' : 'btn-outline-secondary' }}"
            data-from="{{ $startMonth }}" data-to="{{ $endMonth }}">
            <i class="bi bi-calendar-month me-1"></i> Bulan Ini
        </button>
    </div>

    <div class="history-filter__grid">
        <div class="history-filter__field">
            <label class="history-filter__label" for="filter-from">
                <i class="bi bi-calendar3"></i> Dari
            </label>
            <input type="date" id="filter-from" class="form-control form-control-clean history-filter__input"
                value="{{ $currentFrom }}">
        </div>
        <div class="history-filter__field">
            <label class="history-filter__label" for="filter-to">
                <i class="bi bi-calendar3"></i> Sampai
            </label>
            <input type="date" id="filter-to" class="form-control form-control-clean history-filter__input"
                value="{{ $currentTo }}">
        </div>
        @if (! empty($entities))
            <div class="history-filter__field history-filter__field--entity">
                <label class="history-filter__label" for="{{ $entityFieldId }}">
                    <i class="bi {{ $entityIcon }}"></i> {{ $entityLabel }}
                </label>
                <select id="{{ $entityFieldId }}" class="form-select form-control-clean history-filter__input">
                    <option value="">{{ $entityEmpty }}</option>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const quickBtns = document.querySelectorAll('#history-list-filter .btn-quick-filter');
        const fromInput = document.getElementById('filter-from');
        const toInput = document.getElementById('filter-to');

        function syncActiveButton() {
            if (!fromInput || !toInput) return;
            const from = fromInput.value;
            const to = toInput.value;

            quickBtns.forEach(btn => {
                const bFrom = btn.getAttribute('data-from');
                const bTo = btn.getAttribute('data-to');
                const isMatch = (bFrom === from && (bTo === to || (btn.dataset.from === '{{ $startMonth }}' && (to === '{{ $endMonth }}' || to === '{{ $today }}'))));
                if (isMatch) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-secondary');
                }
            });
        }

        quickBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const from = this.getAttribute('data-from');
                const to = this.getAttribute('data-to');
                if (fromInput && toInput) {
                    fromInput.value = from;
                    toInput.value = to;
                    syncActiveButton();
                    if (window.jQuery) {
                        window.jQuery(fromInput).trigger('change');
                    } else {
                        fromInput.dispatchEvent(new Event('change'));
                        toInput.dispatchEvent(new Event('change'));
                    }
                }
            });
        });

        if (fromInput) fromInput.addEventListener('change', syncActiveButton);
        if (toInput) toInput.addEventListener('change', syncActiveButton);

        const resetBtn = document.getElementById('btn-reset-list-filter');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                setTimeout(syncActiveButton, 50);
            });
        }
    });
</script>
