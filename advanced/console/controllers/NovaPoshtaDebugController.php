<?php

declare(strict_types=1);

namespace console\controllers;

use common\services\novaposhta\NovaPoshtaApiService;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

final class NovaPoshtaDebugController extends Controller
{
    /**
     * Возвращает полный сырой ответ поиска населённых пунктов.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-settlements "Одеса" 20
     */
    public function actionRawSettlements(string $query, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta raw settlements response',
            fn (): array => $this->getApiService()->searchSettlements($query, $limit)
        );
    }

    /**
     * Возвращает полный сырой ответ getWarehouses
     * только для обычных почтовых отделений.
     *
     * В $deliveryCityRef передаётся значение DeliveryCity,
     * полученное из searchSettlements.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-post-offices <DELIVERY_CITY_REF> 1 20
     */
    public function actionRawPostOffices(string $deliveryCityRef, int $page = 1, int $limit = 20): int
    {
        return $this->execute(
            'Nova Poshta raw post offices response',
            fn (): array => $this->getApiService()->getPostOffices($deliveryCityRef, $page, $limit)
        );
    }

    /**
     * Возвращает полный сырой справочник типов отделений.
     *
     * Пример:
     * php yii nova-poshta-debug/raw-warehouse-types
     */
    public function actionRawWarehouseTypes(): int
    {
        return $this->execute(
            'Nova Poshta raw warehouse types response',
            fn (): array => $this->getApiService()->getWarehouseTypes()
        );
    }

    /**
     * @param callable(): array<string, mixed> $callback
     */
    private function execute(string $title, callable $callback): int
    {
        try {
            $response = $callback();

            $this->stdout($title . PHP_EOL . PHP_EOL);
            $this->stdout(
                Json::encode(
                    $response,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );

            return ExitCode::OK;
        } catch (Throwable $e) {
            $this->stderr(
                'Nova Poshta debug request failed: ' . $e->getMessage() . PHP_EOL
            );

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    private function getApiService(): NovaPoshtaApiService
    {
        /** @var NovaPoshtaApiService $service */
        $service = Yii::$container->get(NovaPoshtaApiService::class);

        return $service;
    }
}