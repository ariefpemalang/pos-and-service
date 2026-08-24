@extends('layouts.app')

@section('title', 'Ubah Tiket '.$ticket->ticket_no)

@section('content')
    @include('layouts.partials.page-hero', [
        'items' => [
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Tiket Servis', 'url' => route('service-tickets.index')],
            ['label' => $ticket->ticket_no, 'url' => route('service-tickets.show', $ticket)],
            ['label' => 'Ubah'],
        ],
        'title' => 'Ubah Tiket '.$ticket->ticket_no,
        'subtitle' => 'Perbarui spesifikasi perangkat atau diagnosa.',
    ])

    <form method="POST" action="{{ route('service-tickets.update', $ticket) }}" class="form-page-inner">
        @csrf
        @method('PUT')
        <div class="row g-4 align-items-start">
            <div class="col-lg-8 d-flex flex-column gap-3">
                @include('service-tickets.partials.form-fields', ['ticket' => $ticket])
            </div>
            <div class="col-lg-4">
                <div class="ticket-form-aside-wrap sticky-top">
                    <div class="data-panel">
                        <div class="data-panel-head">
                            <h2 class="data-panel-title"><i class="bi bi-shield-check me-1"></i> Garansi</h2>
                        </div>
                        <div class="data-panel-body">
                            <div class="alert alert-light border mb-0 small">
                                Garansi tidak diatur di tahap terima perangkat. Ubah garansi saat
                                <strong>buat nota / transaksi pelunasan</strong> atau dari detail tiket setelahnya.
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        @include('layouts.partials.form-actions', ['submitLabel' => 'Simpan Perubahan'])
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
