<?php

declare(strict_types=1);

namespace frontend\helpers\checkout;

use frontend\models\CheckoutForm;
use Yii;
use yii\helpers\Html;

final class CheckoutFieldViewHelper
{
    public function __construct(
        private readonly CheckoutForm $model
    )
    {
    }

    public function textInput(string $attribute, ?string $label = null, array $options = []): string
    {
        return $this->input('text', $attribute, $label, $options);
    }

    private function input(string $type, string $attribute, ?string $label = null, array $options = []): string
    {
        $id = (string)($options['id'] ?? 'checkout-' . str_replace('_', '-', $attribute));
        $error = $this->error($attribute);
        $hasError = $error !== '';

        $inputOptions = array_merge($options, [
            'id' => $id,
            'class' => trim((string)($options['class'] ?? '')),
            'aria-invalid' => $hasError ? 'true' : 'false',
        ]);

        if ($hasError) {
            $inputOptions['aria-describedby'] = $this->errorId($id);
        }

        $input = Html::input(
            $type,
            $attribute,
            $this->value($attribute),
            $inputOptions
        );

        return Html::tag(
            'label',
            $this->label($attribute, $label)
            . $input
            . $this->errorTag($id, $error),
            [
                'class' => $this->labelClass($attribute),
                'for' => $id,
            ]
        );
    }

    private function error(string $attribute): string
    {
        return (string)$this->model->getFirstError($attribute);
    }

    private function errorId(string $inputId): string
    {
        return $inputId . '-error';
    }

    private function value(string $attribute): string
    {
        return Html::encode((string)($this->model->{$attribute} ?? ''));
    }

    private function label(string $attribute, ?string $label = null): string
    {
        return Html::tag(
            'span',
            Html::encode($this->labelText($attribute, $label)),
            [
                'class' => 'checkout-field__label-text',
            ]
        );
    }

    private function labelText(string $attribute, ?string $label = null): string
    {
        if ($label !== null) {
            return Yii::t('frontend', $label);
        }

        return $this->model->getAttributeLabel($attribute);
    }

    private function errorTag(string $inputId, string $error): string
    {
        if ($error === '') {
            return '';
        }

        return Html::tag(
            'span',
            Html::encode($error),
            [
                'id' => $this->errorId($inputId),
                'class' => 'checkout-field__error',
            ]
        );
    }

    private function labelClass(string $attribute): string
    {
        $classes = [
            'order-form__label',
            'checkout-field',
        ];

        if ($this->model->hasErrors($attribute)) {
            $classes[] = 'checkout-field--error';
        }

        return implode(' ', $classes);
    }

    public function telInput(string $attribute, ?string $label = null, array $options = []): string
    {
        $options = array_merge([
            'autocomplete' => 'tel',
            'inputmode' => 'tel',
        ], $options);

        return $this->input('tel', $attribute, $label, $options);
    }

    public function emailInput(string $attribute, ?string $label = null, array $options = []): string
    {
        $options = array_merge([
            'autocomplete' => 'email',
            'inputmode' => 'email',
        ], $options);

        return $this->input('email', $attribute, $label, $options);
    }

    public function hiddenInput(string $attribute, ?string $value = null, array $options = []): string
    {
        return Html::hiddenInput(
            $attribute,
            $value ?? $this->value($attribute),
            $options
        );
    }
}