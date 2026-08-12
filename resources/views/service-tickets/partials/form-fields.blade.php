@php
    $ticket = $ticket ?? null;
    $isEdit = $ticket !== null;
@endphp

<div class="data-panel">
    <div class="data-panel-head">
        <h2 class="data-panel-title"><i class="bi bi-person me-1"></i> Pelanggan</h2>
    </div>
    <div class="data-panel-body">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                <select name="customer_id" id="ticket_customer_id"
                    class="form-control form-control-clean atha-searchable-select atha-select2-customer" required>
                    <option value="">-- Pilih pelanggan --</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}"
                            data-code="{{ $customer->code }}"
                            data-name="{{ $customer->name }}"
                            data-phone="{{ $customer->phone }}"
                            @selected(old('customer_id', $ticket?->customer_id) == $customer->id)>
                            {{ $customer->code }} — {{ $customer->name }}
                        </option>
                    @endforeach
                    <option value="__new__" @selected(old('customer_id') === '__new__')>+ Pelanggan baru...</option>
                </select>
                <div class="form-hint-sm">Cari nama, kode, atau nomor WhatsApp.</div>
                <div id="ticket-new-customer-fields" class="border rounded p-3 mt-3 customer-inline-panel {{ old('customer_id') === '__new__' ? '' : 'd-none' }}">
                    <div class="small fw-semibold mb-2"><i class="bi bi-person-plus me-1"></i> Data pelanggan baru</div>
                    <div class="mb-2">
                        <label class="form-label small mb-0" for="new_customer_name">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="new_customer_name" id="new_customer_name"
                            class="form-control form-control-clean" value="{{ old('new_customer_name') }}"
                            placeholder="Nama pelanggan" autocomplete="off"
                            @if (old('customer_id') === '__new__') required @endif>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-0" for="new_customer_phone">Telepon / WhatsApp</label>
                        <input type="text" name="new_customer_phone" id="new_customer_phone"
                            class="form-control form-control-clean" value="{{ old('new_customer_phone') }}"
                            placeholder="Disarankan, untuk kirim tautan lacak" autocomplete="off">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-0" for="new_customer_address">Alamat</label>
                        <input type="text" name="new_customer_address" id="new_customer_address"
                            class="form-control form-control-clean" value="{{ old('new_customer_address') }}"
                            placeholder="Opsional" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label">Teknisi</label>
                <select name="technician_id" class="form-control form-control-clean atha-searchable-select">
                    <option value="">-- Belum ditugaskan --</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}"
                            @selected(old('technician_id', $ticket?->technician_id) == $technician->id)>
                            {{ $technician->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="data-panel">
    <div class="data-panel-head">
        <h2 class="data-panel-title"><i class="bi bi-phone me-1"></i> Perangkat</h2>
    </div>
    <div class="data-panel-body">
        <div class="row g-3">
            <div class="col-sm-4">
                <label class="form-label">Jenis perangkat <span class="text-danger">*</span></label>
                <select name="device_type" class="form-control form-control-clean" required>
                    @foreach ($deviceTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('device_type', $ticket?->device_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Merk <span class="text-danger">*</span></label>
                <input type="text" name="brand" class="form-control form-control-clean" required
                    value="{{ old('brand', $ticket?->brand) }}" placeholder="Samsung, iPhone, Asus, ...">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Tipe / model <span class="text-danger">*</span></label>
                <input type="text" name="model" class="form-control form-control-clean" required
                    value="{{ old('model', $ticket?->model) }}" placeholder="A54, 13 Pro, VivoBook, ...">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Warna</label>
                <input type="text" name="color" class="form-control form-control-clean"
                    value="{{ old('color', $ticket?->color) }}">
            </div>
            <div class="col-sm-8">
                <label class="form-label">IMEI / Serial Number</label>
                <input type="text" name="serial_imei" class="form-control form-control-clean"
                    value="{{ old('serial_imei', $ticket?->serial_imei) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Spesifikasi</label>
                <textarea name="specs" class="form-control form-control-clean" rows="3"
                    placeholder="RAM, penyimpanan, kondisi fisik, password, dll.">{{ old('specs', $ticket?->specs) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Kelengkapan yang dititipkan</label>
                <textarea name="accessories" class="form-control form-control-clean" rows="3"
                    placeholder="Charger, tas, stylus, SIM card, ...">{{ old('accessories', $ticket?->accessories) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="data-panel">
    <div class="data-panel-head">
        <h2 class="data-panel-title"><i class="bi bi-chat-left-text me-1"></i> Keluhan</h2>
    </div>
    <div class="data-panel-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Keluhan <span class="text-danger">*</span></label>
                <textarea name="complaint" class="form-control form-control-clean" rows="4" required
                    placeholder="Mati total, LCD pecah, overheat, tidak charging, ...">{{ old('complaint', $ticket?->complaint) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Hasil pengecekan / diagnosa</label>
                <textarea name="diagnosis" class="form-control form-control-clean" rows="4">{{ old('diagnosis', $ticket?->diagnosis) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="data-panel">
    <div class="data-panel-head">
        <h2 class="data-panel-title"><i class="bi bi-cash-coin me-1"></i> Biaya</h2>
    </div>
    <div class="data-panel-body">
        @php
            $estimatedCost = \App\Support\MoneyInput::parse(old('estimated_cost', $ticket?->estimated_cost));
            $downPayment = \App\Support\MoneyInput::parse(old('down_payment', $ticket?->down_payment));
        @endphp
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label">Estimasi biaya</label>
                <input type="text" name="estimated_cost" class="form-control form-control-clean" inputmode="numeric"
                    data-money-input autocomplete="off" placeholder="cth. 1.000.000"
                    value="{{ $estimatedCost !== null ? number_format($estimatedCost, 0, ',', '.') : '' }}">
                <div class="form-hint-sm">Perkiraan, bukan tagihan final. Ketik angka, pemisah ribuan muncul otomatis.</div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">DP / Uang muka</label>
                <input type="text" name="down_payment" id="down_payment" class="form-control form-control-clean" inputmode="numeric"
                    data-money-input autocomplete="off" placeholder="cth. 200.000"
                    value="{{ $downPayment ? number_format($downPayment, 0, ',', '.') : '' }}">
                <div class="form-hint-sm">Dipotong otomatis saat buat nota.</div>
            </div>
            <div class="col-sm-6 {{ (float) old('down_payment', $ticket?->down_payment ?? 0) > 0 ? '' : 'd-none' }}" id="dp-payment-wrap">
                <label class="form-label">Metode bayar DP <span class="text-danger">*</span></label>
                <select name="down_payment_payment_method" id="down_payment_payment_method" class="form-select form-control-clean">
                    <option value="">-- Pilih metode --</option>
                    @foreach (config('workshop.payment_methods', []) as $value => $label)
                        <option value="{{ $value }}" @selected(old('down_payment_payment_method', $ticket?->down_payment_payment_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 {{ old('down_payment_payment_method', $ticket?->down_payment_payment_method) === 'transfer' ? '' : 'd-none' }}" id="dp-bank-account-wrap">
                <label class="form-label">Akun bank DP <span class="text-danger">*</span></label>
                <select name="down_payment_bank_account_id" id="down_payment_bank_account_id" class="form-select form-control-clean">
                    <option value="">-- Pilih akun bank --</option>
                    @foreach ($bankAccounts ?? [] as $account)
                        <option value="{{ $account->id }}" @selected(old('down_payment_bank_account_id', $ticket?->down_payment_bank_account_id) == $account->id)>
                            {{ $account->displayLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="data-panel">
    <div class="data-panel-head">
        <h2 class="data-panel-title"><i class="bi bi-journal-text me-1"></i> Catatan internal</h2>
    </div>
    <div class="data-panel-body">
        <textarea name="internal_notes" class="form-control form-control-clean" rows="3"
            placeholder="Hanya terlihat di admin, tidak tampil di halaman lacak pelanggan.">{{ old('internal_notes', $ticket?->internal_notes) }}</textarea>
    </div>
</div>

@once
    @push('js')
        <script src="{{ asset('js/money-input.js') }}?v={{ filemtime(public_path('js/money-input.js')) }}"></script>
        <script>
            (function ($) {
                const $select = $('#ticket_customer_id');
                const $panel = $('#ticket-new-customer-fields');
                const $name = $('#new_customer_name');

                function toggleNewCustomer() {
                    const isNew = $select.val() === '__new__';
                    $panel.toggleClass('d-none', !isNew);
                    $name.prop('required', isNew);
                }

                $select.on('change', toggleNewCustomer);
                toggleNewCustomer();

                const $dp = $('#down_payment');
                const $dpWrap = $('#dp-payment-wrap');
                const $dpMethod = $('#down_payment_payment_method');
                const $dpBankWrap = $('#dp-bank-account-wrap');
                const $dpBank = $('#down_payment_bank_account_id');

                function dpAmount() {
                    return window.AthaMoneyInput
                        ? (window.AthaMoneyInput.parse($dp.val()) || 0)
                        : (parseFloat(String($dp.val()).replace(/\./g, '').replace(',', '.')) || 0);
                }

                function toggleDownPayment() {
                    const hasDp = dpAmount() > 0;
                    $dpWrap.toggleClass('d-none', !hasDp);
                    $dpMethod.prop('required', hasDp);
                    if (!hasDp) {
                        $dpMethod.val('');
                        $dpBank.val('');
                        $dpBankWrap.addClass('d-none');
                        $dpBank.prop('required', false);
                    } else {
                        toggleDpBank();
                    }
                }

                function toggleDpBank() {
                    const isTransfer = $dpMethod.val() === 'transfer';
                    $dpBankWrap.toggleClass('d-none', !isTransfer);
                    $dpBank.prop('required', isTransfer);
                    if (!isTransfer) {
                        $dpBank.val('');
                    }
                }

                $dp.on('input change atha:money-changed', toggleDownPayment);
                $dpMethod.on('change', toggleDpBank);
                toggleDownPayment();
            })(jQuery);
        </script>
    @endpush
@endonce
