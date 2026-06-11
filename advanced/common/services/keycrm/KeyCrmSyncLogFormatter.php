<?php

declare(strict_types=1);

namespace common\services\keycrm;

use yii\helpers\Json;

final class KeyCrmSyncLogFormatter
{
    public static function event(string $entity, string $status, array $context = []): string
    {
        $contextLine = self::context($context);

        if ($contextLine === '') {
            return sprintf('[KeyCRM][%s][%s]', $entity, $status);
        }

        return sprintf('[KeyCRM][%s][%s] %s', $entity, $status, $contextLine);
    }

    public static function context(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $parts = [];

        foreach ($context as $key => $value) {
            $parts[] = $key . '=' . self::value($value);
        }

        return implode(' ', $parts);
    }

    private static function value(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return self::quoteIfNeeded($value);
        }

        return Json::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function quoteIfNeeded(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/\s/', $value) === 1) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }

        return $value;
    }
}