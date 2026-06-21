<?php

declare(strict_types=1);

namespace common\services\delivery;

use common\dto\delivery\DeliveryAreaReadDto;
use common\dto\delivery\DeliveryPointReadDto;
use common\dto\delivery\DeliveryPointScheduleReadDto;
use common\dto\delivery\DeliveryProviderReadDto;
use common\dto\delivery\DeliverySettlementReadDto;
use common\enums\delivery\DeliverySyncScope;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;
use Yii;
use yii\caching\CacheInterface;
use yii\mutex\Mutex;

final readonly class DeliveryDirectoryCacheService
{
    private const SCHEMA_VERSION = 1;
    private const VERSION_LOCK_TIMEOUT = 3;
    private const LOG_CATEGORY = 'delivery.sync.cache';

    public function __construct(
        private CacheInterface $cache,
        private Mutex          $mutex,
        private int            $dataTtl = 86400,
    )
    {
        if ($this->dataTtl < 1) {
            throw new InvalidArgumentException(
                'Delivery directory cache TTL must be greater than zero.'
            );
        }
    }

    /**
     * @param callable(): list<DeliveryProviderReadDto> $loader
     *
     * @return list<DeliveryProviderReadDto>
     */
    public function rememberProviders(callable $loader): array
    {
        return $this->remember(
            key: $this->providersDataKey(),
            loader: $loader,
            expectedClass: DeliveryProviderReadDto::class
        );
    }

    /**
     * @param callable(): array<mixed> $loader
     *
     * @return list<object>
     */
    private function remember(
        string   $key,
        callable $loader,
        string   $expectedClass
    ): array
    {
        $cached = $this->cache->get($key);

        if ($cached !== false) {
            if ($this->isValidList($cached, $expectedClass)) {
                return $cached;
            }

            $this->cache->delete($key);

            Yii::warning(
                DeliverySyncLogFormatter::event('cache', 'CORRUPTED', [
                    'key' => $key,
                    'expectedClass' => $expectedClass,
                ]),
                self::LOG_CATEGORY
            );
        }

        $items = $loader();

        if (!$this->isValidList($items, $expectedClass)) {
            throw new UnexpectedValueException(sprintf(
                'Delivery cache loader must return a list of %s objects.',
                $expectedClass
            ));
        }

        if (!$this->cache->set($key, $items, $this->dataTtl)) {
            Yii::warning(
                DeliverySyncLogFormatter::event('cache', 'WRITE_FAILED', [
                    'key' => $key,
                ]),
                self::LOG_CATEGORY
            );
        }

        return $items;
    }

    private function isValidList(mixed $value, string $expectedClass): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$item instanceof $expectedClass) {
                return false;
            }
        }

        return true;
    }

    private function providersDataKey(): string
    {
        return sprintf(
            'delivery:providers:v%d:%s',
            self::SCHEMA_VERSION,
            $this->versionToken('providers')
        );
    }

    private function versionToken(
        string  $segment,
        ?string $providerCode = null
    ): string
    {
        $key = $this->versionKey($segment, $providerCode);
        $token = $this->cache->get($key);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $lockName = $this->versionLockName($key);

        if (!$this->mutex->acquire($lockName, self::VERSION_LOCK_TIMEOUT)) {
            throw new RuntimeException(sprintf(
                'Can not acquire delivery cache version lock "%s".',
                $lockName
            ));
        }

        try {
            $token = $this->cache->get($key);

            if (is_string($token) && $token !== '') {
                return $token;
            }

            $token = $this->newVersionToken();
            $this->writeVersionToken($key, $token);

            return $token;
        } finally {
            $this->mutex->release($lockName);
        }
    }

    private function versionKey(
        string  $segment,
        ?string $providerCode = null
    ): string
    {
        if ($providerCode === null) {
            return sprintf(
                'delivery:version:v%d:%s',
                self::SCHEMA_VERSION,
                $segment
            );
        }

        return sprintf(
            'delivery:version:v%d:%s:%s',
            self::SCHEMA_VERSION,
            $providerCode,
            $segment
        );
    }

    private function versionLockName(string $key): string
    {
        return 'delivery:cache-version:' . sha1($key);
    }

    private function newVersionToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function writeVersionToken(string $key, string $token): void
    {
        if ($this->cache->set($key, $token, 0)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Can not persist delivery cache version "%s".',
            $key
        ));
    }

    /**
     * @param callable(): list<DeliveryAreaReadDto> $loader
     *
     * @return list<DeliveryAreaReadDto>
     */
    public function rememberAreas(string $providerCode, callable $loader): array
    {
        $providerCode = $this->normalizeProviderCode($providerCode);

        return $this->remember(
            key: $this->areasDataKey($providerCode),
            loader: $loader,
            expectedClass: DeliveryAreaReadDto::class
        );
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

    private function areasDataKey(string $providerCode): string
    {
        return sprintf(
            'delivery:%s:areas:v%d:%s:%s',
            $providerCode,
            self::SCHEMA_VERSION,
            $this->versionToken('providers'),
            $this->versionToken('areas', $providerCode)
        );
    }

    /**
     * @param callable(): list<DeliverySettlementReadDto> $loader
     *
     * @return list<DeliverySettlementReadDto>
     */
    public function rememberSettlements(
        string   $providerCode,
        int      $areaId,
        callable $loader
    ): array
    {
        $providerCode = $this->normalizeProviderCode($providerCode);
        $this->assertPositiveId($areaId, 'areaId');

        return $this->remember(
            key: $this->settlementsDataKey($providerCode, $areaId),
            loader: $loader,
            expectedClass: DeliverySettlementReadDto::class
        );
    }

    private function assertPositiveId(int $id, string $field): void
    {
        if ($id > 0) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Delivery cache field "%s" must be greater than zero.',
            $field
        ));
    }

    private function settlementsDataKey(
        string $providerCode,
        int    $areaId
    ): string
    {
        return sprintf(
            'delivery:%s:settlements:%d:v%d:%s:%s:%s',
            $providerCode,
            $areaId,
            self::SCHEMA_VERSION,
            $this->versionToken('providers'),
            $this->versionToken('areas', $providerCode),
            $this->versionToken('settlements', $providerCode)
        );
    }

    /**
     * @param callable(): list<DeliveryPointReadDto> $loader
     *
     * @return list<DeliveryPointReadDto>
     */
    public function rememberPoints(
        string   $providerCode,
        int      $settlementId,
        callable $loader
    ): array
    {
        $providerCode = $this->normalizeProviderCode($providerCode);
        $this->assertPositiveId($settlementId, 'settlementId');

        return $this->remember(
            key: $this->pointsDataKey($providerCode, $settlementId),
            loader: $loader,
            expectedClass: DeliveryPointReadDto::class
        );
    }

    private function pointsDataKey(
        string $providerCode,
        int    $settlementId
    ): string
    {
        return sprintf(
            'delivery:%s:points:%d:v%d:%s:%s:%s:%s',
            $providerCode,
            $settlementId,
            self::SCHEMA_VERSION,
            $this->versionToken('providers'),
            $this->versionToken('areas', $providerCode),
            $this->versionToken('settlements', $providerCode),
            $this->versionToken('points', $providerCode)
        );
    }

    /**
     * @param callable(): list<DeliveryPointScheduleReadDto> $loader
     *
     * @return list<DeliveryPointScheduleReadDto>
     */
    public function rememberPointSchedules(
        string   $providerCode,
        int      $pointId,
        callable $loader
    ): array
    {
        $providerCode = $this->normalizeProviderCode($providerCode);
        $this->assertPositiveId($pointId, 'pointId');

        return $this->remember(
            key: $this->pointSchedulesDataKey($providerCode, $pointId),
            loader: $loader,
            expectedClass: DeliveryPointScheduleReadDto::class
        );
    }

    private function pointSchedulesDataKey(
        string $providerCode,
        int    $pointId
    ): string
    {
        return sprintf(
            'delivery:%s:point:%d:schedule:v%d:%s:%s:%s:%s',
            $providerCode,
            $pointId,
            self::SCHEMA_VERSION,
            $this->versionToken('providers'),
            $this->versionToken('areas', $providerCode),
            $this->versionToken('settlements', $providerCode),
            $this->versionToken('points', $providerCode)
        );
    }

    public function invalidateProviders(): void
    {
        $this->rotateVersion('providers');
    }

    private function rotateVersion(
        string  $segment,
        ?string $providerCode = null
    ): void
    {
        $key = $this->versionKey($segment, $providerCode);
        $lockName = $this->versionLockName($key);

        if (!$this->mutex->acquire($lockName, self::VERSION_LOCK_TIMEOUT)) {
            throw new RuntimeException(sprintf(
                'Can not acquire delivery cache version lock "%s".',
                $lockName
            ));
        }

        try {
            $this->writeVersionToken($key, $this->newVersionToken());
        } finally {
            $this->mutex->release($lockName);
        }
    }

    public function invalidateAfterSync(
        string            $providerCode,
        DeliverySyncScope $scope
    ): void
    {
        $providerCode = $this->normalizeProviderCode($providerCode);

        match ($scope) {
            DeliverySyncScope::AREAS => $this->rotateVersion(
                'areas',
                $providerCode
            ),

            DeliverySyncScope::SETTLEMENTS => $this->rotateVersion(
                'settlements',
                $providerCode
            ),

            DeliverySyncScope::POINTS,
            DeliverySyncScope::SCHEDULES => $this->rotateVersion(
                'points',
                $providerCode
            ),
        };
    }
}