<?php

declare(strict_types=1);

namespace api\modules\v1;

use yii\base\Module as BaseModule;

final class Module extends BaseModule
{
    public $controllerNamespace = 'api\modules\v1\controllers';

    public function init(): void
    {
        parent::init();

        $this->modules = [
            'public' => [
                'class' => \api\modules\v1\modules\publicApi\Module::class,
            ],
            'callbacks' => [
                'class' => \api\modules\v1\modules\callbacks\Module::class,
            ],
            'integrations' => [
                'class' => \api\modules\v1\modules\integrations\Module::class,
            ],
        ];
    }
}