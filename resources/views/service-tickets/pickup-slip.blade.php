@extends('layouts.print')

@section('title', 'Nota Servis Pengambilan '.$ticket->ticket_no)

@php
    $initialFormat = request('format', 'third');
@endphp

@section('body-class', 'format-'.$initialFormat)

@section('content')
    @php
        $ticket->loadMissing(['customer', 'technician', 'user', 'serviceLines', 'itemLines', 'paidTransaction', 'downPaymentTransaction']);

        $dp = $ticket->downPaymentTransaction;
        $paidTx = $ticket->paidTransaction;
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');

        $tglMasuk = $ticket->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y') ?? '-';
        $tglAmbil = ($ticket->completed_at ?? now())->timezone(config('app.timezone'))->translatedFormat('d M Y');
        $deviceSummary = trim(($ticket->device_type === 'laptop' ? 'Laptop ' : 'HP ').$ticket->brand.' '.$ticket->model);

        // Masked phone: 08***2872201
        $rawPhone = $ticket->customer?->phone ?? '-';
        if ($rawPhone !== '-' && strlen($rawPhone) >= 9) {
            $prefix = substr($rawPhone, 0, 2);
            $suffix = substr($rawPhone, -7);
            $maskedPhone = $prefix.'***'.$suffix;
        } else {
            $maskedPhone = $rawPhone;
        }

        // Hitung biaya & DP
        $dpAmount = $dp && ! $dp->isRefunded() ? (float) $dp->total : $ticket->downPaymentAmount();
        $isCancelled = $ticket->status === 'cancelled';

        if ($isCancelled) {
            $totalCost = 0;
            $tindakanText = 'cancel';
        } else {
            if ($paidTx && (float) $paidTx->total > 0) {
                $totalCost = (float) $paidTx->total;
            } elseif ($ticket->workTotal() > 0) {
                $totalCost = (float) $ticket->workTotal();
            } else {
                $totalCost = (float) ($ticket->estimated_cost ?? 0);
            }

            if ($ticket->serviceLines->isNotEmpty()) {
                $tindakanText = $ticket->serviceLines->pluck('service_name')->join(', ');
            } else {
                $tindakanText = $ticket->diagnosis ?: $ticket->statusLabel();
            }
        }

        $sisa = max(0, $totalCost - $dpAmount);

        // Status pembayaran teks
        if ($isCancelled || $totalCost <= 0) {
            $statusBayarText = 'Tidak Diperlukan.';
        } elseif ($paidTx && $paidTx->status === 'completed') {
            $method = strtoupper($paidTx->payment_method ?? 'CASH');
            $statusBayarText = 'LUNAS ('.$method.').';
        } elseif ($dpAmount >= $totalCost && $totalCost > 0) {
            $statusBayarText = 'LUNAS (DP).';
        } else {
            $statusBayarText = 'Belum Lunas (Sisa '.$rp($sisa).').';
        }

        // Garansi teks
        if ($isCancelled || (int) $ticket->warranty_days <= 0) {
            $garansiText = 'Tidak Ada.';
        } else {
            $wDate = ($ticket->completed_at ?? now())->timezone(config('app.timezone'))->addDays((int) $ticket->warranty_days)->translatedFormat('d/m/Y');
            $garansiText = $ticket->warranty_days.' hari s/d '.$wDate.($ticket->warranty_notes ? ' ('.$ticket->warranty_notes.')' : '').'.';
        }

        $operatorName = auth()->user()?->name ?? ($ticket->user?->name ?? 'Admin');
        $printedTime = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $trackingUrl = $ticket->trackingUrl();
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&margin=0&data='.urlencode($trackingUrl);
    @endphp

    {{-- TOOLBAR KONTROL CETAK --}}
    <div class="ncr-toolbar no-print">
        <div class="ncr-toolbar-group">
            <span style="font-weight: 600; color: #94a3b8; margin-right: 4px;">Ukuran:</span>
            <button type="button" class="btn-ncr btn-ncr--active" id="btn-toggle-third" onclick="switchFormat('third')">
                <i class="bi bi-file-earmark-text"></i> 1/3 Folio (21.5 × 11 cm)
            </button>
            <button type="button" class="btn-ncr" id="btn-toggle-dotmatrix" onclick="switchFormat('dotmatrix')">
                <i class="bi bi-printer"></i> 1/2 Folio / NCR (21.5 × 14 cm)
            </button>
        </div>
        <div class="ncr-toolbar-group">
            <button type="button" class="btn-ncr btn-ncr--primary" onclick="window.print()">
                <strong>Cetak Sekarang</strong>
            </button>
            <button type="button" class="btn-ncr btn-ncr--light" onclick="window.close()">Tutup</button>
        </div>
    </div>

    {{-- LEMBAR NOTA SERVIS PENGAMBILAN --}}
    <div class="format-dotmatrix-view">
        <div class="pickup-sheet-wrapper">
            {{-- WATERMARK DI TENGAH --}}
            <div class="pickup-watermark-stamp">
                {{ brand_name() ?: 'AR CELL' }}
            </div>

            {{-- 1. HEADER (Kiri: Info Nota, Kanan: Toko) --}}
            <div class="pickup-header">
                <div class="pickup-header-left">
                    <div class="pickup-main-title">NOTA SERVIS</div>
                    <table class="pickup-meta-table">
                        <tr>
                            <td class="pickup-lbl">No. Servis</td>
                            <td class="pickup-col">:</td>
                            <td class="pickup-val font-bold">{{ $ticket->ticket_no }}</td>
                        </tr>
                        <tr>
                            <td class="pickup-lbl">Tgl. Masuk</td>
                            <td class="pickup-col">:</td>
                            <td class="pickup-val">{{ $tglMasuk }}</td>
                        </tr>
                        <tr>
                            <td class="pickup-lbl">Nama</td>
                            <td class="pickup-col">:</td>
                            <td class="pickup-val">{{ $ticket->customer?->name ?? 'Pelanggan Umum' }} &nbsp;|&nbsp; {{ $maskedPhone }}</td>
                        </tr>
                        <tr>
                            <td class="pickup-lbl">Tgl. Ambil</td>
                            <td class="pickup-col">:</td>
                            <td class="pickup-val">{{ $tglAmbil }}</td>
                        </tr>
                    </table>
                </div>

                <div class="pickup-header-right">
                    <div class="pickup-store-name">{{ brand_name() ?: 'AR CELL' }}</div>
                    <div class="pickup-store-tagline">{{ brand_tagline() ?: 'Servis Handphone Segala Kerusakan' }}</div>
                    <div class="pickup-store-address">{{ brand_address() }}</div>
                    <div class="pickup-store-contact">Telp | WA {{ brand_whatsapp() ?: '082322872201' }}</div>
                </div>
            </div>

            {{-- 2. TABEL BARANG SERVIS --}}
            <table class="pickup-items-table">
                <thead>
                    <tr>
                        <th style="width: 28px; text-align: center;">No.</th>
                        <th style="width: 25%;">Nama Barang</th>
                        <th style="width: 20%;">Kelengkapan</th>
                        <th style="width: 20%;">Kerusakan</th>
                        <th style="width: 20%;">Keterangan/Tindakan</th>
                        <th style="width: 15%; text-align: right;">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center; vertical-align: top;">1.</td>
                        <td style="vertical-align: top;">
                            <span class="font-bold">{{ $deviceSummary }}</span>
                            @if ($ticket->color)
                                <div class="pickup-subtext">Warna: {{ $ticket->color }}</div>
                            @endif
                            @if ($ticket->serial_imei)
                                <div class="pickup-subtext">IMEI/SN: {{ $ticket->serial_imei }}</div>
                            @endif
                        </td>
                        <td style="vertical-align: top;">{{ $ticket->accessories ?: '-' }}</td>
                        <td style="vertical-align: top;">{{ $ticket->complaint ?: '-' }}</td>
                        <td style="vertical-align: top;">{{ $tindakanText }}</td>
                        <td style="text-align: right; vertical-align: top;" class="font-bold">
                            {{ $rp($totalCost) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            {{-- 3. CATATAN & TOTAL SECTION --}}
            <div class="pickup-total-section">
                <div class="pickup-notes-col">
                    <span class="pickup-lbl">Catatan :</span>
                    <span class="pickup-notes-val">{{ $ticket->internal_notes ?: ($ticket->diagnosis ?: '-') }}</span>
                </div>
                <div class="pickup-amounts-col">
                    <div class="pickup-amount-row">
                        <span class="pickup-amount-lbl">TOTAL</span>
                        <span class="pickup-amount-val">{{ $rp($totalCost) }}</span>
                    </div>
                    <div class="pickup-amount-row">
                        <span class="pickup-amount-lbl">DP/Uang Muka</span>
                        <span class="pickup-amount-val">{{ $rp($dpAmount) }}</span>
                    </div>
                    @if ($sisa > 0)
                        <div class="pickup-amount-row" style="border-top: 1px dashed #000; margin-top: 2px; padding-top: 2px;">
                            <span class="pickup-amount-lbl">Sisa / Pelunasan</span>
                            <span class="pickup-amount-val">{{ $rp($sisa) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 4. QR CODE & WATERMARK TENGAH --}}
            <div class="pickup-qr-center-box">
                <img src="{{ $qrUrl }}" alt="QR Verifikasi Tiket" class="pickup-qr-img" width="80" height="80">
                <div class="pickup-qr-caption">Scan Pada Saat Terima Garansi Barang Servis</div>
            </div>

            {{-- 5. FOOTER: STATUS, SYARAT KETENTUAN, DAN 4 TANDA TANGAN --}}
            <div class="pickup-footer-grid">
                {{-- Kiri: Keterangan & Syarat Ketentuan --}}
                <div class="pickup-footer-left">
                    <div class="pickup-status-line">*Status Pembayaran {{ $statusBayarText }}</div>
                    <div class="pickup-status-line">*Garansi {{ $garansiText }}</div>
                    <div class="pickup-status-line">*Nota ini dicetak {{ $operatorName }} [{{ $printedTime }} WIB]</div>

                    <div class="pickup-terms-box">
                        <div class="pickup-terms-title">Syarat &amp; Ketentuan {{ brand_name() ?: 'AR CELL' }} :</div>
                        <ol class="pickup-terms-list">
                            <li>Jika barang tidak diambil lebih dari 3 bulan setelah adanya konfirmasi pengambilan barang sudah bukan tanggung jawab {{ brand_name() ?: 'AR CELL' }}.</li>
                            <li>Garansi tidak berlaku jika Nota Servis hilang.</li>
                        </ol>
                    </div>
                </div>

                {{-- Kanan: 4 Kolom Tanda Tangan --}}
                <div class="pickup-footer-right">
                    <div class="pickup-signatures-grid">
                        <div class="pickup-sig-col">
                            <div class="pickup-sig-title">Teknisi</div>
                            <div class="pickup-sig-space"></div>
                            <div class="pickup-sig-name">{{ $ticket->technician?->name ?? 'gema' }}</div>
                        </div>
                        <div class="pickup-sig-col">
                            <div class="pickup-sig-title">QC</div>
                            <div class="pickup-sig-space"></div>
                            <div class="pickup-sig-name">........</div>
                        </div>
                        <div class="pickup-sig-col">
                            <div class="pickup-sig-title">Penyerah</div>
                            <div class="pickup-sig-space"></div>
                            <div class="pickup-sig-name">{{ $operatorName }}</div>
                        </div>
                        <div class="pickup-sig-col">
                            <div class="pickup-sig-title">Pengambil</div>
                            <div class="pickup-sig-space"></div>
                            <div class="pickup-sig-name">{{ $ticket->customer?->name ?? 'Pelanggan' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
<style>
/* ==========================================================================
   STYLING PERSIS NOTA SERVIS PENGAMBILAN (AR CELL REFERENCE)
   ========================================================================== */
.pickup-sheet-wrapper {
    width: 215mm;
    min-height: 110mm;
    max-height: 140mm;
    margin: 0 auto 20px;
    background: #fff;
    color: #000;
    padding: 6mm 8mm;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Consolas', 'Courier New', monospace;
    font-size: 10px;
    line-height: 1.25;
    border: 1px solid #ddd;
    box-sizing: border-box;
}

/* Watermark Stamp di background tengah */
.pickup-watermark-stamp {
    position: absolute;
    top: 52%;
    left: 48%;
    transform: translate(-50%, -50%) rotate(-12deg);
    font-size: 46px;
    font-weight: 900;
    letter-spacing: 5px;
    color: rgba(2, 132, 199, 0.08);
    border: 3px solid rgba(2, 132, 199, 0.12);
    padding: 6px 28px;
    border-radius: 8px;
    text-transform: uppercase;
    pointer-events: none;
    z-index: 1;
    white-space: nowrap;
}

/* 1. Header */
.pickup-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
    position: relative;
    z-index: 2;
}

.pickup-header-left {
    flex: 1;
}

.pickup-main-title {
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
}

.pickup-meta-table {
    border-collapse: collapse;
    font-size: 9.5px;
}
.pickup-meta-table td {
    padding: 0.5px 0;
    vertical-align: top;
}
.pickup-lbl {
    width: 65px;
    color: #111;
}
.pickup-col {
    width: 12px;
    text-align: center;
}
.pickup-val {
    color: #000;
}
.font-bold {
    font-weight: 700;
}

.pickup-header-right {
    text-align: right;
    max-width: 50%;
}
.pickup-store-name {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.5px;
}
.pickup-store-tagline {
    font-size: 9px;
    margin-top: 1px;
}
.pickup-store-address,
.pickup-store-contact {
    font-size: 8.5px;
    color: #222;
}

/* 2. Items Table */
.pickup-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    margin-bottom: 4px;
    font-size: 9.5px;
    border-top: 1px solid #000;
    border-bottom: 1px solid #000;
    position: relative;
    z-index: 2;
}

.pickup-items-table th {
    padding: 3px 4px;
    font-weight: 700;
    text-align: left;
    border-bottom: 1px solid #000;
    font-size: 9px;
    white-space: nowrap;
}

.pickup-items-table td {
    padding: 4px 4px;
    border: none;
}

.pickup-subtext {
    font-size: 8.5px;
    color: #444;
}

/* 3. Catatan & Total Section */
.pickup-total-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 4px;
    border-bottom: 1px solid #000;
    position: relative;
    z-index: 2;
    font-size: 9.5px;
}

.pickup-notes-col {
    flex: 1;
    display: flex;
    gap: 4px;
}
.pickup-notes-val {
    color: #111;
}

.pickup-amounts-col {
    width: 190px;
    text-align: right;
}
.pickup-amount-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5px 0;
}
.pickup-amount-lbl {
    font-weight: 700;
}
.pickup-amount-val {
    font-weight: 700;
    min-width: 75px;
    text-align: right;
}

