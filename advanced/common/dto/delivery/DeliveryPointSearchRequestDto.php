<?php

declare(strict_types=1);

namespace common\dto\delivery;

use InvalidArgumentException;

final readonly class DeliveryPointSearchRequestDto
{
    public const DEFAULT_LIMIT = 30;
    public const MAX_LIMIT = 30;

    public string $providerCode;
    public string $query;
    public ?string $areaRef;
    public ?string $cityRef;
    public ?string $settlementRef;
    public int $limit;

    public function __construct(
        string $providerCode,
        string $query,
        ?string $areaRef = null,
        ?string $cityRef = null,
        ?string $settlementRef = null,
        int $limit = self::DEFAULT_LIMIT
    ) {
        $this->providerCode = $this->normalizeProviderCode($providerCode);
        $this->query = $this->normalizeQuery($query);
        $this->areaRef = $this->normalizeOptionalRef($areaRef, 'areaRef');
        $this->cityRef = $this->normalizeOptionalRef($cityRef, 'cityRef');
        $this->settlementRef = $this->normalizeOptionalRef(
            $settlementRef,
            'settlementRef'
        );
        $this->limit = $this->normalizeLimit($limit);
    }

    public function hasCityScope(): bool
    {
        return $this->cityRef !== null || $this->settlementRef !== null;
    }

    public function hasAreaScope(): bool
    {
        return $this->areaRef !== null;
    }

    private function normalizeProviderCode(string $providerCode): string
    {
        $providerCode = strtolower(trim($providerCode));

        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $providerCode) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid delivery provider code "%s".',
                $providerCode
            ));
        }

        return $providerCode;
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException(
                'Delivery point search query must not be empty.'
            );
        }

        if (mb_strlen($query) > 64) {
            throw new InvalidArgumentException(
                'Delivery point search query is too long.'
            );
        }

        return $query;
    }

    private function normalizeOptionalRef(
        ?string $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 128) {
            throw new InvalidArgumentException(sprintf(
                'Delivery point search field "%s" is too long.',
                $field
            ));
        }

        return $value;
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return 1;
        }

        return min($limit, self::MAX_LIMIT);
    }
}