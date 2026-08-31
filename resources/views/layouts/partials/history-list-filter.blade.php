@php
    $entityFieldId = $entityFieldId ?? 'filter-entity';
    $entityLabel = $entityLabel ?? 'Filter';
    $entityEmpty = $entityEmpty ?? 'Semua';
    $entityIcon = $entityIcon ?? 'bi-person-badge';
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
    <div class="history-filter__grid">
        <div class="history-filter__field">
            <label class="history-filter__label" for="filter-from">
                <i class="bi bi-calendar3"></i> Dari
            </label>
            <input type="date" id="filter-from" class="form-control form-control-clean history-filter__input"
                value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="history-filter__field">
            <label class="history-filter__label" for="filter-to">
                <i class="bi bi-calendar3"></i> Sampai
            </label>
            <input type="date" id="filter-to" class="form-control form-control-clean history-filter__input"
                value="{{ $filters['to'] ?? '' }}">
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
