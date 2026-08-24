/**
 * Keranjang jasa + barang pada tiket servis (draft, tidak memotong stok)
 */
(function ($) {
    'use strict';

    function formatRp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function cloneLines(lines) {
        return (lines || []).map(function (line) {
            return $.extend({}, line);
        });
    }

    window.AthaTicketWorkLines = {
        init: function (options) {
            const settings = $.extend({
                itemsCatalog: [],
                servicesCatalog: [],
                initialItems: [],
                initialServices: [],
                containerSelector: '#ticket-work-lines',
                helperText: 'Wajib isi minimal 1 jasa atau barang sebelum status Bisa diambil',
            }, options);

            const $root = $(settings.containerSelector);
            if (!$root.length) {
                return null;
            }

            const itemLines = [];
            const serviceLines = [];

            function findItem(id) {
                return settings.itemsCatalog.find(function (i) {
                    return String(i.id) === String(id);
                });
            }

            function findService(id) {
                return settings.servicesCatalog.find(function (s) {
                    return String(s.id) === String(id);
                });
            }

            function itemPrice(item) {
                return parseFloat(item && (item.selling_price != null ? item.selling_price : item.price)) || 0;
            }

            function servicePrice(service) {
                return parseFloat(service && service.price) || 0;
            }

            function lineSubtotal(line) {
                return Math.round((parseFloat(line.unit_price) || 0) * (parseInt(line.quantity, 10) || 0));
            }

            function catalogOptions(rows, emptyLabel) {
                let html = '<option value="">' + escapeHtml(emptyLabel) + '</option>';
                (rows || []).forEach(function (row) {
                    const price = row.selling_price != null ? row.selling_price : row.price;
                    const stock = row.stock != null ? ' · Stok ' + Number(row.stock).toLocaleString('id-ID') : '';
                    html += '<option value="' + escapeHtml(row.id) + '">'
                        + escapeHtml((row.code ? row.code + ' — ' : '') + row.name)
                        + ' (' + formatRp(price) + stock + ')'
                        + '</option>';
                });
                return html;
            }

            function buildShell() {
                $root.addClass('ticket-work-lines');
                $root.html(
                    '<p class="form-hint-sm mb-2">' + escapeHtml(settings.helperText) + '</p>'
                    + '<div class="mb-3">'
                    +   '<label class="form-label mb-1">Jasa servis</label>'
                    +   '<div class="row g-2 align-items-end mb-2">'
                    +     '<div class="col-12"><select class="form-control form-control-clean js-work-service-select"></select></div>'
                    +     '<div class="col-4"><label class="form-label small mb-0">Qty</label>'
                    +       '<input type="number" class="form-control form-control-clean js-work-service-qty" min="1" step="1" value="1"></div>'
                    +     '<div class="col-5"><label class="form-label small mb-0">Harga</label>'
                    +       '<input type="number" class="form-control form-control-clean js-work-service-price" min="0" step="1" value="0"></div>'
                    +     '<div class="col-3"><button type="button" class="btn btn-outline-primary w-100 js-work-add-service" title="Tambah jasa"><i class="bi bi-plus-lg"></i></button></div>'
                    +   '</div>'
                    +   '<div class="js-work-service-list"></div>'
                    + '</div>'
                    + '<div class="mb-2">'
                    +   '<label class="form-label mb-1">Barang / sparepart</label>'
                    +   '<div class="row g-2 align-items-end mb-2">'
                    +     '<div class="col-12"><select class="form-control form-control-clean js-work-item-select"></select></div>'
                    +     '<div class="col-4"><label class="form-label small mb-0">Qty</label>'
                    +       '<input type="number" class="form-control form-control-clean js-work-item-qty" min="1" step="1" value="1"></div>'
                    +     '<div class="col-5"><label class="form-label small mb-0">Harga</label>'
                    +       '<input type="number" class="form-control form-control-clean js-work-item-price" min="0" step="1" value="0"></div>'
                    +     '<div class="col-3"><button type="button" class="btn btn-outline-primary w-100 js-work-add-item" title="Tambah barang"><i class="bi bi-plus-lg"></i></button></div>'
                    +   '</div>'
                    +   '<p class="form-hint-sm mb-2">Stok tidak dipotong di tahap ini.</p>'
                    +   '<div class="js-work-item-list"></div>'
                    + '</div>'
                    + '<div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">'
                    +   '<span class="small text-muted">Total</span>'
                    +   '<strong class="js-work-total">Rp 0</strong>'
                    + '</div>'
                    + '<div class="js-work-hidden"></div>'
                );

                $root.find('.js-work-service-select').html(catalogOptions(settings.servicesCatalog, '-- Pilih jasa --'));
                $root.find('.js-work-item-select').html(catalogOptions(settings.itemsCatalog, '-- Pilih barang --'));
            }

            function renderHidden() {
                const $hidden = $root.find('.js-work-hidden').empty();
                serviceLines.forEach(function (line, i) {
                    $hidden.append('<input type="hidden" name="services[' + i + '][workshop_service_id]" value="' + escapeHtml(line.workshop_service_id) + '">');
                    $hidden.append('<input type="hidden" name="services[' + i + '][quantity]" value="' + escapeHtml(line.quantity) + '">');
                    $hidden.append('<input type="hidden" name="services[' + i + '][unit_price]" value="' + escapeHtml(line.unit_price) + '">');
                });
                itemLines.forEach(function (line, i) {
                    $hidden.append('<input type="hidden" name="items[' + i + '][item_id]" value="' + escapeHtml(line.item_id) + '">');
                    $hidden.append('<input type="hidden" name="items[' + i + '][quantity]" value="' + escapeHtml(line.quantity) + '">');
                    $hidden.append('<input type="hidden" name="items[' + i + '][unit_price]" value="' + escapeHtml(line.unit_price) + '">');
                });
            }

            function renderList($target, lines, kind) {
                $target.empty();
                if (!lines.length) {
                    $target.append('<div class="text-muted small py-1">Belum ada ' + (kind === 'item' ? 'barang' : 'jasa') + '.</div>');
                    return;
                }

                lines.forEach(function (line, index) {
                    $target.append(
                        '<div class="d-flex align-items-start gap-2 py-2 border-bottom">'
                        +   '<div class="flex-grow-1 min-width-0">'
                        +     '<div class="fw-medium small">' + escapeHtml(line.name) + '</div>'
                        +     '<div class="text-muted" style="font-size:0.72rem">' + escapeHtml(line.code || '') + '</div>'
                        +     '<div class="row g-1 mt-1">'
                        +       '<div class="col-5"><input type="number" class="form-control form-control-sm form-control-clean js-work-line-qty" data-kind="' + kind + '" data-index="' + index + '" min="1" step="1" value="' + escapeHtml(line.quantity) + '"></div>'
                        +       '<div class="col-7"><input type="number" class="form-control form-control-sm form-control-clean js-work-line-price" data-kind="' + kind + '" data-index="' + index + '" min="0" step="1" value="' + escapeHtml(line.unit_price) + '"></div>'
                        +     '</div>'
                        +   '</div>'
                        +   '<div class="text-end">'
                        +     '<div class="small fw-semibold text-nowrap">' + formatRp(lineSubtotal(line)) + '</div>'
                        +     '<button type="button" class="btn btn-sm btn-outline-danger mt-1 js-work-remove" data-kind="' + kind + '" data-index="' + index + '" title="Hapus"><i class="bi bi-trash"></i></button>'
                        +   '</div>'
                        + '</div>'
                    );
                });
            }

            function render() {
                renderList($root.find('.js-work-service-list'), serviceLines, 'service');
                renderList($root.find('.js-work-item-list'), itemLines, 'item');
                const total = serviceLines.concat(itemLines).reduce(function (sum, line) {
                    return sum + lineSubtotal(line);
                }, 0);
                $root.find('.js-work-total').text(formatRp(total));
                renderHidden();
            }

            function seedInitial() {
                cloneLines(settings.initialServices).forEach(function (line) {
                    if (!line.workshop_service_id && !line.name) {
                        return;
                    }
                    serviceLines.push({
                        workshop_service_id: line.workshop_service_id,
                        code: line.code || '',
                        name: line.name || '',
                        quantity: parseInt(line.quantity, 10) || 1,
                        unit_price: parseFloat(line.unit_price) || 0,
                    });
                });
                cloneLines(settings.initialItems).forEach(function (line) {
                    if (!line.item_id) {
                        return;
                    }
                    itemLines.push({
                        item_id: line.item_id,
                        code: line.code || '',
                        name: line.name || '',
                        quantity: parseInt(line.quantity, 10) || 1,
                        unit_price: parseFloat(line.unit_price) || 0,
                    });
                });
            }

            function addService() {
                const $select = $root.find('.js-work-service-select');
                const service = findService($select.val());
                const qty = parseInt($root.find('.js-work-service-qty').val(), 10);
                let price = parseFloat($root.find('.js-work-service-price').val());

                if (!service) {
                    Swal.fire({ icon: 'warning', title: 'Pilih jasa terlebih dahulu.' });
                    return;
                }
                if (!qty || qty < 1) {
                    Swal.fire({ icon: 'warning', title: 'Qty harus lebih dari 0.' });
                    return;
                }
                if (isNaN(price) || price < 0) {
                    price = servicePrice(service);
                }

                const existing = serviceLines.find(function (l) {
                    return String(l.workshop_service_id) === String(service.id);
                });
                if (existing) {
                    existing.quantity += qty;
                    existing.unit_price = price;
                } else {
                    serviceLines.push({
                        workshop_service_id: service.id,
                        code: service.code || '',
                        name: service.name,
                        quantity: qty,
                        unit_price: price,
                    });
                }

                $select.val('');
                $root.find('.js-work-service-qty').val('1');
                $root.find('.js-work-service-price').val('0');
                render();
            }

            function addItem() {
                const $select = $root.find('.js-work-item-select');
                const item = findItem($select.val());
                const qty = parseInt($root.find('.js-work-item-qty').val(), 10);
                let price = parseFloat($root.find('.js-work-item-price').val());

                if (!item) {
                    Swal.fire({ icon: 'warning', title: 'Pilih barang terlebih dahulu.' });
                    return;
                }
                if (!qty || qty < 1) {
                    Swal.fire({ icon: 'warning', title: 'Qty harus lebih dari 0.' });
                    return;
                }
                if (isNaN(price) || price < 0) {
                    price = itemPrice(item);
                }

                const stock = parseInt(item.stock, 10);
                const existing = itemLines.find(function (l) {
                    return String(l.item_id) === String(item.id);
                });
                const nextQty = (existing ? existing.quantity : 0) + qty;
                if (!isNaN(stock) && nextQty > stock) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stok tidak cukup',
                        text: item.name + ' — tersedia: ' + stock.toLocaleString('id-ID') + '. Stok baru dipotong saat Nota Bayar.',
                    });
                    return;
                }

                if (existing) {
                    existing.quantity = nextQty;
                    existing.unit_price = price;
                } else {
                    itemLines.push({
                        item_id: item.id,
                        code: item.code || '',
                        name: item.name,
                        quantity: qty,
                        unit_price: price,
                    });
                }

                $select.val('');
                $root.find('.js-work-item-qty').val('1');
                $root.find('.js-work-item-price').val('0');
                render();
            }

            function cartFor(kind) {
                return kind === 'item' ? itemLines : serviceLines;
            }

            buildShell();
            seedInitial();
            render();

            $root.on('change', '.js-work-service-select', function () {
                const service = findService($(this).val());
                $root.find('.js-work-service-price').val(service ? servicePrice(service) : 0);
            });

            $root.on('change', '.js-work-item-select', function () {
                const item = findItem($(this).val());
                $root.find('.js-work-item-price').val(item ? itemPrice(item) : 0);
            });

            $root.on('click', '.js-work-add-service', addService);
            $root.on('click', '.js-work-add-item', addItem);

            $root.on('click', '.js-work-remove', function () {
                const kind = $(this).data('kind');
                const index = parseInt($(this).data('index'), 10);
                cartFor(kind).splice(index, 1);
                render();
            });

            $root.on('change', '.js-work-line-qty', function () {
                const kind = $(this).data('kind');
                const index = parseInt($(this).data('index'), 10);
                const line = cartFor(kind)[index];
                if (!line) {
                    return;
                }
                let qty = parseInt($(this).val(), 10);
                if (!qty || qty < 1) {
                    qty = 1;
                }
                if (kind === 'item') {
                    const item = findItem(line.item_id);
                    const stock = item ? parseInt(item.stock, 10) : NaN;
                    if (!isNaN(stock) && qty > stock) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Stok tidak cukup',
                            text: line.name + ' — tersedia: ' + stock.toLocaleString('id-ID'),
                        });
                        qty = Math.max(1, stock);
                    }
                }
                line.quantity = qty;
                render();
            });

            $root.on('change', '.js-work-line-price', function () {
                const kind = $(this).data('kind');
                const index = parseInt($(this).data('index'), 10);
                const line = cartFor(kind)[index];
                if (!line) {
                    return;
                }
                let price = parseFloat($(this).val());
                if (isNaN(price) || price < 0) {
                    price = 0;
                }
                line.unit_price = price;
                render();
            });

            return {
                render: render,
                getItems: function () { return cloneLines(itemLines); },
                getServices: function () { return cloneLines(serviceLines); },
            };
        },
    };
})(jQuery);
