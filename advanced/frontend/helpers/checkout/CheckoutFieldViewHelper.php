<?php

declare(strict_types=1);

namespace frontend\helpers\checkout;

use yii\base\Model;
use yii\helpers\Html;

final class CheckoutFieldViewHelper
{
    public function __construct(
        private readonly Model $model
    ) {
    }

    public function textInput(string $attribute, string $label, array $options = []): string
    {
        return $this->input($attribute, $label, 'text', $options);
    }

    public function telInput(string $attribute, string $label, array $options = []): string
    {
        return $this->input($attribute, $label, 'text', array_merge([
            'inputmode' => 'tel',
            'autocomplete' => 'tel',
            'placeholder' => '+380XXXXXXXXX',
        ], $options));
    }

    public function emailInput(string $attribute, string $label, array $options = []): string
    {
        return $this->input($attribute, $label, 'text', array_merge([
            'inputmode' => 'email',
            'autocomplete' => 'email',
        ], $options));
    }

    public function numericInput(string $attribute, string $label, array $options = []): string
    {
        return $this->input($attribute, $label, 'text', array_merge([
            'inputmode' => 'numeric',
            'autocomplete' => 'off',
        ], $options));
    }

    public function hiddenInput(string $name, string $value): string
    {
        return Html::hiddenInput($name, $value);
    }

    private function input(string $attribute, string $label, string $type, array $options = []): string
    {
        $id = $options['id'] ?? $this->buildId($attribute);

        $inputOptions = $this->normalizeInputOptions($attribute, $id, $options);

        return Html::tag(
            'label',
            $this->renderLabel($label)
            . $this->renderInput($type, $attribute, $inputOptions)
            . $this->renderError($attribute),
            [
                'class' => $this->labelClass($attribute),
                'for' => $id,
            ]
        );
    }

    private function renderLabel(string $label): string
    {
        return Html::tag(
            'span',
            Html::encode($label),
            ['class' => 'checkout-field__label']
        );
    }

    private function renderInput(string $type, string $attribute, array $options): string
    {
        return Html::input(
            $type,
            $attribute,
            $this->value($attribute),
            $options
        );
    }

    private function renderError(string $attribute): string
    {
        $error = $this->model->getFirstError($attribute);

        if ($error === '') {
            return '';
        }

        return Html::tag(
            'span',
            Html::encode($error),
            [
                'class' => 'checkout-field__error',
                'role' => 'alert',
            ]
        );
    }

    private function normalizeInputOptions(string $attribute, string $id, array $options): array
    {
        unset($options['labelOptions']);

        $options['id'] = $id;

        $options['class'] = trim(
            'checkout-field__control ' . (string)($options['class'] ?? '')
        );

        if ($this->model->hasErrors($attribute)) {
            $options['aria-invalid'] = 'true';
        }

        return $options;
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

    private function value(string $attribute): string
    {
        if (
            property_exists($this->model, $attribute)
            || $this->model->canGetProperty($attribute)
        ) {
            return (string)$this->model->{$attribute};
        }

        return '';
    }

    private function buildId(string $attribute): string
    {
        return 'checkout-' . str_replace('_', '-', $attribute);
    }
}