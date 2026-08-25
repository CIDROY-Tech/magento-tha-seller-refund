define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'jquery-ui-modules/widget'
], function ($, $t, confirm) {
    'use strict';

    $.widget('acme.acmeRefundForm', {
        options: {
            calculateUrl: '',
            saveUrl: '',
            formKey: '',
            orderId: '',
            currency: 'JPY',
            selectors: {
                qtyInput: '[data-role="qty-input"]',
                lineRow: '[data-role="line-row"]',
                lineAmount: '[data-role="line-amount"]',
                grandTotal: '[data-role="grand-total"]',
                submit: '[data-role="submit"]',
                reason: '[data-role="reason"]',
                state: '[data-role="state"]',
                receiptLink: '[data-role="receipt-link"]'
            }
        },

        /** Widget bootstrap. */
        _create: function () {
            this.inFlight = false;
            this.lastGrandTotal = '0';
            this._on(this.element.find(this.options.selectors.qtyInput), {change: '_recalculate'});
            this._on(this.element.find(this.options.selectors.submit), {click: '_onSubmit'});
        },

        /** @return {Object} order_item_id => requested qty */
        _collectItems: function () {
            var items = {};
            this.element.find(this.options.selectors.qtyInput).each(function () {
                var qty = $(this).val();
                if (qty !== '' && qty !== null) {
                    items[$(this).data('orderItemId')] = qty;
                }
            });

            return items;
        },

        /** Server-side recalculation on every quantity change. */
        _recalculate: function () {
            var self = this;

            $.ajax({
                url: this.options.calculateUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: this.options.formKey,
                    order_id: this.options.orderId,
                    items: this._collectItems()
                }
            }).done(function (response) {
                if (response.ok && response.figures) {
                    self._paint(response.figures);
                }
            });
        },

        /** Repaint the per-line amounts and the grand total from the server figures. */
        _paint: function (figures) {
            var self = this;

            this.element.find(this.options.selectors.lineAmount).text('-');
            $.each(figures.lines, function (i, line) {
                self.element
                    .find(self.options.selectors.lineRow + '[data-order-item-id="' + line.order_item_id + '"]')
                    .find(self.options.selectors.lineAmount)
                    .text(self._money(line.grand_total));
            });

            this.lastGrandTotal = figures.refund.grand_total;
            this.element.find(this.options.selectors.grandTotal).text(this._money(figures.refund.grand_total));
        },

        /** Confirmation step before any financial action. */
        _onSubmit: function () {
            var self = this;

            if (this.inFlight) {
                return;
            }

            confirm({
                title: $t('Confirm refund'),
                content: $t('Refund total:') + ' ' + this._money(this.lastGrandTotal),
                actions: {
                    confirm: function () {
                        self._save();
                    }
                }
            });
        },

        /** Issue the save. The button is disabled and relabelled before the request goes out. */
        _save: function () {
            var self = this,
                button = this.element.find(this.options.selectors.submit);

            this.inFlight = true;
            button.prop('disabled', true);
            button.find('span').text($t('Submitting...'));

            $.ajax({
                url: this.options.saveUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: this.options.formKey,
                    order_id: this.options.orderId,
                    reason_code: this.element.find(this.options.selectors.reason).val(),
                    items: this._collectItems()
                }
            }).done(function (response) {
                if (!response.ok) {
                    self._fail(response.message || $t('The refund could not be saved.'));

                    return;
                }
                self._renderServerState(response);
            }).fail(function () {
                self._fail($t('The refund request failed. Please retry.'));
            });
        },

        /**
         * Render the state the server declared. The confirmed/accepted state is shown only
         * when the server reports the create succeeded; otherwise the pending-retry state is
         * shown. The client never invents a lifecycle transition.
         */
        _renderServerState: function (response) {
            var state = this.element.find(this.options.selectors.state),
                link = this.element.find(this.options.selectors.receiptLink);

            if (response.create_status === 'succeeded') {
                state.text($t('Refund submitted and accepted by the ERP.'));
            } else {
                state.text($t('Saved; ERP notification pending retry'));
            }

            if (response.redirect_url) {
                link.attr('href', response.redirect_url);
                link.prop('hidden', false);
            }
        },

        /** Only an error path re-enables the button. */
        _fail: function (message) {
            var button = this.element.find(this.options.selectors.submit);

            this.inFlight = false;
            button.prop('disabled', false);
            button.find('span').text($t('Submit Refund'));
            this.element.find(this.options.selectors.state).text(message);
        },

        /** @return {String} */
        _money: function (amount) {
            var value = parseFloat(amount || 0);

            return this.options.currency + ' ' + value.toLocaleString('en-US', {maximumFractionDigits: 0});
        }
    });

    return $.acme.acmeRefundForm;
});
