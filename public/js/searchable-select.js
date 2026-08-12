/**
 * Dropdown dengan pencarian (Select2) — barang, pelanggan, teknisi, dll.
 */
(function ($) {
    'use strict';

    const LANG = {
        noResults: function () { return 'Tidak ditemukan'; },
        searching: function () { return 'Mencari...'; },
        inputTooShort: function () { return 'Ketik untuk mencari'; },
        loadingMore: function () { return 'Memuat...'; },
    };

    function placeholderFor($el) {
        const $empty = $el.find('option[value=""]').first();
        return $empty.length ? $empty.text() : 'Pilih...';
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatRp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    function optionData($el, key) {
        const fromAttr = $el.attr('data-' + key);
        if (fromAttr != null && fromAttr !== '') {
            return String(fromAttr);
        }
        const camel = key.replace(/-([a-z])/g, function (_, c) { return c.toUpperCase(); });
        const raw = $el.data(camel);
        if (raw !== undefined && raw !== null && raw !== '') {
            return String(raw);
        }
        return '';
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function phoneCore(digits) {
        if (!digits) {
            return '';
        }
        if (digits.indexOf('62') === 0 && digits.length > 4) {
            return digits.slice(2);
        }
        if (digits.indexOf('0') === 0 && digits.length > 3) {
            return digits.slice(1);
        }
        return digits;
    }

    function phonesMatch(phone, term) {
        const termDigits = digitsOnly(term);
        if (termDigits.length < 3) {
            return false;
        }

        const phoneDigits = digitsOnly(phone);
        if (!phoneDigits) {
            return false;
        }

        if (phoneDigits.indexOf(termDigits) > -1) {
            return true;
        }

        const haystack = phoneCore(phoneDigits);
        const needle = phoneCore(termDigits);

        return needle.length >= 3 && haystack.indexOf(needle) > -1;
    }

    function customerMatcher(params, data) {
        const term = $.trim(params.term || '');
        if (term === '') {
            return data;
        }

        if (data.children && data.children.length > 0) {
            const match = $.extend(true, {}, data);
            match.children = [];
            data.children.forEach(function (child) {
                const matched = customerMatcher(params, child);
                if (matched) {
                    match.children.push(matched);
                }
            });
            return match.children.length ? match : null;
        }

        if (data.id == null || data.id === '') {
            return null;
        }

        const $el = data.element ? $(data.element) : $();
        const id = String(data.id);
        const name = optionData($el, 'name');
        const code = optionData($el, 'code');
        const phone = optionData($el, 'phone');
        const haystack = [data.text, name, code, phone].join(' ').toLowerCase();

        if (haystack.indexOf(term.toLowerCase()) > -1) {
            return data;
        }

        if (id !== '__new__' && id !== '__umum__' && phonesMatch(phone || data.text, term)) {
            return data;
        }

        return null;
    }

    function customerCardIcon(option, extraClass) {
        const extra = extraClass ? ' ' + extraClass : '';

        return '<i class="bi bi-person-vcard atha-s2-opt-card' + extra + '"></i>';
    }

    function formatCustomerOption(option) {
        if (!option.id) {
            return option.text;
        }

        const val = String(option.id);
        const text = option.text || '';
        const $el = $(option.element);

        if (val === '__new__') {
            return $('<span class="atha-s2-opt atha-s2-opt--action"><i class="bi bi-person-plus"></i> Pelanggan baru</span>');
        }

        if (val === '__umum__') {
            return $('<span class="atha-s2-opt atha-s2-opt--walkin"><i class="bi bi-shop"></i> ' + escapeHtml(text) + '</span>');
        }

        const sep = text.indexOf(' — ');
        const code = optionData($el, 'code') || (sep > -1 ? text.slice(0, sep) : '');
        const name = optionData($el, 'name') || (sep > -1 ? text.slice(sep + 3) : text);
        const phone = optionData($el, 'phone');

        let html = '<span class="atha-s2-opt">' + customerCardIcon(option);
        if (code) {
            html += '<span class="atha-s2-opt-code">' + escapeHtml(code) + '</span><span class="atha-s2-opt-sep">—</span>';
        }
        html += '<span class="atha-s2-opt-name">' + escapeHtml(name) + '</span>';
        if (phone) {
            html += '<span class="atha-s2-opt-phone"><i class="bi bi-whatsapp"></i> ' + escapeHtml(phone) + '</span>';
        }
        html += '</span>';

        return $(html);
    }

    function formatCustomerSelection(option) {
        if (!option.id) {
            return option.text;
        }

        const val = String(option.id);
        const text = option.text || '';
        const $el = $(option.element);

        if (val === '__new__' || val === '__umum__') {
            return text;
        }

        const sep = text.indexOf(' — ');
        const name = optionData($el, 'name') || (sep > -1 ? text.slice(sep + 3) : text);
        const phone = optionData($el, 'phone');

        let html = '<span class="atha-s2-opt atha-s2-opt--selection">' + customerCardIcon(option) +
            '<span class="atha-s2-opt-name">' + escapeHtml(name) + '</span>';
        if (phone) {
            html += '<span class="atha-s2-opt-phone">' + escapeHtml(phone) + '</span>';
        }
        html += '</span>';

        return $(html);
    }

    function itemThumbHtml(photo, name) {
        if (photo) {
            return '<img src="' + escapeHtml(photo) + '" alt="' + escapeHtml(name) + '" class="atha-s2-item-thumb">';
        }

        return '<span class="atha-s2-item-thumb atha-s2-item-thumb--empty"><i class="bi bi-box-seam"></i></span>';
    }

    function formatItemOption(option) {
        if (!option.id) {
            return option.text;
        }

        const $el = $(option.element);
        const code = optionData($el, 'code') || '';
        const name = optionData($el, 'name') || option.text || '';
        const stock = optionData($el, 'stock') || '0';
        const price = optionData($el, 'price') || '0';
        const purchasePrice = optionData($el, 'purchase-price') || '0';
        const category = optionData($el, 'category');
        const unit = optionData($el, 'unit');
        const photo = optionData($el, 'photo');

        const metaParts = [];
        if (category) {
            metaParts.push(escapeHtml(category));
        }
        if (unit) {
            metaParts.push(escapeHtml(unit));
        }
        metaParts.push('Stok: ' + Number(stock).toLocaleString('id-ID'));

        let priceLine = 'Jual: ' + formatRp(price);
        if (Number(purchasePrice) > 0) {
            priceLine += ' · Beli: ' + formatRp(purchasePrice);
        }

        return $(
            '<span class="atha-s2-item">' +
                itemThumbHtml(photo, name) +
                '<span class="atha-s2-item-body">' +
                    '<span class="atha-s2-item-code">' + escapeHtml(code) + '</span>' +
                    '<span class="atha-s2-item-name">' + escapeHtml(name) + '</span>' +
                    '<span class="atha-s2-item-meta">' + metaParts.join(' · ') + '</span>' +
                    '<span class="atha-s2-item-price">' + priceLine + '</span>' +
                '</span>' +
            '</span>'
        );
    }

    function formatItemSelection(option) {
        if (!option.id) {
            return option.text;
        }

        const $el = $(option.element);
        const code = optionData($el, 'code') || '';
        const name = optionData($el, 'name') || option.text || '';
        const stock = optionData($el, 'stock') || '0';
        const photo = optionData($el, 'photo');

        return $(
            '<span class="atha-s2-item atha-s2-item--selection">' +
                itemThumbHtml(photo, name) +
                '<span class="atha-s2-item-body">' +
                    '<span class="atha-s2-item-name">' + escapeHtml(name) + '</span>' +
                    '<span class="atha-s2-item-meta">' + escapeHtml(code) + ' · Stok: ' + Number(stock).toLocaleString('id-ID') + '</span>' +
                '</span>' +
            '</span>'
        );
    }

    window.AthaSearchableSelect = {
        init: function (selector, options) {
            if (!$.fn.select2) {
                return;
            }

            const defaults = {
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                minimumResultsForSearch: 0,
                language: LANG,
                containerCssClass: 'atha-select2-wrap',
                selectionCssClass: 'atha-select2-selection',
                dropdownCssClass: 'atha-select2-dropdown',
            };

            $(selector).each(function () {
                const $el = $(this);
                if ($el.data('select2')) {
                    return;
                }

                const opts = $.extend({}, defaults, options || {}, {
                    placeholder: placeholderFor($el),
                });

                if ($el.hasClass('atha-select2-customer')) {
                    opts.templateResult = formatCustomerOption;
                    opts.templateSelection = formatCustomerSelection;
                    opts.matcher = customerMatcher;
                    opts.dropdownAutoWidth = false;
                }

                if ($el.hasClass('atha-select2-item')) {
                    opts.templateResult = formatItemOption;
                    opts.templateSelection = formatItemSelection;
                    opts.dropdownCssClass = (opts.dropdownCssClass || '') + ' atha-select2-dropdown--item';
                    opts.selectionCssClass = (opts.selectionCssClass || '') + ' atha-select2-selection--item';
                    opts.dropdownAutoWidth = false;
                }

                const parent = $el.data('searchable-parent');
                if (parent) {
                    opts.dropdownParent = $(parent);
                }

                $el.select2(opts);
            });
        },

        clear: function (selector) {
            $(selector).val(null).trigger('change');
        },

        destroy: function (selector) {
            $(selector).each(function () {
                const $el = $(this);
                if ($el.data('select2')) {
                    $el.select2('destroy');
                }
            });
        },
    };

    function boot() {
        AthaSearchableSelect.init('.atha-searchable-select');
    }

    if (document.readyState === 'loading') {
        $(boot);
    } else {
        boot();
    }
})(jQuery);
