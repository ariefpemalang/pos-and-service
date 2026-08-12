(function ($) {
    'use strict';

    function parseMoney(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (typeof value === 'number' && isFinite(value)) {
            return value;
        }

        var raw = String(value).replace(/[^\d.,]/g, '');
        if (!raw || raw === '.' || raw === ',') {
            return null;
        }

        var hasComma = raw.indexOf(',') !== -1;
        var hasDot = raw.indexOf('.') !== -1;

        if (hasComma && hasDot) {
            if (raw.lastIndexOf(',') > raw.lastIndexOf('.')) {
                raw = raw.replace(/\./g, '').replace(',', '.');
            } else {
                raw = raw.replace(/,/g, '');
            }
        } else if (hasComma) {
            raw = normalizeSingle(raw, ',');
        } else if (hasDot) {
            raw = normalizeSingle(raw, '.');
        }

        var amount = parseFloat(raw);
        return isFinite(amount) ? amount : null;
    }

    function normalizeSingle(raw, separator) {
        var parts = raw.split(separator);
        if (parts.length > 2) {
            return raw.split(separator).join('');
        }

        if (separator === '.' && /^\d+\.\d{3}$/.test(raw)) {
            return raw.replace(/\./g, '');
        }

        if (separator === ',' && /^\d+,\d{3}$/.test(raw)) {
            return raw.replace(/,/g, '');
        }

        return raw.replace(separator, '.');
    }

    function formatMoney(value) {
        var amount = parseMoney(value);
        if (amount === null) {
            return '';
        }

        return Math.round(amount).toLocaleString('id-ID');
    }

    function restoreCaret(el, digitsBefore) {
        var formatted = el.value;
        var pos = 0;
        var seen = 0;

        while (pos < formatted.length && seen < digitsBefore) {
            if (/\d/.test(formatted.charAt(pos))) {
                seen += 1;
            }
            pos += 1;
        }

        el.setSelectionRange(pos, pos);
    }

    function bind($input) {
        if ($input.data('atha-money-bound')) {
            return;
        }

        $input.data('atha-money-bound', true);

        if ($input.val()) {
            $input.val(formatMoney($input.val()));
        }

        $input.on('input', function () {
            var el = this;
            var digitsBefore = String(el.value.slice(0, el.selectionStart || 0)).replace(/\D/g, '').length;
            var formatted = formatMoney(el.value);
            el.value = formatted;
            restoreCaret(el, digitsBefore);
            $(el).trigger('atha:money-changed');
        });

        $input.on('blur', function () {
            if (this.value) {
                this.value = formatMoney(this.value);
            }
        });

        $input.closest('form').on('submit', function () {
            var amount = parseMoney($input.val());
            $input.val(amount === null ? '' : String(amount));
        });
    }

    window.AthaMoneyInput = {
        parse: parseMoney,
        format: formatMoney,
        bind: function (selector) {
            $(selector).each(function () {
                bind($(this));
            });
        },
    };

    $(function () {
        window.AthaMoneyInput.bind('[data-money-input]');
    });
})(jQuery);
