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

    function getCartItemsCount(data) {
        if (!data || typeof data.itemsCount === 'undefined') {
            return null;
        }

        var count = parseInt(data.itemsCount, 10);

        return isNaN(count) ? 0 : Math.max(0, count);
    }

    function updateCartCounters(data) {
        var count = getCartItemsCount(data);

        if (count === null) {
            return;
        }

        $('.js-cart-count, [data-cart-count]').text(count);
    }

    function updateCartLinks(data) {
        if (!data || !data.checkoutUrl) {
            return;
        }

        $('.js-cart-link, .js-cart-side-action').attr('href', data.checkoutUrl);
    }

    function updateCartSideBox(data) {
        var count = getCartItemsCount(data);

        if (count === null) {
            return;
        }

        var isActive = count > 0;
        var $box = $('#cart-side-box');
        var $action = $('.js-cart-side-action');

        if (!$box.length || !$action.length) {
            return;
        }

        var activeText = $action.data('active-text') || 'Оформити замовлення';
        var emptyText = $action.data('empty-text') || 'Кошик порожній';

        $box
            .toggleClass('cart-side-box--active', isActive)
            .toggleClass('cart-side-box--empty', !isActive)
            .toggleClass('cart-side-box--pulse', isActive);

        $action
            .toggleClass('is-disabled', !isActive)
            .attr('aria-disabled', isActive ? 'false' : 'true')
            .attr('tabindex', isActive ? '0' : '-1');

        $('.js-cart-side-text').text(isActive ? activeText : emptyText);

        if (isActive && data.checkoutUrl) {
            $action.attr('href', data.checkoutUrl);
        }

        window.setTimeout(function () {
            $box.removeClass('cart-side-box--pulse');
        }, 700);
    }

    function updateCartUi(data) {
        updateCartCounters(data);
        updateCartLinks(data);
        updateCartSideBox(data);
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
                updateCartUi(data);

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

    $(document).on('click', '.js-cart-side-action.is-disabled', function (event) {
        event.preventDefault();
    });

    $(document).on('click', '.js-category-collapse-toggle', function (event) {
        event.preventDefault();
        event.stopPropagation();

        var $button = $(this);
        var $item = $button.closest('.catalog-category-menu__item');
        var isOpen = $item.hasClass('is-open');

        $item.toggleClass('is-open', !isOpen);
        $button.attr('aria-expanded', !isOpen ? 'true' : 'false');

        if (isOpen) {
            $button.blur();
        }
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