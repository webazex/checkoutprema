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
        private readonly array $cart,
        private readonly string $hash,
        private readonly CheckoutForm $model
    ) {
        $this->field = new CheckoutFieldViewHelper($model);
    }

    public function field(): CheckoutFieldViewHelper
    {
        return $this->field;
    }

    public function t(string $message, array $params = []): string
    {
        return Yii::t('frontend', $message, $params);
    }

    public function hasItems(): bool
    {
        return !empty($this->cart['items']);
    }

    public function submitUrl(): string
    {
        return Url::to(['checkout/submit', 'hash' => $this->hash]);
    }

    public function clearUrl(): string
    {
        return Url::to(['checkout/clear', 'hash' => $this->hash]);
    }

    public function catalogUrl(): string
    {
        return Url::to(['/catalog']);
    }

    public function csrfInput(): string
    {
        return Html::hiddenInput(
            Yii::$app->request->csrfParam,
            Yii::$app->request->getCsrfToken()
        );
    }

    public function flash(string $key): string
    {
        if (!Yii::$app->session->hasFlash($key)) {
            return '';
        }

        return Html::tag(
            'div',
            Html::encode(Yii::$app->session->getFlash($key)),
            ['class' => 'checkout-alert checkout-alert--' . $key]
        );
    }

    public function money(mixed $amount, ?string $currency = null): string
    {
        $value = is_numeric($amount) ? (float)$amount : 0.0;

        $formatted = fmod($value, 1.0) === 0.0
            ? number_format($value, 0, '.', ' ')
            : number_format($value, 2, '.', ' ');

        $currentCurrency = $currency ?: $this->currency();

        return $formatted . ' ' . ($currentCurrency === 'UAH'
                ? $this->t('UAH')
                : $currentCurrency);
    }

    public function total(): string
    {
        return $this->money($this->cart['totalAmount'] ?? 0, $this->currency());
    }

    public function renderItems(): string
    {
        $html = '';

        foreach ($this->items() as $item) {
            $html .= (new CheckoutCartItemViewHelper(
                item: $item,
                hash: $this->hash,
                csrfParam: Yii::$app->request->csrfParam,
                csrfToken: Yii::$app->request->getCsrfToken(),
                defaultCurrency: $this->currency()
            ))->render();
        }

        return $html;
    }

    public function clearCartForm(): string
    {
        return Html::beginForm(
                $this->clearUrl(),
                'post',
                ['class' => 'checkout-clear-form']
            )
            . $this->csrfInput()
            . Html::button($this->t('Clear cart'), [
                'type' => 'submit',
                'class' => 'checkout-clear-btn',
                'data-confirm-modal' => true,
                'data-confirm-title' => $this->t('Clear cart'),
                'data-confirm-message' => $this->t('Are you sure you want to clear the cart?'),
                'data-confirm-confirm' => $this->t('Clear'),
                'data-confirm-cancel' => $this->t('Cancel'),
            ])
            . Html::endForm();
    }

    public function confirmModal(): string
    {
        return Html::tag(
            'div',
            Html::tag('div', '', [
                'class' => 'checkout-confirm-modal__backdrop',
                'data-confirm-close' => true,
            ])
            . Html::tag(
                'div',
                Html::button('×', [
                    'type' => 'button',
                    'class' => 'checkout-confirm-modal__close',
                    'data-confirm-close' => true,
                    'aria-label' => $this->t('Close'),
                ])
                . Html::tag('h2', '', [
                    'class' => 'checkout-confirm-modal__title',
                    'id' => 'checkoutConfirmTitle',
                ])
                . Html::tag('p', '', ['class' => 'checkout-confirm-modal__message'])
                . Html::tag(
                    'div',
                    Html::button($this->t('Cancel'), [
                        'type' => 'button',
                        'class' => 'checkout-confirm-modal__btn checkout-confirm-modal__btn--ghost',
                        'data-confirm-cancel' => true,
                    ])
                    . Html::button($this->t('Confirm'), [
                        'type' => 'button',
                        'class' => 'checkout-confirm-modal__btn checkout-confirm-modal__btn--danger',
                        'data-confirm-submit' => true,
                    ]),
                    ['class' => 'checkout-confirm-modal__actions']
                ),
                [
                    'class' => 'checkout-confirm-modal__dialog',
                    'role' => 'dialog',
                    'aria-modal' => 'true',
                    'aria-labelledby' => 'checkoutConfirmTitle',
                ]
            ),
            [
                'class' => 'checkout-confirm-modal',
                'id' => 'checkoutConfirmModal',
                'hidden' => true,
            ]
        );
    }

    private function items(): array
    {
        return is_array($this->cart['items'] ?? null) ? $this->cart['items'] : [];
    }

    private function currency(): string
    {
        return (string)($this->cart['currency'] ?? 'UAH');
    }
}