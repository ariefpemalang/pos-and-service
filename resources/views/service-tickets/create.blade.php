@extends('layouts.app')

@section('title', 'Terima Perangkat')

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Tiket Servis', 'url' => route('service-tickets.index')],
            ['label' => 'Terima Perangkat'],
        ],
        'title' => 'Terima Perangkat',
        'subtitle' => 'Catat spesifikasi HP/laptop yang dititipkan. Centang opsi WhatsApp jika ingin kirim tautan lacak ke pelanggan.',
    ])

    <form method="POST" action="{{ route('service-tickets.store') }}" class="form-page-inner">
        @csrf
        <div class="row g-4 align-items-start">
            <div class="col-lg-8 d-flex flex-column gap-3">
                @include('service-tickets.partials.form-fields')
            </div>
            <div class="col-lg-4">
                <div class="ticket-form-aside-wrap sticky-top">
                    @include('service-tickets.partials.form-sidebar')
                    <div class="form-actions justify-content-between flex-wrap gap-2 mt-3">
                        <div class="form-check mb-0">
                            <input type="hidden" name="notify_customer" value="0">
                            <input type="checkbox" name="notify_customer" value="1" class="form-check-input" id="notify_customer"
                                @checked(old('notify_customer'))>
                            <label class="form-check-label" for="notify_customer">
                                <i class="bi bi-whatsapp me-1"></i> Kirim notifikasi WhatsApp
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-save">
                            <i class="bi bi-check2-circle me-1"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
