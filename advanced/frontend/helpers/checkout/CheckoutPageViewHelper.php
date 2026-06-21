<?php

declare(strict_types=1);

namespace frontend\helpers\checkout;

use frontend\models\CheckoutForm;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

final class CheckoutPageViewHelper
{
    private CheckoutFieldViewHelper $field;

    public function __construct(
        private readonly array        $cart,
        private readonly string       $hash,
        private readonly CheckoutForm $model
    )
    {
        $this->field = new CheckoutFieldViewHelper($this->model);
    }

    public function field(): CheckoutFieldViewHelper
    {
        return $this->field;
    }

    public function hasItems(): bool
    {
        return !empty($this->items());
    }

    public function items(): array
    {
        return is_array($this->cart['items'] ?? null)
            ? $this->cart['items']
            : [];
    }

    public function total(): string
    {
        return $this->formatMoney($this->totalAmount(), $this->currency());
    }

    private function formatMoney(mixed $amount, ?string $currency = null): string
    {
        $value = is_numeric($amount) ? (float)$amount : 0.0;

        $formatted = fmod($value, 1.0) === 0.0
            ? number_format($value, 0, '.', ' ')
            : number_format($value, 2, '.', ' ');

        $currentCurrency = $currency ?: $this->currency();

        return $formatted . ' ' . ($currentCurrency === 'UAH'
                ? $this->t('UAH')
                : $currentCurrency
            );
    }

    public function currency(): string
    {
        return (string)($this->cart['currency'] ?? 'UAH');
    }

    public function t(string $message, array $params = []): string
    {
        return Yii::t('frontend', $message, $params);
    }

    public function totalAmount(): mixed
    {
        return $this->cart['totalAmount'] ?? 0;
    }

    public function catalogUrl(): string
    {
        return Url::to(['/catalog']);
    }

    public function submitUrl(): string
    {
        return Url::to(['/checkout/submit', 'hash' => $this->hash]);
    }

    public function flash(string $type): string
    {
        if (!Yii::$app->session->hasFlash($type)) {
            return '';
        }

        return Html::tag(
            'div',
            Html::encode((string)Yii::$app->session->getFlash($type)),
            [
                'class' => 'checkout-alert checkout-alert--' . $type,
            ]
        );
    }

    public function clearCartForm(): string
    {
        $button = Html::button(
            Html::encode($this->t('Clear cart')),
            [
                'type' => 'submit',
                'class' => 'checkout-clear-btn',
                'data-confirm-modal' => true,
                'data-confirm-title' => $this->t('Clear cart'),
                'data-confirm-message' => $this->t('Are you sure you want to clear the cart?'),
                'data-confirm-confirm' => $this->t('Clear'),
                'data-confirm-cancel' => $this->t('Cancel'),
            ]
        );

        return Html::beginForm($this->clearUrl(), 'post', [
                'class' => 'checkout-clear-form',
            ])
            . $this->csrfInput()
            . $button
            . Html::endForm();
    }

    public function clearUrl(): string
    {
        return Url::to(['/checkout/clear', 'hash' => $this->hash]);
    }

    public function csrfInput(): string
    {
        return Html::hiddenInput(
            Yii::$app->request->csrfParam,
            Yii::$app->request->getCsrfToken()
        );
    }

    public function renderItems(): string
    {
        $html = '';

        foreach ($this->items() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $html .= $this->renderItem($item);
        }

        return $html;
    }

    private function renderItem(array $item): string
    {
        $itemId = (int)($item['id'] ?? 0);
        $itemTitle = (string)($item['title'] ?? '');
        $itemSku = (string)($item['sku'] ?? '');
        $itemQty = max((int)($item['quantity'] ?? 0), 1);
        $itemAvailableQty = (int)($item['availableQuantity'] ?? 0);
        $itemMaxQty = max($itemAvailableQty, 0);

        $canDecrease = $itemQty > 1;
        $canIncrease = $itemMaxQty > 0 && $itemQty < $itemMaxQty;

        $itemPrice = $item['price'] ?? 0;
        $itemSubtotal = $item['subtotal'] ?? 0;
        $itemCurrency = (string)($item['currency'] ?? $this->currency());
        $itemImage = $item['image'] ?? $item['thumbnail'] ?? $item['thumbnailUrl'] ?? null;

        return Html::tag(
            'article',
            $this->renderItemThumb($itemTitle, $itemImage)
            . $this->renderItemInfo(
                itemTitle: $itemTitle,
                itemSku: $itemSku,
                itemQty: $itemQty,
                itemMaxQty: $itemMaxQty,
                canDecrease: $canDecrease,
                canIncrease: $canIncrease,
                itemId: $itemId,
                itemPrice: $itemPrice,
                itemCurrency: $itemCurrency
            )
            . $this->renderItemSide($itemId, $itemSubtotal, $itemCurrency),
            [
                'class' => 'products__product-item checkout-product',
                'data-cart-item-id' => $itemId,
                'data-available-quantity' => $itemMaxQty,
            ]
        );
    }

