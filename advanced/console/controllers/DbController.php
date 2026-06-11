<?php

declare(strict_types=1);

namespace console\controllers;

use RuntimeException;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

final class DbController extends Controller
{
    public bool $force = false;
    public bool $dryRun = false;
    public bool $resetAutoIncrement = true;
    public bool $includeQueue = false;
    public bool $includeSyncState = false;

    /**
     * @return string[]
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'force',
            'dryRun',
            'resetAutoIncrement',
            'includeQueue',
            'includeSyncState',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'f' => 'force',
        ]);
    }

    /**
     * Cleans local transactional/test data before production launch.
     *
     * Keeps catalog data:
     * - product
     * - product_external_map
     * - catalog_category
     *
     * Removes checkout/order/customer/payment data:
     * - payment_log
     * - payment
     * - order_item
     * - order
     * - cart_item
     * - cart
     * - customer_password_reset_token
     * - customer
     * - related meta rows
     */
    public function actionProdClean(): int
    {
        $this->stdout(PHP_EOL . 'CheckoutPrema DB prod clean' . PHP_EOL, Console::FG_YELLOW);
        $this->stdout('This command removes local checkout/customer/order/payment/cart data.' . PHP_EOL, Console::FG_YELLOW);
        $this->stdout('Catalog products and categories will NOT be removed.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        $plan = $this->buildCleanupPlan();

        $this->printCounts($plan);

        if ($this->dryRun) {
            $this->stdout(PHP_EOL . 'Dry run mode enabled. Nothing was changed.' . PHP_EOL, Console::FG_CYAN);

            return ExitCode::OK;
        }

        if (!$this->force) {
            $this->stdout(PHP_EOL, Console::FG_YELLOW);
            $confirmed = $this->confirm(
                'This will permanently delete local checkout/customer/order/payment/cart data. Continue?'
            );

            if (!$confirmed) {
                $this->stdout('Cancelled.' . PHP_EOL, Console::FG_YELLOW);

                return ExitCode::OK;
            }
        }

        try {
            $this->cleanup($plan);
        } catch (Throwable $e) {
            $this->stderr(PHP_EOL . 'Cleanup failed: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(PHP_EOL . 'Cleanup completed successfully.' . PHP_EOL, Console::FG_GREEN);

        $this->printCounts($plan);

        return ExitCode::OK;
    }

    /**
     * @return array<int, array{type:string, table?:string, entityTypes?:string[], label:string}>
     */
    private function buildCleanupPlan(): array
    {
        $plan = [
            [
                'type' => 'meta',
                'entityTypes' => [
                    'customer',
                    'order',
                    'order_item',
                    'payment',
                ],
                'label' => 'meta rows for customer/order/order_item/payment',
            ],
            [
                'type' => 'table',
                'table' => '{{%payment_log}}',
                'label' => 'payment_log',
            ],
            [
                'type' => 'table',
                'table' => '{{%payment}}',
                'label' => 'payment',
            ],
            [
                'type' => 'table',
                'table' => '{{%order_item}}',
                'label' => 'order_item',
            ],
            [
                'type' => 'table',
                'table' => '{{%cart_item}}',
                'label' => 'cart_item',
            ],
            [
                'type' => 'table',
                'table' => '{{%order}}',
                'label' => 'order',
            ],
            [
                'type' => 'table',
                'table' => '{{%cart}}',
                'label' => 'cart',
            ],
            [
                'type' => 'table',
                'table' => '{{%customer_password_reset_token}}',
                'label' => 'customer_password_reset_token',
            ],
            [
                'type' => 'table',
                'table' => '{{%customer}}',
                'label' => 'customer',
            ],
        ];

        if ($this->includeQueue) {
            $plan[] = [
                'type' => 'table',
                'table' => '{{%queue}}',
                'label' => 'queue',
            ];
        }

        if ($this->includeSyncState) {
            $plan[] = [
                'type' => 'table',
                'table' => '{{%keycrm_sync_state}}',
                'label' => 'keycrm_sync_state',
            ];

            $plan[] = [
                'type' => 'table',
                'table' => '{{%keycrm_sync_run}}',
                'label' => 'keycrm_sync_run',
            ];
        }

        return $plan;
    }

    /**
     * @param array<int, array{type:string, table?:string, entityTypes?:string[], label:string}> $plan
     */
    private function printCounts(array $plan): void
    {
        $this->stdout(PHP_EOL . 'Current rows:' . PHP_EOL, Console::FG_CYAN);

        foreach ($plan as $item) {
            $count = $this->countPlanItem($item);

            $this->stdout(sprintf(
                ' - %-45s %d%s',
                $item['label'] . ':',
                $count,
                PHP_EOL
            ));
        }
    }

    /**
     * @param array{type:string, table?:string, entityTypes?:string[], label:string} $item
     */
    private function countPlanItem(array $item): int
    {
        if ($item['type'] === 'meta') {
            $entityTypes = $item['entityTypes'] ?? [];

            if ($entityTypes === []) {
                return 0;
            }

            return (int)Yii::$app->db
                ->createCommand()
                ->select('COUNT(*)')
                ->from('{{%meta}}')
                ->where(['entity_type' => $entityTypes])
                ->queryScalar();
        }

        if ($item['type'] === 'table') {
            $table = $item['table'] ?? null;

            if ($table === null) {
                throw new RuntimeException('Cleanup plan table is missing.');
            }

            if (Yii::$app->db->schema->getTableSchema($table) === null) {
                return 0;
            }

            return (int)Yii::$app->db
                ->createCommand("SELECT COUNT(*) FROM {$table}")
                ->queryScalar();
        }

        throw new RuntimeException('Unknown cleanup plan item type: ' . $item['type']);
    }

    /**
     * @param array<int, array{type:string, table?:string, entityTypes?:string[], label:string}> $plan
     */
    private function cleanup(array $plan): void
    {
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            foreach ($plan as $item) {
                if ($item['type'] === 'meta') {
                    $entityTypes = $item['entityTypes'] ?? [];

                    if ($entityTypes !== []) {
                        $deleted = $db
                            ->createCommand()
                            ->delete('{{%meta}}', ['entity_type' => $entityTypes])
                            ->execute();

                        $this->stdout("Deleted {$deleted} rows from {$item['label']}." . PHP_EOL);
                    }

                    continue;
                }

                if ($item['type'] === 'table') {
                    $table = $item['table'] ?? null;

                    if ($table === null) {
                        throw new RuntimeException('Cleanup plan table is missing.');
                    }

                    if ($db->schema->getTableSchema($table) === null) {
                        $this->stdout("Skipped missing table {$item['label']}." . PHP_EOL, Console::FG_YELLOW);
                        continue;
                    }

                    $deleted = $db
                        ->createCommand()
                        ->delete($table)
                        ->execute();

                    $this->stdout("Deleted {$deleted} rows from {$item['label']}." . PHP_EOL);

                    continue;
                }

                throw new RuntimeException('Unknown cleanup plan item type: ' . $item['type']);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($this->resetAutoIncrement) {
            $this->resetAutoIncrements($plan);
        }
    }

    /**
     * @param array<int, array{type:string, table?:string, entityTypes?:string[], label:string}> $plan
     */
    private function resetAutoIncrements(array $plan): void
    {
        $driver = Yii::$app->db->driverName;

        if (!in_array($driver, ['mysql', 'mysqli'], true)) {
            $this->stdout(
                'Auto increment reset skipped: unsupported DB driver ' . $driver . PHP_EOL,
                Console::FG_YELLOW
            );

            return;
        }

        foreach ($plan as $item) {
            if ($item['type'] !== 'table') {
                continue;
            }

            $table = $item['table'] ?? null;

            if ($table === null) {
                continue;
            }

            if (Yii::$app->db->schema->getTableSchema($table) === null) {
                continue;
            }

            Yii::$app->db
                ->createCommand("ALTER TABLE {$table} AUTO_INCREMENT = 1")
                ->execute();

            $this->stdout("Reset AUTO_INCREMENT for {$item['label']}." . PHP_EOL, Console::FG_BLUE);
        }
    }
}