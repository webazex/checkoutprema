<?php
return [
    // Самые конкретные правила — в начале
    ''                      => 'site/index',          // главная
    'customer'              => 'customer/index',
    'customer/<id:\d+>'     => 'customer/view',
    'customer/orders'       => 'customer/orders',     // конкретный экшен
    'customer/orders/<id:\d+>' => 'customer/orders',  // если id опционален — подкорректируй

    // Конкретные страницы (добавь все статические, чтобы они не попадали в общие правила)
//    'about'                 => 'site/about',
//    'contact'               => 'site/contact',
    // 'cart'               => 'cart/index',
    // 'checkout'           => 'order/checkout',

    // Общие правила — после конкретных!
    '<controller:\w+>'                        => '<controller>/index',
    '<controller:\w+>/<action:\w+>'           => '<controller>/<action>',
    '<controller:\w+>/<action:\w+>/<id:\d+>'  => '<controller>/<action>',
    '<controller:\w+>/<id:\d+>'               => '<controller>/view',


];