@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Pengaturan Aplikasi'],
        ],
        'title' => 'Pengaturan Aplikasi',
        'subtitle' => 'Identitas toko, kontak, logo, dan gateway WhatsApp HiWA.',
    ])

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('settings.update') }}" method="POST" class="form-page-inner" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="data-panel h-100">
                    <div class="data-panel-head">
                        <h2 class="data-panel-title"><i class="bi bi-building me-1"></i> Identitas Toko</h2>
                    </div>
                    <div class="data-panel-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Toko <span class="text-danger">*</span></label>
                            <input type="text" name="app_name" class="form-control form-control-clean @error('app_name') is-invalid @enderror"
                                value="{{ old('app_name', $settings['app_name']) }}" required>
                            @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint-sm">Ditampilkan di sidebar, login, nota, dan judul halaman.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tagline / Subjudul</label>
                            <input type="text" name="app_tagline" class="form-control form-control-clean"
                                value="{{ old('app_tagline', $settings['app_tagline']) }}" placeholder="Service Smartphone & Laptop">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat Toko</label>
                            <textarea name="company_address" class="form-control form-control-clean @error('company_address') is-invalid @enderror" rows="2"
                                placeholder="Jl. Contoh No. 1, Kota">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                            @error('company_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint-sm">Tampil di nota penjualan / struk thermal.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">WhatsApp Toko</label>
                            <input type="text" name="company_whatsapp" class="form-control form-control-clean @error('company_whatsapp') is-invalid @enderror"
                                value="{{ old('company_whatsapp', $settings['company_whatsapp'] ?? '') }}"
                                placeholder="08xxxxxxxxxx">
                            @error('company_whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint-sm">Nomor kontak pelanggan (bukan token gateway HiWA).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi Aplikasi</label>
                            <textarea name="app_description" class="form-control form-control-clean" rows="3">{{ old('app_description', $settings['app_description']) }}</textarea>
                        </div>
                        <div class="mb-0">
                            @include('layouts.partials.entity-photo-field', [
                                'label' => 'Logo Toko',
                                'placeholderIcon' => 'bi-shop',
                            ])
                            @error('photo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            <div class="form-hint-sm mt-1">Logo dipakai di halaman login, sidebar, dan nota (jika diisi).</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="data-panel h-100">
                    <div class="data-panel-head">
                        <h2 class="data-panel-title"><i class="bi bi-whatsapp me-1"></i> Gateway WhatsApp (HiWA)</h2>
                    </div>
                    <div class="data-panel-body">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="hiwa_enabled" value="1" class="form-check-input" id="hiwa_enabled"
                                @checked(old('hiwa_enabled', $settings['hiwa_enabled']))>
                            <label class="form-check-label" for="hiwa_enabled">Aktifkan integrasi HiWA</label>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="hiwa_token_device">Token Device</label>
                            <div class="secret-input-wrap">
                                <input type="password" name="hiwa_token_device" id="hiwa_token_device"
                                    class="form-control form-control-clean"
                                    value="{{ old('hiwa_token_device', $settings['hiwa_token_device'] ?? '') }}"
                                    placeholder="Token dari perangkat HiWA"
                                    autocomplete="new-password" spellcheck="false">
                                <button type="button" class="secret-input-toggle" data-toggle-secret="hiwa_token_device"
                                    aria-label="Tampilkan token">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-hint-sm">Dari dashboard HiWA saat perangkat pertama kali terhubung.</div>
                        </div>
                    </div>
                </div>

                <div class="data-panel mt-4">
                    <div class="data-panel-head">
                        <h2 class="data-panel-title"><i class="bi bi-whatsapp me-1 text-success"></i> Gateway WhatsApp (Fonnte)</h2>
                    </div>
                    <div class="data-panel-body">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="fonnte_enabled" value="1" class="form-check-input" id="fonnte_enabled"
                                @checked(old('fonnte_enabled', $settings['fonnte_enabled'] ?? false))>
                            <label class="form-check-label" for="fonnte_enabled">Aktifkan integrasi Fonnte</label>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="fonnte_token">Token Fonnte (API Key)</label>
                            <div class="secret-input-wrap">
                                <input type="password" name="fonnte_token" id="fonnte_token"
                                    class="form-control form-control-clean"
                                    value="{{ old('fonnte_token', $settings['fonnte_token'] ?? '') }}"
                                    placeholder="Contoh: aBcDeFgHiJkLmNoPqRsTuVwXyZ"
                                    autocomplete="new-password" spellcheck="false">
                                <button type="button" class="secret-input-toggle" data-toggle-secret="fonnte_token"
                                    aria-label="Tampilkan token">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-hint-sm">Dapatkan token dari dashboard <strong>fonnte.com</strong> (Menu Device / API).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.partials.form-actions', [
            'backUrl' => route('dashboard'),
            'submitLabel' => 'Simpan Pengaturan',
        ])
    </form>
@endsection

@push('js')
    <script>
        @if (! empty($logoUrl))
            if (window.AthaEntityPhoto) {
                AthaEntityPhoto.setPreview(@json($logoUrl));
            }
        @endif

        document.querySelectorAll('[data-toggle-secret]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.getAttribute('data-toggle-secret'));
                const icon = btn.querySelector('i');
                if (!input || !icon) {
                    return;
                }

                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('bi-eye', !show);
                icon.classList.toggle('bi-eye-slash', show);
                btn.setAttribute('aria-label', show ? 'Sembunyikan token' : 'Tampilkan token');
            });
        });
    </script>
@endpush
