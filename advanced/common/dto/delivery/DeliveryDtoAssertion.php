<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;
use JsonException;

final class DeliveryDtoAssertion
{
    public static function providerCode(string $value): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid delivery provider code "%s".',
                $value
            ));
        }
    }

    public static function normalizedCode(string $value, string $field): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must contain a normalized lowercase code.',
                $field
            ));
        }
    }

    public static function requiredString(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must not be empty.',
                $field
            ));
        }
    }

    public static function optionalString(?string $value, string $field): void
    {
        if ($value !== null && trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must be null or a non-empty string.',
                $field
            ));
        }
    }

    public static function coordinates(?float $latitude, ?float $longitude): void
    {
        if (($latitude === null) !== ($longitude === null)) {
            throw new InvalidArgumentException(
                'Delivery latitude and longitude must either both be specified or both be null.'
            );
        }

        if ($latitude === null) {
            return;
        }

        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException(
                'Delivery latitude must be between -90 and 90.'
            );
        }

        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException(
                'Delivery longitude must be between -180 and 180.'
            );
        }
    }

    public static function time(?string $value, string $field): void
    {
        if ($value === null) {
            return;
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must use the H:i:s time format.',
                $field
            ));
        }
    }

    public static function date(?string $value, string $field): void
    {
        if ($value === null) {
            return;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must use the Y-m-d date format.',
                $field
            ));
        }

        if (!checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1])) {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" contains an invalid date.',
                $field
            ));
        }
    }

    public static function positiveInt(int $value, string $field): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must be greater than zero.',
                $field
            ));
        }
    }

    public static function nonNegativeInt(int $value, string $field): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf(
                'Delivery field "%s" must not be negative.',
                $field
            ));
        }
    }

    public static function jsonEncodable(array $value, string $field): void
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Delivery field "%s" must contain JSON-encodable data.',
                    $field
                ),
                previous: $exception
            );
        }
    }
}