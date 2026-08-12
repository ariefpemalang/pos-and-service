@php
    $ticket = $ticket ?? null;
    $warrantyPresets = $warrantyPresets ?? config('service-tickets.warranty_presets');
    $defaultDays = (int) config('service-tickets.warranty_default_days', 30);
    $currentDays = (int) old('warranty_days', $ticket?->warranty_days ?? $defaultDays);
    $presetKeys = array_map('intval', array_keys($warrantyPresets ?? []));
    $isCustom = ! in_array($currentDays, $presetKeys, true);
    $presetValue = old('warranty_preset', $isCustom ? 'custom' : (string) $currentDays);
@endphp

<div class="data-panel ticket-form-aside">
    <div class="data-panel-head">
        <h2 class="data-panel-title"><i class="bi bi-shield-check me-1"></i> Garansi</h2>
    </div>
    <div class="data-panel-body">
        <div class="warranty-aside-preview warranty-card {{ (int) $presetValue === 0 ? 'warranty-card--none' : 'warranty-card--pending' }}" id="warranty-aside-preview">
            <div class="warranty-card-head">
                <div class="warranty-card-icon"><i class="bi bi-shield-check"></i></div>
                <div>
                    <div class="warranty-card-kicker">Pratinjau</div>
                    <div class="warranty-card-title" id="warranty-preview-title">
                        @if ((string) $presetValue === 'custom')
                            {{ $currentDays }} hari
                        @else
                            {{ $warrantyPresets[(int) $presetValue] ?? '30 hari' }}
                        @endif
                    </div>
                </div>
            </div>
            <p class="warranty-card-copy mb-0" id="warranty-preview-copy">
                @if ((int) $presetValue === 0 && $presetValue !== 'custom')
                    Tiket ini tidak memiliki garansi.
                @else
                    Mulai dihitung setelah servis selesai dan lunas.
                @endif
            </p>
        </div>

        <div class="mt-3">
            @include('service-tickets.partials.warranty-fields', [
                'ticket' => $ticket,
                'warrantyPresets' => $warrantyPresets,
                'fieldId' => 'ticket_warranty',
                'stack' => true,
            ])
        </div>

        <ul class="warranty-aside-tips">
            <li>Tampil di nota, halaman lacak, dan WhatsApp saat lunas.</li>
            <li>Durasi baru berjalan setelah perangkat selesai / dibayar.</li>
            <li>Bisa diubah lagi dari detail tiket kapan saja.</li>
        </ul>
    </div>
</div>

@once
    @push('js')
        <script>
            (function ($) {
                const $aside = $('.ticket-form-aside');
                if (!$aside.length) return;

                const $preset = $aside.find('.js-warranty-preset');
                const $days = $aside.find('.js-warranty-days');
                const $title = $('#warranty-preview-title');
                const $copy = $('#warranty-preview-copy');
                const $card = $('#warranty-aside-preview');

                function previewLabel() {
                    const val = $preset.val();
                    if (val === 'custom') {
                        const n = parseInt($days.val(), 10);
                        return n > 0 ? n + ' hari' : 'Custom';
                    }
                    return $.trim($preset.find('option:selected').text()) || 'Garansi';
                }

                function updatePreview() {
                    const none = $preset.val() === '0';
                    $title.text(previewLabel());
                    $copy.text(none
                        ? 'Tiket ini tidak memiliki garansi.'
                        : 'Mulai dihitung setelah servis selesai dan lunas.');
                    $card.toggleClass('warranty-card--none', none);
                    $card.toggleClass('warranty-card--pending', !none);
                }

                $preset.on('change', updatePreview);
                $days.on('input change', updatePreview);
                updatePreview();
            })(jQuery);
        </script>
    @endpush
@endonce