/* 4. QR Code Center Box */
.pickup-qr-center-box {
    text-align: center;
    margin: 6px auto 4px;
    position: relative;
    z-index: 2;
}
.pickup-qr-img {
    display: inline-block;
    border: 1px solid #000;
    padding: 1px;
    background: #fff;
    width: 74px;
    height: 74px;
}
.pickup-qr-caption {
    font-size: 8.5px;
    font-weight: 600;
    margin-top: 2px;
    letter-spacing: 0.2px;
}

/* 5. Footer: Terms & Signatures */
.pickup-footer-grid {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 4px;
    position: relative;
    z-index: 2;
}

.pickup-footer-left {
    flex: 1;
    font-size: 8.5px;
    line-height: 1.3;
}
.pickup-status-line {
    font-style: italic;
    font-size: 8px;
    color: #111;
}

.pickup-terms-box {
    margin-top: 3px;
}
.pickup-terms-title {
    font-weight: 700;
    font-size: 8px;
}
.pickup-terms-list {
    margin: 1px 0 0 14px;
    padding: 0;
    font-size: 8px;
    line-height: 1.25;
}

.pickup-footer-right {
    width: 250px;
}
.pickup-signatures-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    text-align: center;
    gap: 4px;
}
.pickup-sig-title {
    font-size: 8.5px;
    font-weight: 600;
}
.pickup-sig-space {
    height: 22px;
}
.pickup-sig-name {
    font-size: 8.5px;
    border-top: 1px dotted #555;
    padding-top: 1px;
    min-height: 12px;
}

/* PRINT MEDIA STYLING */
@media print {
    @page {
        size: 215mm 110mm landscape;
        margin: 0;
    }
    body.format-dotmatrix @page {
        size: 215mm 140mm landscape;
        margin: 0;
    }
    body {
        margin: 0;
        padding: 0;
        background: #fff !important;
        color: #000 !important;
    }
    .no-print {
        display: none !important;
    }
    .pickup-sheet-wrapper {
        width: 100% !important;
        min-height: 100% !important;
        margin: 0 !important;
        padding: 5mm 7mm !important;
        box-shadow: none !important;
        border: none !important;
    }
}
</style>
@endpush

@push('js')
<script>
function switchFormat(fmt) {
    document.body.classList.remove('format-third', 'format-dotmatrix');
    document.body.classList.add('format-' + fmt);

    document.querySelectorAll('.btn-ncr').forEach(b => b.classList.remove('btn-ncr--active'));
    if (fmt === 'third') {
        document.getElementById('btn-toggle-third')?.classList.add('btn-ncr--active');
    } else {
        document.getElementById('btn-toggle-dotmatrix')?.classList.add('btn-ncr--active');
    }
}
</script>
@endpush
