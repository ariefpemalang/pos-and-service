(function ($) {
    'use strict';

    function parseMoney(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (typeof value === 'number' && isFinite(value)) {
            return Math.round(value);
        }

        var digits = String(value).replace(/\D/g, '');
        if (digits === '') {
            return null;
        }

        var amount = parseInt(digits, 10);
        return isFinite(amount) ? amount : null;
    }

    function formatMoney(value) {
        var amount = parseMoney(value);
        if (amount === null) {
            return '';
        }

        return amount.toLocaleString('id-ID');
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
