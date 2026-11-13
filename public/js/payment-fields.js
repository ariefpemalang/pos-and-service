(function ($) {
    'use strict';

    function formatRp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    let currentTotal = 0;

    window.AthaPaymentFields = {
        init: function () {
            const $method = $('#payment_method');
            const $bankWrap = $('#bank-account-wrap');
            const $cashWrap = $('#cash-payment-wrap');
            const $bank = $('#bank_account_id');
            const $amountPaid = $('#amount_paid');
            const $change = $('#cash-change');

            function toggleFields() {
                const method = $method.val();
                const isTransfer = method === 'transfer';
                const isCash = method === 'cash';

                $bankWrap.toggleClass('d-none', !isTransfer);
                $bank.prop('required', isTransfer);
                if (!isTransfer) {
                    $bank.val('');
                }

                $cashWrap.toggleClass('d-none', !isCash);
                $amountPaid.prop('required', isCash);
                if (!isCash) {
                    $amountPaid.val('');
                    $change.text(formatRp(0)).removeClass('text-danger').addClass('text-success');
                }
            }

            $method.on('change', function () {
                toggleFields();
                $(document).trigger('atha:payment-fields-changed');
            });

            $amountPaid.on('input change', function () {
                $(document).trigger('atha:payment-fields-changed');
            });

            // Klik tombol "Uang Pas": isi otomatis sesuai total / sisa tagihan
            $(document).on('click', '#btn-exact-cash', function (e) {
                e.preventDefault();
                const due = Math.max(0, Math.round(currentTotal));
                $amountPaid.val(due).trigger('input').trigger('change');
            });

            toggleFields();
        },

        /**
         * @param {number} total
         * @returns {{ paid: number, change: number, sufficient: boolean }}
         */
        getCashPayment: function (total) {
            currentTotal = total;
            const paid = parseFloat($('#amount_paid').val()) || 0;
            const change = Math.max(0, paid - total);

            return {
                paid: paid,
                change: change,
                sufficient: paid >= total,
            };
        },

        updateCashChange: function (total) {
            currentTotal = total;
            const $wrap = $('#cash-payment-wrap');
            const $change = $('#cash-change');

            if ($wrap.hasClass('d-none')) {
                return;
            }

            const cash = this.getCashPayment(total);
            $change.text(formatRp(cash.change));

            if (total > 0 && cash.paid > 0 && cash.paid < total) {
                $change.removeClass('text-success').addClass('text-danger');
            } else {
                $change.removeClass('text-danger').addClass('text-success');
            }
        },
    };
})(jQuery);
