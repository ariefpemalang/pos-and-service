@php
    $ticket = $ticket ?? null;
    $warrantyPresets = $warrantyPresets ?? config('service-tickets.warranty_presets', []);
    $defaultDays = (int) config('service-tickets.warranty_default_days', 30);
    $currentDays = (int) old('warranty_days', $ticket?->warranty_days ?? $defaultDays);
    $presetKeys = array_map('intval', array_keys($warrantyPresets));
    $isCustom = ! in_array($currentDays, $presetKeys, true);
    $presetValue = old('warranty_preset', $isCustom ? 'custom' : (string) $currentDays);
    $fieldId = $fieldId ?? 'warranty';
    $stack = $stack ?? false;
    $col = $stack ? 'col-12' : 'col-md-6';
@endphp

<div class="row g-3 warranty-fields">
    <div class="{{ $col }}">
        <label class="form-label" for="{{ $fieldId }}_preset">Durasi garansi</label>
        <select name="warranty_preset" id="{{ $fieldId }}_preset" class="form-select form-control-clean js-warranty-preset">
            @foreach ($warrantyPresets as $days => $label)
                <option value="{{ $days }}" @selected($presetValue === (string) $days)>{{ $label }}</option>
            @endforeach
            <option value="custom" @selected($presetValue === 'custom')>Custom...</option>
        </select>
        <div class="form-hint-sm">Mulai dihitung setelah servis selesai / lunas.</div>
    </div>
    <div class="{{ $col }} js-warranty-custom {{ $presetValue === 'custom' ? '' : 'd-none' }}">
        <label class="form-label" for="{{ $fieldId }}_days">Jumlah hari</label>
        <input type="number" name="warranty_days" id="{{ $fieldId }}_days" class="form-control form-control-clean js-warranty-days"
            min="1" max="{{ (int) config('service-tickets.warranty_max_days', 365) }}"
            value="{{ $presetValue === 'custom' ? $currentDays : '' }}"
            @if ($presetValue === 'custom') required @endif>
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $fieldId }}_notes">Cakupan / syarat</label>
        <textarea name="warranty_notes" id="{{ $fieldId }}_notes" class="form-control form-control-clean" rows="2"
            placeholder="Contoh: Hanya kerusakan yang sama. Tidak termasuk jatuh, kena air, atau software.">{{ old('warranty_notes', $ticket?->warranty_notes) }}</textarea>
    </div>
</div>

@once
    @push('js')
        <script>
            (function ($) {
                $(document).on('change', '.js-warranty-preset', function () {
                    const $select = $(this);
                    const $scope = $select.closest('.warranty-fields, .warranty-form-block, .warranty-card-edit, .ticket-form-aside, form');
                    const $wrap = $scope.find('.js-warranty-custom').first();
                    const $days = $wrap.find('.js-warranty-days');
                    const isCustom = $select.val() === 'custom';
                    $wrap.toggleClass('d-none', !isCustom);
                    $days.prop('required', isCustom);
                    if (!isCustom) {
                        $days.val('');
                    }
                });
            })(jQuery);
        </script>
    @endpush
@endonce
