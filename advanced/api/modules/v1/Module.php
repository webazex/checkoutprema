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
                'class' => modules\publicApi\Module::class,
            ],
            'callbacks' => [
                'class' => modules\callbacks\Module::class,
            ],
            'integrations' => [
                'class' => modules\integrations\Module::class,
            ],
        ];
    }
}