(function ($) {
    'use strict';
    $(document).ready(function () {
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

                $(document).on('click', '[data-confirm-modal]', function (event) {
                    event.preventDefault();
                    self.open($(this));
                });

                this.$submitBtn.on('click', function () {
                    self.submitPendingForm();
                });

                this.$cancelBtn.on('click', function () {
                    self.close();
                });

                this.$modal.on('click', '[data-confirm-close]', function () {
                    self.close();
                });

                $(document).on('keydown', function (event) {
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

                form.submit();
            }
        };

        $(function () {
            CheckoutConfirmModal.init();
        });
    });
})(jQuery);