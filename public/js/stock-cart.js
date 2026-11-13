/**
 * Keranjang stok masuk / keluar — multi barang
 */
(function ($) {
    'use strict';

    window.AthaStockCart = {
        init: function (options) {
            const settings = $.extend({
                table: '#data-table',
                formModal: '#form-modal',
                showModal: '#show-modal',
                storeUrl: '',
                batchShowUrl: '',
                items: [],
                type: 'in',
                quantityPrefix: '+',
            }, options);

            const cart = [];
            const $table = $(settings.table);
            const $formModal = $(settings.formModal);
            const $showModal = $(settings.showModal);
            const $cartBody = $('#stock-cart-body');
            const $cartEmpty = $('#stock-cart-empty');
            const $cartCount = $('#stock-cart-count');
            const formModal = bootstrap.Modal.getOrCreateInstance($formModal[0], { backdrop: 'static' });
            const showModal = bootstrap.Modal.getOrCreateInstance($showModal[0], { backdrop: 'static' });

            function findItem(id) {
                return settings.items.find(function (item) {
                    return String(item.id) === String(id);
                });
            }

            function renderCart(focusIndex) {
                $cartBody.empty();
                $cartCount.text(cart.length);

                if (!cart.length) {
                    $cartEmpty.show();
                    $('#btn-submit-cart').prop('disabled', true);
                    return;
                }

                $cartEmpty.hide();
                $('#btn-submit-cart').prop('disabled', false);

                cart.forEach(function (line, index) {
                    const prefix = settings.type === 'in' ? '+' : '-';
                    $cartBody.append(`
                        <tr>
                            <td>
                                <div class="fw-medium">${line.code}</div>
                                <div class="text-muted small">${line.name}</div>
                            </td>
                            <td class="text-center">${Number(line.stock).toLocaleString('id-ID')}</td>
                            <td class="text-center" style="width:130px;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text px-2">${prefix}</span>
                                    <input type="number" class="form-control form-control-sm text-center cart-qty-input"
                                        data-index="${index}"
                                        value="${line.quantity}"
                                        min="1"
                                        step="1"
                                        ${settings.type === 'out' ? `max="${line.stock}"` : ''}
                                        title="Ubah jumlah">
                                </div>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-cart" data-index="${index}" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });

                if (typeof focusIndex === 'number' && focusIndex >= 0) {
                    const $input = $cartBody.find(`.cart-qty-input[data-index="${focusIndex}"]`);
                    if ($input.length) {
                        setTimeout(function () {
                            $input.trigger('focus').select();
                        }, 50);
                    }
                }
            }

            function resetForm() {
                cart.length = 0;
                renderCart();
                $('#stock-add-form')[0].reset();
                if (window.AthaSearchableSelect) {
                    AthaSearchableSelect.clear('#cart_item_id');
                } else {
                    $('#cart_item_id').val(null).trigger('change');
                }
                $('#stock-hint').text('');
                $('#reference_no, #notes').val('');
            }

            function clearSelect() {
                if (window.AthaSearchableSelect) {
                    AthaSearchableSelect.clear('#cart_item_id');
                } else {
                    $('#cart_item_id').val(null).trigger('change');
                }
            }

            function addItemToCart(itemId, quantity) {
                if (!itemId) {
                    return;
                }
                const item = findItem(itemId);
                if (!item) {
                    return;
                }

                quantity = parseInt(quantity, 10);
                if (isNaN(quantity) || quantity < 1) {
                    quantity = 1;
                }

                if (settings.type === 'out') {
                    if (item.stock <= 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Stok kosong',
                            text: item.name + ' tidak memiliki stok (0).',
                        });
                        clearSelect();
                        return;
                    }
                    if (quantity > item.stock) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Stok tidak mencukupi',
                            text: 'Tersedia: ' + Number(item.stock).toLocaleString('id-ID'),
                        });
                        clearSelect();
                        return;
                    }
                }

                let targetIndex = -1;
                const existingIndex = cart.findIndex(function (line) {
                    return String(line.item_id) === String(itemId);
                });

                if (existingIndex >= 0) {
                    const existing = cart[existingIndex];
                    const newQty = existing.quantity + quantity;
                    if (settings.type === 'out' && newQty > item.stock) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Total melebihi stok tersedia',
                            text: 'Tersedia: ' + Number(item.stock).toLocaleString('id-ID'),
                        });
                        clearSelect();
                        return;
                    }
                    existing.quantity = newQty;
                    targetIndex = existingIndex;
                } else {
                    cart.push({
                        item_id: item.id,
                        code: item.code,
                        name: item.name,
                        stock: item.stock,
                        quantity: quantity,
                    });
                    targetIndex = cart.length - 1;
                }

                renderCart(targetIndex);
                $('#cart_quantity').val('');
                clearSelect();
            }

            $('[data-action="create"]').on('click', function () {
                resetForm();
                formModal.show();
            });

            $('#stock-add-form').on('submit', function (e) {
                e.preventDefault();
                const itemId = $('#cart_item_id').val();
                if (!itemId) {
                    Swal.fire({ icon: 'warning', title: 'Pilih barang terlebih dahulu.' });
                    return;
                }
                const quantity = parseInt($('#cart_quantity').val(), 10) || 1;
                addItemToCart(itemId, quantity);
            });

            $cartBody.on('click', '.btn-remove-cart', function () {
                cart.splice($(this).data('index'), 1);
                renderCart();
            });

            // Edit jumlah langsung di keranjang
            $cartBody.on('input change', '.cart-qty-input', function () {
                const index = parseInt($(this).data('index'), 10);
                const line = cart[index];
                if (!line) {
                    return;
                }

                let newQty = parseInt($(this).val(), 10);
                if (isNaN(newQty) || newQty < 1) {
                    newQty = 1;
                }

                if (settings.type === 'out') {
                    if (newQty > line.stock) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Stok tidak mencukupi',
                            text: line.name + ' — stok tersedia hanya ' + Number(line.stock).toLocaleString('id-ID'),
                        });
                        newQty = Math.max(1, line.stock);
                        $(this).val(newQty);
                    }
                }

                line.quantity = newQty;
            });

            $('#cart_item_id').on('change', function () {
                const itemId = $(this).val();
                if (!itemId) {
                    $('#stock-hint').text('');
                    $('#cart_quantity').removeAttr('max');
                    return;
                }
                const item = findItem(itemId);
                if (!item) {
                    return;
                }
                $('#stock-hint').text('Stok tersedia: ' + Number(item.stock).toLocaleString('id-ID'));
                if (settings.type === 'out') {
                    $('#cart_quantity').attr('max', item.stock);
                }

                // Auto-add barang ke keranjang saat dipilih
                const qty = parseInt($('#cart_quantity').val(), 10) || 1;
                addItemToCart(itemId, qty);
            });

            $('#btn-submit-cart').on('click', function () {
                if (!cart.length) {
                    return;
                }

                const $btn = $(this).prop('disabled', true);

                $.ajax({
                    url: settings.storeUrl,
                    type: 'POST',
                    headers: { Accept: 'application/json' },
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        reference_no: $('#reference_no').val(),
                        notes: $('#notes').val(),
                        items: cart.map(function (line) {
                            return { item_id: line.item_id, quantity: line.quantity };
                        }),
                    },
                    success: function (res) {
                        formModal.hide();
                        Swal.fire({ icon: 'success', title: res.message, timer: 1800, showConfirmButton: false });
                        $table.DataTable().ajax.reload(null, false);
                        location.reload();
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: xhr.responseJSON?.message || 'Gagal menyimpan.' });
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    },
                });
            });

            $table.on('click', '[data-action="show-batch"]', function () {
                const batchNo = $(this).data('batch');
                const $body = $showModal.find('.modal-body');
                const prefix = settings.type === 'in' ? '+' : '-';

                $body.html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>');
                showModal.show();

                $.get(settings.batchShowUrl.replace('__BATCH__', encodeURIComponent(batchNo)), function (res) {
                    const d = res.data;
                    let rows = d.items.map(function (item) {
                        return `<tr>
                            <td>${item.code} — ${item.name}</td>
                            <td class="text-center">${prefix}${Number(item.quantity).toLocaleString('id-ID')}</td>
                            <td class="text-center text-muted small">${Number(item.stock_before).toLocaleString('id-ID')} → ${Number(item.stock_after).toLocaleString('id-ID')}</td>
                        </tr>`;
                    }).join('');

                    $body.html(`
                        <dl class="detail-list mb-3">
                            <dt>No. Transaksi</dt><dd>${d.batch_no}</dd>
                            <dt>Referensi Eksternal</dt><dd>${d.reference_no || '-'}</dd>
                            <dt>Catatan</dt><dd>${d.notes || '-'}</dd>
                            <dt>Petugas</dt><dd>${d.user?.name || '-'}</dd>
                            <dt>Waktu</dt><dd>${new Date(d.created_at).toLocaleString('id-ID')}</dd>
                        </dl>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th class="text-center">Jumlah</th>
                                        <th class="text-center">Stok</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    `);
                }).fail(function () {
                    $body.html('<p class="text-danger mb-0">Gagal memuat detail.</p>');
                });
            });
        },
    };
})(jQuery);
