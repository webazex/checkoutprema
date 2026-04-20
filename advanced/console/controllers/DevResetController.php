<?php

declare(strict_types=1);

namespace console\controllers;

use common\services\cart\BrokenCartItemCleanupService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class DevResetController extends Controller
{
    public function actionCleanupBrokenCartItems(): int
    {
        /** @var BrokenCartItemCleanupService $service */
        $service = Yii::$container->get(BrokenCartItemCleanupService::class);

        $stats = $service->cleanupBrokenItems();

        $this->stdout("Broken cart items cleanup completed.\n");
        $this->stdout('Broken items found: ' . $stats['brokenItemsFound'] . "\n");
        $this->stdout('Broken items deleted: ' . $stats['brokenItemsDeleted'] . "\n");
        $this->stdout('Affected carts: ' . $stats['affectedCarts'] . "\n");
        $this->stdout('Recalculated carts: ' . $stats['recalculatedCarts'] . "\n");
        $this->stdout('Errors: ' . $stats['errors'] . "\n");

        return ExitCode::OK;
    }

    public function actionCleanupEmptyActiveCarts(): int
    {
        /** @var BrokenCartItemCleanupService $service */
        $service = Yii::$container->get(BrokenCartItemCleanupService::class);

        $stats = $service->cleanupEmptyActiveCarts();

        $this->stdout("Empty active carts cleanup completed.\n");
        $this->stdout('Scanned: ' . $stats['scanned'] . "\n");
        $this->stdout('Fixed: ' . $stats['fixed'] . "\n");
        $this->stdout('Already clean: ' . $stats['alreadyClean'] . "\n");
        $this->stdout('Errors: ' . $stats['errors'] . "\n");

        return ExitCode::OK;
    }
}