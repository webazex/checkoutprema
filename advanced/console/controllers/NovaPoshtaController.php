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
        int $limit = 20,
    ): int {
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
     * Получение отделений выбранного населённого пункта.
     *
     * Пример:
     * php yii nova-poshta/warehouses <CITY_REF>
     */
    public function actionWarehouses(
        string $cityRef,
        int $page = 1,
        int $limit = 100,
    ): int {
        return $this->execute(
            title: 'Nova Poshta warehouses response',
            callback: static function () use (
                $cityRef,
                $page,
                $limit,
            ): array {
                /** @var NovaPoshtaApiClient $client */
                $client = Yii::$container->get(
                    NovaPoshtaApiClient::class
                );

                return $client->getWarehouses(
                    cityRef: $cityRef,
                    page: $page,
                    limit: $limit,
                );
            },
        );
    }

    /**
     * @param callable(): array $callback
     */
    private function execute(
        string $title,
        callable $callback,
    ): int {
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
}