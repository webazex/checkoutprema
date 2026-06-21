<?php

declare(strict_types=1);

namespace common\services\novaposhta;

use common\integrations\novaposhta\exceptions\NovaPoshtaApiException;
use yii\helpers\Json;

final class NovaPoshtaLogFormatter
{
    /**
     * @param array<string, mixed> $context
     */
    public static function event(
        string $component,
        string $status,
        array  $context = []
    ): string
    {
        $component = trim($component);
        $status = trim($status);

        $contextLine = self::context($context);

        if ($contextLine === '') {
            return sprintf(
                '[NovaPoshta][%s][%s]',
                $component,
                $status
            );
        }

        return sprintf(
            '[NovaPoshta][%s][%s] %s',
            $component,
            $status,
            $contextLine
        );
    }

    /**
     * @param array<string, mixed> $context
     */
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

        return Json::encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private static function quoteIfNeeded(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/\s/u', $value) === 1) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function exceptionContext(
        NovaPoshtaApiException $exception
    ): array
    {
        return array_merge(
            [
                'message' => $exception->getMessage(),
            ],
            $exception->context()
        );
    }
}