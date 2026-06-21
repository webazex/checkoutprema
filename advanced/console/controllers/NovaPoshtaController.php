<?php

declare(strict_types=1);

namespace console\controllers;

use common\integrations\novaposhta\NovaPoshtaApiClient;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

final class NovaPoshtaController extends Controller
{
    /**
     * Поиск населённых пунктов.
     *
     * Пример:
     * php yii nova-poshta/search-settlements "Одеса"
     */
    public function actionSearchSettlements(
        string $query,
        int    $limit = 20,
    ): int
    {
        return $this->execute(
            title: 'Nova Poshta settlements response',
            callback: static function () use ($query, $limit): array {
                /** @var NovaPoshtaApiClient $client */
                $client = Yii::$container->get(
                    NovaPoshtaApiClient::class
                );

                return $client->searchSettlements(
                    query: $query,
                    limit: $limit,
                );
            },
        );
    }

    /**
     * @param callable(): array $callback
     */
    private function execute(
        string   $title,
        callable $callback,
    ): int
    {
        try {
            $data = $callback();

            $this->stdout($title . PHP_EOL);
            $this->stdout('Items: ' . count($data) . PHP_EOL);
            $this->stdout(PHP_EOL);

            $this->stdout(
                Json::encode(
                    $data,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );

            return ExitCode::OK;
        } catch (Throwable $e) {
            $this->stderr(
                'Nova Poshta request failed: '
                . $e->getMessage()
                . PHP_EOL
            );

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Получение отделений и почтоматов выбранного города.
     *
     * На этом диагностическом этапе выводим исходный ответ API.
     * Фильтрация почтоматов будет выполняться отдельным сервисом.
     *
     * В cityRef нужно передавать значение DeliveryCity,
     * полученное из searchSettlements().
     *
     * Пример:
     * php yii nova-poshta/warehouses <DELIVERY_CITY_REF>
     */

    public function actionWarehouses(string $cityRef, ?string $warehouseTypeRef = null,
                                     int    $page = 1, int $limit = 100): int
    {
        return $this->execute(
            title: 'Nova Poshta warehouses response',
            callback: static function () use (
                $cityRef,
                $warehouseTypeRef,
                $page,
                $limit,
            ): array {
                /** @var NovaPoshtaApiClient $client */
                $client = Yii::$container->get(
                    NovaPoshtaApiClient::class
                );

                return $client->getWarehouses(
                    cityRef: $cityRef,
                    warehouseTypeRef: $warehouseTypeRef,
                    page: $page,
                    limit: $limit,
                );
            },
        );
    }

    /**
     * Получение официального справочника типов точек Новой почты.
     *
     * Он нужен, чтобы отделять обычные отделения от почтоматов
     * по TypeOfWarehouseRef, а не по тексту названия.
     *
     * Пример:
     * php yii nova-poshta/warehouse-types
     */
    public function actionWarehouseTypes(): int
    {
        return $this->execute(
            title: 'Nova Poshta warehouse types response',
            callback: static function (): array {
                /** @var NovaPoshtaApiClient $client */
                $client = Yii::$container->get(
                    NovaPoshtaApiClient::class
                );

                $response = $client->call(
                    modelName: 'Address',
                    calledMethod: 'getWarehouseTypes',
                );

                $data = $response['data'] ?? [];

                return is_array($data) ? $data : [];
            },
        );
    }
}