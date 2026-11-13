@extends('layouts.print')

@section('title', 'Nota '.$transaction->transaction_no)

@php
    $initialFormat = request('format', 'third');
@endphp

@section('body-class', 'format-'.$initialFormat)

@section('content')
    @php
        $paymentLabel = \App\Support\PaymentMethodResolver::label($transaction->payment_method);
        if ($transaction->payment_method === 'transfer' && $transaction->bankAccount) {
            $paymentLabel .= ' — '.$transaction->bankAccount->displayLabel();
        }
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $printedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $txDate = $transaction->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $hasItems = (float) $transaction->subtotal_items > 0;
        $hasServices = (float) $transaction->subtotal_services > 0;
        $showSubtotals = $hasItems && $hasServices;

        $lines = collect();
        foreach ($transaction->items as $item) {
            $lines->push([
                'type' => 'item',
                'code' => $item->item_code ?? '-',
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit' => $item->item?->unit?->abbreviation ?? 'pcs',
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ]);
        }
        foreach ($transaction->serviceLines as $line) {
            $lines->push([
                'type' => 'service',
                'code' => $line->service_code ?? 'JASA',
                'name' => $line->service_name,
                'quantity' => $line->quantity,
                'unit' => 'jasa',
                'unit_price' => $line->unit_price,
                'subtotal' => $line->subtotal,
            ]);
        }

        $customerName = $transaction->displayCustomerName();
        $customerPhone = $transaction->customer?->phone ?? '-';
        $customerAddress = $transaction->customer?->address ?? '-';

        $ticket = $transaction->serviceTicket;
        $isService = (bool) $ticket;
        $isDp = $transaction->isDownPayment();

        $invoiceTitle = $isDp
            ? 'TANDA TERIMA UANG MUKA (DP)'
            : ($isService ? 'NOTA SERVICE & PERBAIKAN' : 'FAKTUR PENJUALAN');

        $terbilangText = function_exists('terbilang')
            ? '# '.terbilang($transaction->total).' Rupiah #'
            : '';
    @endphp

    {{-- TOOLBAR KONTROL CETAK & FORMAT (Hanya tampil di layar, tidak tercetak) --}}
    <div class="ncr-toolbar no-print">
        <div class="ncr-toolbar-group">
            <span style="font-weight: 600; color: #94a3b8; margin-right: 4px;">Ukuran:</span>
            <button type="button" class="btn-ncr btn-ncr--active" id="btn-toggle-third" onclick="switchFormat('third')">
                <i class="bi bi-file-earmark-text"></i> 1/3 Folio (21.5 × 11 cm)
            </button>
            <button type="button" class="btn-ncr" id="btn-toggle-dotmatrix" onclick="switchFormat('dotmatrix')">
                <i class="bi bi-printer"></i> 1/2 Folio / NCR (21.5 × 14 cm)
            </button>
            <button type="button" class="btn-ncr" id="btn-toggle-thermal" onclick="switchFormat('thermal')">
                <i class="bi bi-receipt"></i> Struk Thermal (58mm)
            </button>
        </div>
        <div class="ncr-toolbar-group">
            <span style="font-weight: 600; color: #94a3b8; margin-right: 4px;">Orientasi:</span>
            <button type="button" class="btn-ncr btn-ncr--active" id="btn-orient-portrait" onclick="switchOrientation('portrait')">
                <i class="bi bi-file-earmark"></i> Potret (Tegak)
            </button>
            <button type="button" class="btn-ncr" id="btn-orient-landscape" onclick="switchOrientation('landscape')">
                <i class="bi bi-file-earmark-landscape"></i> Lanskap
            </button>
        </div>
        <div class="ncr-toolbar-group">
            <button type="button" class="btn-ncr btn-ncr--primary" onclick="window.print()">
                <strong>Cetak Sekarang</strong>
            </button>
            <button type="button" class="btn-ncr btn-ncr--light" onclick="window.close()">Tutup</button>
        </div>
    </div>

    {{-- =========================================================================
         TAMPILAN 1: FORMAT LEMBARAN 1/3 FOLIO (21.5 x 11 CM) & 1/2 FOLIO (21.5 x 14 CM)
         ========================================================================= --}}
    <div class="format-dotmatrix-view">
        <div class="ncr-paper-wrapper">
            {{-- EMBOSS / WATERMARK ARCell DI TENGAH NOTA --}}
            <div class="ncr-watermark" aria-hidden="true">ARCell</div>

            <div class="ncr-sheet">
                {{-- HEADER TOKO & JUDUL NOTA --}}
                <div class="ncr-header">
                    <div class="ncr-brand">
                        <div class="ncr-brand-name">{{ brand_name() }}</div>
                        <div class="ncr-brand-sub">{{ brand_tagline() ?: 'Service Smartphone & Laptop' }}</div>
                        <div class="ncr-brand-contact">
                            jl. Perwira no.8 kapangsari Petarukan<br>
                            Hp: 082322872201 / 087733300186
                        </div>
                    </div>
                    <div class="ncr-title-box">
                        <div class="ncr-title">{{ $invoiceTitle }}</div>
                        <div class="ncr-title-meta">No. Nota : <strong>{{ $transaction->transaction_no }}</strong></div>
                        <div class="ncr-title-meta">Tanggal  : {{ $txDate }}</div>
                        <div class="ncr-title-meta">Kasir    : {{ $transaction->user?->name ?? 'Admin' }}</div>
                    </div>
                </div>

                {{-- INFO PELANGGAN & PERANGKAT --}}
                <div class="ncr-info-grid">
                    <div class="ncr-info-col">
                        <div class="ncr-info-row">
                            <span class="ncr-info-label">Kepada Yth.</span>
                            <span class="ncr-info-colon">:</span>
                            <span class="ncr-info-value">{{ $customerName }}</span>
                        </div>
                        <div class="ncr-info-row">
                            <span class="ncr-info-label">No. Telepon / WA</span>
                            <span class="ncr-info-colon">:</span>
                            <span class="ncr-info-value">{{ $customerPhone }}</span>
                        </div>
                        <div class="ncr-info-row">
                            <span class="ncr-info-label">Alamat</span>
                            <span class="ncr-info-colon">:</span>
                            <span class="ncr-info-value">{{ $customerAddress }}</span>
                        </div>
                    </div>
                    <div class="ncr-info-col">
                        @if ($ticket)
                            <div class="ncr-info-row">
                                <span class="ncr-info-label">No. Tiket / Unit</span>
                                <span class="ncr-info-colon">:</span>
                                <span class="ncr-info-value">{{ $ticket->ticket_no }} — {{ $ticket->deviceSummary() }}</span>
                            </div>
                            <div class="ncr-info-row">
                                <span class="ncr-info-label">IMEI / SN</span>
                                <span class="ncr-info-colon">:</span>
                                <span class="ncr-info-value">{{ $ticket->serial_imei ?: '-' }}</span>
                            </div>
                            <div class="ncr-info-row">
                                <span class="ncr-info-label">Teknisi / Status</span>
                                <span class="ncr-info-colon">:</span>
                                <span class="ncr-info-value">{{ $transaction->technician?->name ?? $ticket->technician?->name ?? '-' }} ({{ $ticket->statusLabel() }})</span>
                            </div>
                        @else
                            <div class="ncr-info-row">
                                <span class="ncr-info-label">Jenis Transaksi</span>
                                <span class="ncr-info-colon">:</span>
                                <span class="ncr-info-value">Penjualan Langsung (POS)</span>
                            </div>
                            <div class="ncr-info-row">
                                <span class="ncr-info-label">Pembayaran</span>
                                <span class="ncr-info-colon">:</span>
                                <span class="ncr-info-value">{{ $paymentLabel }}</span>
                            </div>
                            <div class="ncr-info-row">
                                <span class="ncr-info-label">Teknisi Reff</span>
                                <span class="ncr-info-colon">:</span>
                                <span class="ncr-info-value">{{ $transaction->technician?->name ?? '-' }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- TABEL BARANG / JASA --}}
                <table class="ncr-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 25px;">NO</th>
                            <th class="text-left" style="width: 110px;">KODE</th>
                            <th class="text-left">DESKRIPSI BARANG / JASA LAYANAN</th>
                            <th class="text-center" style="width: 45px;">QTY</th>
                            <th class="text-center" style="width: 45px;">SAT</th>
                            <th class="text-right" style="width: 105px;">HARGA</th>
                            <th class="text-right" style="width: 115px;">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lines as $idx => $line)
                            <tr>
                                <td class="text-center">{{ $idx + 1 }}</td>
                                <td class="text-left">{{ $line['code'] }}</td>
                                <td class="text-left"><strong>{{ $line['name'] }}</strong></td>
                                <td class="text-center">{{ number_format($line['quantity']) }}</td>
                                <td class="text-center">{{ strtoupper($line['unit']) }}</td>
                                <td class="text-right">{{ $rp($line['unit_price']) }}</td>
                                <td class="text-right"><strong>{{ $rp($line['subtotal']) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center" style="padding: 8px;">Tidak ada rincian transaksi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- BAGIAN BAWAH: TERBILANG, SYARAT, TANDA TANGAN & TOTAL --}}
                <div class="ncr-bottom">
                    <div class="ncr-bottom-left">
                        @if ($terbilangText)
                            <div class="ncr-terbilang-box">
                                <strong>Terbilang:</strong> {{ $terbilangText }}
                            </div>
                        @endif

                        <div class="ncr-terms">
                            <strong>Perhatian & Ketentuan:</strong><br>
                            @if ($ticket && ! $isDp)
                                • Garansi Servis: <strong>{{ $ticket->warrantySummary() }}</strong> {{ $ticket->warranty_notes ? '('.$ticket->warranty_notes.')' : '' }}<br>
                            @endif
                            • Barang yang sudah dibeli/diservis tidak dapat ditukar tanpa persetujuan toko.<br>
                            • Nota ini adalah bukti sah serah terima dan pembayaran. Simpan baik-baik.
                        </div>

                        <div class="ncr-signatures">
                            <div class="ncr-sign-col">
                                <div>Tanda Terima Pelanggan,</div>
                                <div class="ncr-sign-space"></div>
                                <div class="ncr-sign-name">( {{ $customerName }} )</div>
                            </div>
                            <div class="ncr-sign-col">
                                <div>Hormat Kami,</div>
                                <div class="ncr-sign-space"></div>
                                <div class="ncr-sign-name">( {{ brand_name() }} )</div>
                            </div>
                        </div>
                    </div>

                    <div class="ncr-bottom-right">
                        <table class="ncr-summary-table">
                            @if ($showSubtotals)
                                <tr>
                                    <td>Subtotal Barang</td>
                                    <td class="text-right">{{ $rp($transaction->subtotal_items) }}</td>
                                </tr>
                                <tr>
                                    <td>Subtotal Jasa</td>
                                    <td class="text-right">{{ $rp($transaction->subtotal_services) }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td>Subtotal</td>
                                    <td class="text-right">{{ $rp($transaction->subtotal_items + $transaction->subtotal_services) }}</td>
                                </tr>
                            @endif

                            @if ((float) $transaction->discount > 0)
                                <tr>
                                    <td>Diskon Potongan</td>
                                    <td class="text-right">- {{ $rp($transaction->discount) }}</td>
                                </tr>
                            @endif

                            @if ($isDp)
                                <tr class="ncr-summary-total">
                                    <td>TOTAL UANG MUKA (DP)</td>
                                    <td class="text-right">{{ $rp($transaction->total) }}</td>
                                </tr>
                            @else
                                @if ($transaction->downPaymentAmount() > 0)
                                    <tr>
                                        <td>Total Biaya</td>
                                        <td class="text-right">{{ $rp($transaction->jobTotal()) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Uang Muka (DP)</td>
                                        <td class="text-right">- {{ $rp($transaction->downPaymentAmount()) }}</td>
                                    </tr>
                                    <tr class="ncr-summary-total">
                                        <td>SISA BAYAR (PELUNASAN)</td>
                                        <td class="text-right">{{ $rp($transaction->total) }}</td>
                                    </tr>
                                @else
                                    <tr class="ncr-summary-total">
                                        <td>TOTAL AKHIR</td>
                                        <td class="text-right">{{ $rp($transaction->total) }}</td>
                                    </tr>
                                @endif
                            @endif

                            @if ($transaction->payment_method === 'cash' && $transaction->cash_received !== null)
                                <tr>
                                    <td>Bayar Tunai</td>
                                    <td class="text-right">{{ $rp($transaction->cash_received) }}</td>
                                </tr>
                                <tr>
                                    <td>Kembali</td>
                                    <td class="text-right">{{ $rp($transaction->cash_change ?? 0) }}</td>
                                </tr>
                            @endif

                            <tr>
                                <td colspan="2" class="text-right" style="padding-top: 4px;">
                                    <div class="ncr-payment-badge">
                                        {{ $isDp ? 'DP DITERIMA' : 'STATUS: LUNAS' }} ({{ strtoupper($paymentLabel) }})
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         TAMPILAN 2: FORMAT THERMAL (58MM)
         ========================================================================= --}}
    <div class="format-thermal-view">
        <article class="receipt-thermal">
            @if (brand_has_custom_logo())
                <div class="receipt-center receipt-logo-wrap">
                    <img src="{{ brand_logo_url() }}" alt="{{ brand_name() }}" class="receipt-logo">
                </div>
            @endif
            <div class="receipt-center receipt-brand">{{ brand_name() }}</div>
            @if (brand_address() !== '')
                <div class="receipt-center receipt-meta">{{ brand_address() }}</div>
            @endif
            @if (brand_whatsapp() !== '')
                <div class="receipt-center receipt-meta">WA: {{ brand_whatsapp() }}</div>
            @endif
            <div class="receipt-center receipt-title">{{ $invoiceTitle }}</div>
            <div class="receipt-center receipt-meta">{{ $transaction->transaction_no }} · {{ $txDate }}</div>
            @if ($transaction->serviceTicket)
                <div class="receipt-center receipt-meta">Tiket {{ $transaction->serviceTicket->ticket_no }} · {{ $transaction->serviceTicket->deviceSummary() }}</div>
            @endif

            <div class="receipt-sep"></div>

            <div class="receipt-text receipt-text--strong">{{ $customerName }}</div>
            @if ($customerPhone !== '-' || $customerAddress !== '-')
                <div class="receipt-text receipt-text--muted">{{ collect([$customerPhone, $customerAddress])->filter(fn($v)=>$v!=='-')->implode(' · ') }}</div>
            @endif
            <div class="receipt-row receipt-row--tight">
                <span>{{ $paymentLabel }}</span>
                @if ($transaction->technician)
                    <span>{{ $transaction->technician->name }}</span>
                @endif
            </div>

            <div class="receipt-sep"></div>

            @forelse ($lines as $line)
                <div class="receipt-item">
                    <div class="receipt-row receipt-row--tight">
                        <span class="receipt-item-name">{{ $line['name'] }}</span>
                        <span>{{ $rp($line['subtotal']) }}</span>
                    </div>
                    <div class="receipt-text receipt-text--muted">{{ number_format($line['quantity']) }} × {{ $rp($line['unit_price']) }}</div>
                </div>
            @empty
                <div class="receipt-text receipt-center">Tidak ada rincian.</div>
            @endforelse

            <div class="receipt-sep"></div>

            @if ($showSubtotals)
                <div class="receipt-row receipt-row--tight">
                    <span>Barang</span>
                    <span>{{ $rp($transaction->subtotal_items) }}</span>
                </div>
                <div class="receipt-row receipt-row--tight">
                    <span>Jasa</span>
                    <span>{{ $rp($transaction->subtotal_services) }}</span>
                </div>
            @endif
            @if ((float) $transaction->discount > 0)
                <div class="receipt-row receipt-row--tight">
                    <span>Diskon</span>
                    <span>- {{ $rp($transaction->discount) }}</span>
                </div>
            @endif
            @if ($transaction->isDownPayment())
                <div class="receipt-row receipt-row--total">
                    <span>UANG MUKA</span>
                    <span>{{ $rp($transaction->total) }}</span>
                </div>
            @else
                @if ($transaction->downPaymentAmount() > 0)
                    <div class="receipt-row receipt-row--tight">
                        <span>Total</span>
                        <span>{{ $rp($transaction->jobTotal()) }}</span>
                    </div>
                    <div class="receipt-row receipt-row--tight">
                        <span>Uang muka</span>
                        <span>- {{ $rp($transaction->downPaymentAmount()) }}</span>
                    </div>
                @endif
                <div class="receipt-row receipt-row--total">
                    <span>{{ $transaction->downPaymentAmount() > 0 ? 'SISA BAYAR' : 'TOTAL' }}</span>
                    <span>{{ $rp($transaction->total) }}</span>
                </div>
            @endif

            @if ($transaction->payment_method === 'cash' && $transaction->cash_received !== null)
                <div class="receipt-row receipt-row--tight">
                    <span>Bayar</span>
                    <span>{{ $rp($transaction->cash_received) }}</span>
                </div>
                <div class="receipt-row receipt-row--tight">
                    <span>Kembali</span>
                    <span>{{ $rp($transaction->cash_change ?? 0) }}</span>
                </div>
            @endif

            <div class="receipt-center receipt-status">{{ $transaction->isDownPayment() ? 'DP DITERIMA' : 'LUNAS' }}</div>

            @if ($transaction->serviceTicket && ! $transaction->isDownPayment())
                <div class="receipt-sep"></div>
                <div class="receipt-center receipt-warranty">
                    Garansi: {{ $transaction->serviceTicket->warrantySummary() }}
                </div>
                @if (filled($transaction->serviceTicket->warranty_notes))
                    <div class="receipt-center receipt-text receipt-text--muted">{{ $transaction->serviceTicket->warranty_notes }}</div>
                @endif
            @endif

            @if ($transaction->notes)
                <div class="receipt-sep"></div>
                <div class="receipt-text receipt-text--muted"><strong>Cat:</strong> {{ $transaction->notes }}</div>
            @endif

            <div class="receipt-sep"></div>
            <div class="receipt-center receipt-footer">Terima kasih · Sah tanpa tanda tangan</div>
            <div class="receipt-center receipt-text--muted">{{ $printedAt }}</div>
        </article>
    </div>
@endsection

@push('js')
    <script>
        let currentFormat = 'third';
        let currentOrientation = 'portrait';

        function updatePrintStyle() {
            const styleTag = document.getElementById('dynamic-page-size');
            if (!styleTag) return;

            if (currentFormat === 'thermal') {
                styleTag.innerHTML = '@media print { @page { size: 58mm auto; margin: 1mm !important; } }';
            } else {
                styleTag.innerHTML = `@media print { @page { size: ${currentOrientation}; margin: 0 !important; } }`;
            }
        }

        function switchOrientation(orient) {
            currentOrientation = orient;
            const btnPort = document.getElementById('btn-orient-portrait');
            const btnLand = document.getElementById('btn-orient-landscape');
            if (btnPort) btnPort.classList.toggle('btn-ncr--active', orient === 'portrait');
            if (btnLand) btnLand.classList.toggle('btn-ncr--active', orient === 'landscape');

            document.body.classList.remove('orient-portrait', 'orient-landscape');
            document.body.classList.add('orient-' + orient);

            updatePrintStyle();
            try {
                localStorage.setItem('ar_invoice_orientation', orient);
            } catch (e) {}
        }

        function switchFormat(format) {
            currentFormat = format;
            document.body.classList.remove('format-third', 'format-dotmatrix', 'format-thermal');
            document.body.classList.add('format-' + format);

            const btnThird = document.getElementById('btn-toggle-third');
            const btnDot = document.getElementById('btn-toggle-dotmatrix');
            const btnThermal = document.getElementById('btn-toggle-thermal');

            if (btnThird) btnThird.classList.toggle('btn-ncr--active', format === 'third');
            if (btnDot) btnDot.classList.toggle('btn-ncr--active', format === 'dotmatrix');
            if (btnThermal) btnThermal.classList.toggle('btn-ncr--active', format === 'thermal');

            updatePrintStyle();
            try {
                localStorage.setItem('ar_invoice_format', format);
            } catch (e) {}
        }

        // Inisialisasi format dan orientasi tersimpan
        (function() {
            try {
                const savedFormat = localStorage.getItem('ar_invoice_format');
                const savedOrient = localStorage.getItem('ar_invoice_orientation');
                const urlParam = new URLSearchParams(window.location.search).get('format');
                const urlOrient = new URLSearchParams(window.location.search).get('orientation');

                const targetFormat = urlParam || savedFormat || 'third';
                const targetOrient = urlOrient || savedOrient || 'portrait';

                switchFormat(targetFormat);
                switchOrientation(targetOrient);
            } catch (e) {}
        })();

        @if (request()->boolean('print'))
            window.addEventListener('load', function () {
                window.print();
            });
        @endif
    </script>
@endpush