    private function renderItemThumb(string $title, mixed $image): string
    {
        if (is_string($image) && trim($image) !== '') {
            $content = Html::img($image, [
                'alt' => '',
                'loading' => 'lazy',
            ]);
        } else {
            $content = Html::tag(
                'span',
                Html::encode(mb_substr($title, 0, 1, 'UTF-8') ?: 'P'),
                [
                    'class' => 'checkout-product__thumb-text',
                ]
            );
        }

        return Html::tag(
            'div',
            $content,
            [
                'class' => 'product-item__thumb checkout-product__thumb',
                'aria-hidden' => 'true',
            ]
        );
    }

    private function renderItemInfo(
        string $itemTitle,
        string $itemSku,
        int    $itemQty,
        int    $itemMaxQty,
        bool   $canDecrease,
        bool   $canIncrease,
        int    $itemId,
        mixed  $itemPrice,
        string $itemCurrency
    ): string
    {
        $title = Html::tag(
            'div',
            Html::encode($itemTitle),
            [
                'class' => 'info-box__product-name checkout-product__name',
            ]
        );

        $sku = $itemSku !== ''
            ? $this->renderSku($itemSku)
            : '';

        $meta = Html::tag(
            'div',
            Html::tag(
                'span',
                Html::encode($this->formatMoney($itemPrice, $itemCurrency))
            )
            . $this->renderQty(
                itemId: $itemId,
                itemQty: $itemQty,
                itemMaxQty: $itemMaxQty,
                canDecrease: $canDecrease,
                canIncrease: $canIncrease
            )
            . $this->renderStockNote($itemMaxQty),
            [
                'class' => 'checkout-product__meta',
            ]
        );

        return Html::tag(
            'div',
            $title . $sku . $meta,
            [
                'class' => 'product-item__info-box checkout-product__info',
            ]
        );
    }

    private function renderSku(string $sku): string
    {
        return Html::tag(
            'div',
            Html::tag(
                'div',
                Html::tag(
                    'span',
                    Html::encode($this->t('SKU') . ':'),
                    [
                        'class' => 'prop-row__key',
                    ]
                )
                . Html::tag(
                    'span',
                    Html::encode($sku),
                    [
                        'class' => 'prop-row__val',
                    ]
                ),
                [
                    'class' => 'properties__prop-row',
                ]
            ),
            [
                'class' => 'info-box__properties checkout-product__props',
            ]
        );
    }

    private function renderQty(
        int  $itemId,
        int  $itemQty,
        int  $itemMaxQty,
        bool $canDecrease,
        bool $canIncrease
    ): string
    {
        return Html::tag(
            'div',
            $this->qtyForm(
                itemId: $itemId,
                quantity: max(1, $itemQty - 1),
                sign: '−',
                enabled: $canDecrease,
                extraClass: 'checkout-qty__form--minus'
            )
            . Html::tag(
                'span',
                Html::encode((string)$itemQty),
                [
                    'class' => 'checkout-qty__value',
                ]
            )
            . $this->qtyForm(
                itemId: $itemId,
                quantity: $itemQty + 1,
                sign: '+',
                enabled: $canIncrease,
                extraClass: 'checkout-qty__form--plus',
                disabledTitle: $this->t('Only {count} item(s) available.', [
                    'count' => $itemMaxQty,
                ])
            ),
            [
                'class' => 'checkout-qty',
                'aria-label' => $this->t('Quantity'),
            ]
        );
    }

