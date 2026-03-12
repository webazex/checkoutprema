<?php
return [
    '' => 'site/index',
    'customer' => 'customer/index',
    'customer/<id:\d+>' => 'customer/view',
    'customer/<action>' => 'customer/<action>',
    'customer/orders' => 'customer/orders',
    'customer/orders/<id:\d+>' => 'customer/orders',
    '<controller:\w+>'                        => '<controller>/index',
    '<controller:\w+>/<action:\w+>'  => '<controller>/<action>',
    'catchAll' => 'site/offline',
];