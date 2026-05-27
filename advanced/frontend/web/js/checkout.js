(function ($) {
    'use strict';

    var CheckoutConfirmModal = {
        $modal: null,
        $title: null,
        $message: null,
        $submitBtn: null,
        $cancelBtn: null,
        pendingForm: null,
        defaultSubmitText: '',
        defaultCancelText: '',

        init: function () {
            this.$modal = $('#checkoutConfirmModal');

            if (!this.$modal.length) {
                return;
            }

            this.$title = this.$modal.find('.checkout-confirm-modal__title');
            this.$message = this.$modal.find('.checkout-confirm-modal__message');
            this.$submitBtn = this.$modal.find('[data-confirm-submit]');
            this.$cancelBtn = this.$modal.find('[data-confirm-cancel]');

            this.defaultSubmitText = $.trim(this.$submitBtn.text());
            this.defaultCancelText = $.trim(this.$cancelBtn.text());

            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('click.checkoutConfirm', '[data-confirm-modal]', function (event) {
                event.preventDefault();
                self.open($(this));
            });

            this.$submitBtn.on('click.checkoutConfirm', function () {
                self.submitPendingForm();
            });

            this.$cancelBtn.on('click.checkoutConfirm', function () {
                self.close();
            });

            this.$modal.on('click.checkoutConfirm', '[data-confirm-close]', function () {
                self.close();
            });

            $(document).on('keydown.checkoutConfirm', function (event) {
                if (event.key === 'Escape' && !self.$modal.prop('hidden')) {
                    self.close();
                }
            });
        },

        open: function ($button) {
            var $form = $button.closest('form');

            if (!$form.length) {
                return;
            }

            this.pendingForm = $form.get(0);

            this.$title.text($button.data('confirmTitle') || '');
            this.$message.text($button.data('confirmMessage') || '');
            this.$submitBtn.text($button.data('confirmConfirm') || this.defaultSubmitText);
            this.$cancelBtn.text($button.data('confirmCancel') || this.defaultCancelText);

            this.$modal.prop('hidden', false);
            $('html').addClass('checkout-confirm-open');

            this.$submitBtn.trigger('focus');
        },

        close: function () {
            this.pendingForm = null;
            this.$modal.prop('hidden', true);
            $('html').removeClass('checkout-confirm-open');
        },

        submitPendingForm: function () {
            var form = this.pendingForm;

            if (!form) {
                this.close();
                return;
            }

            this.pendingForm = null;
            this.$modal.prop('hidden', true);
            $('html').removeClass('checkout-confirm-open');

            // Native submit: не триггерим jQuery submit handlers.
            form.submit();
        }
    };

    var CheckoutQty = {
        init: function () {
            if (!$('.checkout-qty__form').length) {
                return;
            }

            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('submit.checkoutQty', '.checkout-qty__form', function (event) {
                event.preventDefault();
                self.submit($(this));
            });
        },

        submit: function ($form) {
            var self = this;
            var $button = $form.find('.checkout-qty__btn');

            if ($form.data('checkoutPending')) {
                return;
            }

            if ($button.prop('disabled')) {
                return;
            }

            $form.data('checkoutPending', true);
            $button.prop('disabled', true).addClass('is-loading');

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        self.showMessage(
                            response && response.message ? response.message : 'Error',
                            true
                        );

                        $button.prop('disabled', false);
                        return;
                    }

                    self.applyCart(response.cart);
                    self.showMessage(response.message || '', false);
                })
                .fail(function (xhr) {
                    var message = 'Error';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    self.showMessage(message, true);
                    $button.prop('disabled', false);
                })
                .always(function () {
                    $form.removeData('checkoutPending');
                    $button.removeClass('is-loading');
                });
        },

        applyCart: function (cart) {
            if (!cart || !$.isArray(cart.items)) {
                return;
            }

            var currency = cart.currency || 'UAH';

            $('.checkout-total__value').text(this.formatMoney(cart.totalAmount, currency));

            for (var i = 0; i < cart.items.length; i++) {
                this.applyItem(cart.items[i], currency);
            }
        },

        applyItem: function (item, fallbackCurrency) {
            var $row = $('.checkout-product[data-cart-item-id="' + item.id + '"]');

            if (!$row.length) {
                return;
            }

            var quantity = parseInt(item.quantity || 0, 10);
            var availableQuantity = parseInt(item.availableQuantity || 0, 10);
            var currency = item.currency || fallbackCurrency || 'UAH';

            var canDecrease = quantity > 1;

            // Важно: если availableQuantity не пришёл или равен 0,
            // НЕ разрешаем бесконечно увеличивать количество.
            var canIncrease = availableQuantity > 0 && quantity < availableQuantity;

            $row.find('.checkout-qty__value').text(quantity);
            $row.find('.checkout-product__subtotal').text(this.formatMoney(item.subtotal, currency));

            $row.find('.checkout-qty__form--minus input[name="quantity"]').val(Math.max(1, quantity - 1));
            $row.find('.checkout-qty__form--plus input[name="quantity"]').val(quantity + 1);

            $row.find('.checkout-qty__form--minus .checkout-qty__btn').prop('disabled', !canDecrease);
            $row.find('.checkout-qty__form--plus .checkout-qty__btn').prop('disabled', !canIncrease);

            if (availableQuantity > 0) {
                $row.find('.checkout-stock-note__count').text(availableQuantity);
                $row.find('.checkout-stock-note').prop('hidden', false);
            } else {
                $row.find('.checkout-stock-note').prop('hidden', true);
            }
        },

        formatMoney: function (amount, currency) {
            var value = parseFloat(amount || 0);
            var rounded = Math.round(value * 100) / 100;
            var formatted = Number.isInteger(rounded)
                ? String(rounded)
                : rounded.toFixed(2);

            formatted = formatted.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

            return formatted + ' ' + (currency || 'UAH');
        },

        showMessage: function (message, isError) {
            var $box;

            if (!message) {
                return;
            }

            $box = $('.checkout-ajax-message');

            if (!$box.length) {
                $box = $('<div class="checkout-ajax-message" hidden></div>');
                $('.checkout-page').before($box);
            }

            $box
                .removeClass('checkout-ajax-message--error checkout-ajax-message--success')
                .addClass(isError ? 'checkout-ajax-message--error' : 'checkout-ajax-message--success')
                .text(message)
                .prop('hidden', false);

            window.clearTimeout($box.data('hideTimer'));

            $box.data('hideTimer', window.setTimeout(function () {
                $box.prop('hidden', true);
            }, 2500));
        }
    };

    $(function () {
        CheckoutConfirmModal.init();
        CheckoutQty.init();
    });
})(jQuery);