    private function qtyForm(
        int     $itemId,
        int     $quantity,
        string  $sign,
        bool    $enabled,
        string  $extraClass,
        ?string $disabledTitle = null
    ): string
    {
        $buttonOptions = [
            'type' => 'submit',
            'class' => 'checkout-qty__btn',
        ];

        if (!$enabled) {
            $buttonOptions['disabled'] = true;

            if ($disabledTitle !== null) {
                $buttonOptions['title'] = $disabledTitle;
            }
        }

        $button = Html::button($sign, $buttonOptions);

        return Html::beginForm($this->updateItemUrl(), 'post', [
                'class' => 'checkout-qty__form ' . $extraClass,
            ])
            . $this->csrfInput()
            . Html::hiddenInput('itemId', (string)$itemId)
            . Html::hiddenInput('quantity', (string)$quantity)
            . $button
            . Html::endForm();
    }

    public function updateItemUrl(): string
    {
        return Url::to(['/checkout/update-item', 'hash' => $this->hash]);
    }

    private function renderStockNote(int $itemMaxQty): string
    {
        return Html::tag(
            'div',
            Html::encode($this->t('Available:')) . ' '
            . Html::tag(
                'span',
                Html::encode((string)$itemMaxQty),
                [
                    'class' => 'checkout-stock-note__count',
                ]
            ),
            [
                'class' => 'checkout-stock-note',
                'hidden' => $itemMaxQty <= 0,
            ]
        );
    }

    private function renderItemSide(int $itemId, mixed $subtotal, string $currency): string
    {
        return Html::tag(
            'div',
            $this->removeItemForm($itemId)
            . Html::tag(
                'div',
                Html::encode($this->formatMoney($subtotal, $currency)),
                [
                    'class' => 'checkout-product__subtotal',
                ]
            ),
            [
                'class' => 'checkout-product__side',
            ]
        );
    }

    private function removeItemForm(int $itemId): string
    {
        $button = Html::button(
            '×',
            [
                'type' => 'submit',
                'class' => 'product-item__remove-icon checkout-remove-btn',
                'aria-label' => $this->t('Remove item'),
                'data-confirm-modal' => true,
                'data-confirm-title' => $this->t('Remove item'),
                'data-confirm-message' => $this->t('Are you sure you want to remove this item?'),
                'data-confirm-confirm' => $this->t('Remove'),
                'data-confirm-cancel' => $this->t('Cancel'),
            ]
        );

        return Html::beginForm($this->removeItemUrl(), 'post', [
                'class' => 'checkout-remove-form',
            ])
            . $this->csrfInput()
            . Html::hiddenInput('itemId', (string)$itemId)
            . $button
            . Html::endForm();
    }

    public function removeItemUrl(): string
    {
        return Url::to(['/checkout/remove-item', 'hash' => $this->hash]);
    }

    public function confirmModal(): string
    {
        $closeButton = Html::button(
            '×',
            [
                'type' => 'button',
                'class' => 'checkout-confirm-modal__close',
                'data-confirm-close' => true,
                'aria-label' => $this->t('Close'),
            ]
        );

        $cancelButton = Html::button(
            Html::encode($this->t('Cancel')),
            [
                'type' => 'button',
                'class' => 'checkout-confirm-modal__btn checkout-confirm-modal__btn--ghost',
                'data-confirm-cancel' => true,
            ]
        );

        $submitButton = Html::button(
            Html::encode($this->t('Confirm')),
            [
                'type' => 'button',
                'class' => 'checkout-confirm-modal__btn checkout-confirm-modal__btn--danger',
                'data-confirm-submit' => true,
            ]
        );

        $dialog = Html::tag(
            'div',
            $closeButton
            . Html::tag('h2', '', [
                'class' => 'checkout-confirm-modal__title',
                'id' => 'checkoutConfirmTitle',
            ])
            . Html::tag('p', '', [
                'class' => 'checkout-confirm-modal__message',
            ])
            . Html::tag(
                'div',
                $cancelButton . $submitButton,
                [
                    'class' => 'checkout-confirm-modal__actions',
                ]
            ),
            [
                'class' => 'checkout-confirm-modal__dialog',
                'role' => 'dialog',
                'aria-modal' => 'true',
                'aria-labelledby' => 'checkoutConfirmTitle',
            ]
        );

        return Html::tag(
            'div',
            Html::tag('div', '', [
                'class' => 'checkout-confirm-modal__backdrop',
                'data-confirm-close' => true,
            ])
            . $dialog,
            [
                'class' => 'checkout-confirm-modal',
                'id' => 'checkoutConfirmModal',
                'hidden' => true,
            ]
        );
    }
}