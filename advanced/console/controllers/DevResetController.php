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

        $stats = $service->cleanup();

        $this->stdout("Broken cart items cleanup completed.\n");
        $this->stdout('Broken items found: ' . $stats['brokenItemsFound'] . "\n");
        $this->stdout('Broken items deleted: ' . $stats['brokenItemsDeleted'] . "\n");
        $this->stdout('Affected carts: ' . $stats['affectedCarts'] . "\n");
        $this->stdout('Recalculated carts: ' . $stats['recalculatedCarts'] . "\n");
        $this->stdout('Errors: ' . $stats['errors'] . "\n");

        return ExitCode::OK;
    }
}