$(function () {
    'use strict';

    var FORM_SELECTOR = '.js-catalog-add-to-cart';

    function getToastContainer() {
        var $container = $('.catalog-toast-container');

        if (!$container.length) {
            $container = $('<div>', {
                class: 'catalog-toast-container'
            });

            $('body').append($container);
        }

        return $container;
    }

    function showToast(message, type) {
        type = type || 'success';

        var $container = getToastContainer();

        var $toast = $('<div>', {
            class: 'catalog-toast catalog-toast--' + type,
            text: message
        });

        $container.append($toast);

        setTimeout(function () {
            $toast.addClass('catalog-toast--visible');
        }, 10);

        setTimeout(function () {
            $toast.removeClass('catalog-toast--visible');

            setTimeout(function () {
                $toast.remove();
            }, 300);
        }, 3500);
    }

    function setButtonLoading($button, isLoading) {
        if (!$button || !$button.length) {
            return;
        }

        if (isLoading) {
            $button.data('original-text', $button.text());
            $button.prop('disabled', true);
            $button.text('Додаємо...');
            return;
        }

        $button.prop('disabled', false);

        var originalText = $button.data('original-text');

        if (originalText) {
            $button.text(originalText);
            $button.removeData('original-text');
        }
    }

    function updateCartCounters(data) {
        if (!data || typeof data.itemsCount === 'undefined') {
            return;
        }

        $('.js-cart-count, [data-cart-count]').text(data.itemsCount);
    }

    $(document).on('submit', FORM_SELECTOR, function (event) {
        event.preventDefault();

        var $form = $(this);
        var $button = $form.find('[type="submit"]').first();

        setButtonLoading($button, true);

        $.ajax({
            url: $form.attr('action'),
            type: $form.attr('method') || 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function (data) {
                if (!data || !data.success) {
                    showToast(
                        data && data.message ? data.message : 'Не вдалося додати товар до кошика.',
                        'error'
                    );

                    return;
                }

                showToast(data.message || 'Товар додано до кошика.', 'success');
                updateCartCounters(data);

                $(document).trigger('catalog:cart-updated', [data]);
            },
            error: function (xhr) {
                var message = 'Не вдалося додати товар до кошика.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                showToast(message, 'error');
            },
            complete: function () {
                setButtonLoading($button, false);
            }
        });
    });

    $(document).on('click', '.js-category-collapse-toggle', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $item = $button.closest('.catalog-category-menu__item');
        var isOpen = $item.hasClass('is-open');

        $item.toggleClass('is-open', !isOpen);
        $button.attr('aria-expanded', !isOpen ? 'true' : 'false');
    });

    $(document).on('click', function (event) {
        var $target = $(event.target);

        if ($target.closest('.catalog-category-menu').length) {
            return;
        }

        $('.catalog-category-menu__item.is-open')
            .removeClass('is-open')
            .find('> .js-category-collapse-toggle')
            .attr('aria-expanded', 'false');
    });
});