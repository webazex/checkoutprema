<?php

declare(strict_types=1);

namespace frontend\helpers\checkout;

use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

final class CheckoutCartItemViewHelper
{
    public function __construct(
        private readonly array $item,
        private readonly string $hash,
        private readonly string $csrfParam,
        private readonly string $csrfToken,
        private readonly string $defaultCurrency
    ) {
    }

    public function render(): string
    {
        return Html::tag(
            'article',
            $this->renderThumb()
            . $this->renderInfo()
            . $this->renderSide(),
            [
                'class' => 'products__product-item checkout-product',
                'data-cart-item-id' => $this->id(),
                'data-available-quantity' => $this->maxQuantity(),
            ]
        );
    }

    private function renderThumb(): string
    {
        $image = $this->image();

        if ($image !== '') {
            $content = Html::img($image, [
                'alt' => '',
                'loading' => 'lazy',
            ]);
        } else {
            $content = Html::tag(
                'span',
                Html::encode(mb_substr($this->title(), 0, 1, 'UTF-8') ?: 'P'),
                ['class' => 'checkout-product__thumb-text']
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

    private function renderInfo(): string
    {
        return Html::tag(
            'div',
            Html::tag(
                'div',
                Html::encode($this->title()),
                ['class' => 'info-box__product-name checkout-product__name']
            )
            . $this->renderSku()
            . $this->renderMeta(),
            ['class' => 'product-item__info-box checkout-product__info']
        );
    }

    private function renderSku(): string
    {
        $sku = $this->sku();

        if ($sku === '') {
            return '';
        }

        return Html::tag(
            'div',
            Html::tag(
                'div',
                Html::tag('span', Html::encode(Yii::t('frontend', 'SKU')) . ':', ['class' => 'prop-row__key'])
                . Html::tag('span', Html::encode($sku), ['class' => 'prop-row__val']),
                ['class' => 'properties__prop-row']
            ),
            ['class' => 'info-box__properties checkout-product__props']
        );
    }

    private function renderMeta(): string
    {
        $stockNoteOptions = ['class' => 'checkout-stock-note'];

        if ($this->maxQuantity() <= 0) {
            $stockNoteOptions['hidden'] = true;
        }

        return Html::tag(
            'div',
            Html::tag('span', Html::encode($this->money($this->price(), $this->currency())))
            . $this->renderQuantityControl()
            . Html::tag(
                'div',
                Html::encode(Yii::t('frontend', 'Available:'))
                . ' '
                . Html::tag(
                    'span',
                    Html::encode((string)$this->maxQuantity()),
                    ['class' => 'checkout-stock-note__count']
                ),
                $stockNoteOptions
            ),
            ['class' => 'checkout-product__meta']
        );
    }

    private function renderQuantityControl(): string
    {
        return Html::tag(
            'div',
            $this->renderQuantityForm('minus')
            . Html::tag(
                'span',
                Html::encode((string)$this->quantity()),
                ['class' => 'checkout-qty__value']
            )
            . $this->renderQuantityForm('plus'),
            [
                'class' => 'checkout-qty',
                'aria-label' => Yii::t('frontend', 'Quantity'),
            ]
        );
    }

    private function renderQuantityForm(string $type): string
    {
        $isPlus = $type === 'plus';
        $quantity = $isPlus ? $this->quantity() + 1 : max(1, $this->quantity() - 1);
        $canSubmit = $isPlus ? $this->canIncrease() : $this->canDecrease();

        $buttonOptions = [
            'class' => 'checkout-qty__btn',
            'type' => 'submit',
        ];

        if (!$canSubmit) {
            $buttonOptions['disabled'] = true;
        }

        if ($isPlus && !$canSubmit) {
            $buttonOptions['title'] = Yii::t(
                'frontend',
                'Only {count} item(s) available.',
                ['count' => $this->maxQuantity()]
            );
        }

        return Html::beginForm(
                Url::to(['checkout/update-item', 'hash' => $this->hash]),
                'post',
                ['class' => 'checkout-qty__form checkout-qty__form--' . $type]
            )
            . $this->csrfInput()
            . Html::hiddenInput('itemId', (string)$this->id())
            . Html::hiddenInput('quantity', (string)$quantity)
            . Html::button($isPlus ? '+' : '−', $buttonOptions)
            . Html::endForm();
    }

    private function renderSide(): string
    {
        return Html::tag(
            'div',
            $this->renderRemoveForm()
            . Html::tag(
                'div',
                Html::encode($this->money($this->subtotal(), $this->currency())),
                ['class' => 'checkout-product__subtotal']
            ),
            ['class' => 'checkout-product__side']
        );
    }

    private function renderRemoveForm(): string
    {
        return Html::beginForm(
                Url::to(['checkout/remove-item', 'hash' => $this->hash]),
                'post',
                ['class' => 'checkout-remove-form']
            )
            . $this->csrfInput()
            . Html::hiddenInput('itemId', (string)$this->id())
            . Html::button('×', [
                'type' => 'submit',
                'class' => 'product-item__remove-icon checkout-remove-btn',
                'aria-label' => Yii::t('frontend', 'Remove item'),
                'data-confirm-modal' => true,
                'data-confirm-title' => Yii::t('frontend', 'Remove item'),
                'data-confirm-message' => Yii::t('frontend', 'Are you sure you want to remove this item?'),
                'data-confirm-confirm' => Yii::t('frontend', 'Remove'),
                'data-confirm-cancel' => Yii::t('frontend', 'Cancel'),
            ])
            . Html::endForm();
    }

    private function csrfInput(): string
    {
        return Html::hiddenInput($this->csrfParam, $this->csrfToken);
    }

    private function id(): int
    {
        return (int)($this->item['id'] ?? 0);
    }

    private function title(): string
    {
        return (string)($this->item['title'] ?? '');
    }

    private function sku(): string
    {
        return (string)($this->item['sku'] ?? '');
    }

    private function quantity(): int
    {
        return max((int)($this->item['quantity'] ?? 0), 1);
    }

    private function maxQuantity(): int
    {
        return max((int)($this->item['availableQuantity'] ?? 0), 0);
    }

    private function canDecrease(): bool
    {
        return $this->quantity() > 1;
    }

    private function canIncrease(): bool
    {
        return $this->maxQuantity() > 0 && $this->quantity() < $this->maxQuantity();
    }

    private function price(): mixed
    {
        return $this->item['price'] ?? 0;
    }

    private function subtotal(): mixed
    {
        return $this->item['subtotal'] ?? 0;
    }

    private function currency(): string
    {
        return (string)($this->item['currency'] ?? $this->defaultCurrency);
    }

    private function image(): string
    {
        $image = $this->item['image']
            ?? $this->item['thumbnail']
            ?? $this->item['thumbnailUrl']
            ?? '';

        return is_string($image) ? trim($image) : '';
    }

    private function money(mixed $amount, ?string $currency = null): string
    {
        $value = is_numeric($amount) ? (float)$amount : 0.0;

        $formatted = fmod($value, 1.0) === 0.0
            ? number_format($value, 0, '.', ' ')
            : number_format($value, 2, '.', ' ');

        $currentCurrency = $currency ?: $this->defaultCurrency;

        return $formatted . ' ' . ($currentCurrency === 'UAH'
                ? Yii::t('frontend', 'UAH')
                : $currentCurrency);
    }
}