<?php

use yii\db\Migration;
use yii\rbac\Item;

class m260316_120003_init_rbac extends Migration
{
    public function up()
    {
        $auth = Yii::$app->authManager;

        // Роли
        $admin = $auth->createRole('admin');
        $manager = $auth->createRole('manager');
        $auth->add($admin);
        $auth->add($manager);

        // Разрешения (пример)
        $manageOrders = $auth->createPermission('manageOrders');
        $manageProducts = $auth->createPermission('manageProducts');
        $auth->add($manageOrders);
        $auth->add($manageProducts);

        $auth->addChild($admin, $manageOrders);
        $auth->addChild($admin, $manageProducts);
        $auth->addChild($manager, $manageOrders);

        // Назначаем роль admin первому пользователю (id=1)
        $auth->assign($admin, 1);   // ← поменяй на реальный ID твоего супер-админа
    }

    public function down()
    {
        $auth = Yii::$app->authManager;
        $auth->removeAll();
    }
}