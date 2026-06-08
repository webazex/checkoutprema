<?php

declare(strict_types=1);

namespace frontend\helpers\checkout;

use Yii;
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
        $options = array_merge([
            'inputmode' => 'tel',
            'autocomplete' => 'tel',
        ], $options);

        return $this->input($attribute, $label, 'text', $options);
    }

    public function emailInput(string $attribute, string $label, array $options = []): string
    {
        $options = array_merge([
            'inputmode' => 'email',
            'autocomplete' => 'email',
        ], $options);

        return $this->input($attribute, $label, 'text', $options);
    }

    public function numberInput(string $attribute, string $label, array $options = []): string
    {
        $options = array_merge([
            'inputmode' => 'numeric',
            'autocomplete' => 'off',
        ], $options);

        return $this->input($attribute, $label, 'number', $options);
    }

    public function hiddenInput(string $name, string $value): string
    {
        return Html::hiddenInput($name, $value);
    }

    private function input(string $attribute, string $label, string $type, array $options = []): string
    {
        $id = $options['id'] ?? $this->buildId($attribute);

        $inputOptions = array_merge([
            'id' => $id,
            'class' => null,
            'autocomplete' => 'off',
        ], $options);

        unset($inputOptions['labelOptions']);

        $input = Html::input(
            $type,
            $attribute,
            $this->value($attribute),
            $inputOptions
        );

        return Html::tag(
            'label',
            Html::tag('span', Html::encode(Yii::t('frontend', $label)))
            . "\n"
            . $input
            . "\n"
            . $this->error($attribute),
            [
                'class' => $this->labelClass($attribute),
                'for' => $id,
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

    private function error(string $attribute): string
    {
        $error = $this->model->getFirstError($attribute);

        if ($error === '') {
            return '';
        }

        return Html::tag(
            'span',
            Html::encode($error),
            ['class' => 'checkout-field__error']
        );
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