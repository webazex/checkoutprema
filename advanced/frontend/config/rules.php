<?php
return [
    'login' => 'customer/login',
    'register' => 'customer/register',
    'exit' => 'customer/logout',
    'restore' => 'customer/restore',
    'restore/<token:[A-Za-z0-9_\-]+>' => 'customer/reset-password',

    'customer' => 'customer/index',
    'customer/<customerHash:[a-zA-Z0-9_-]{8,64}>/orders' => 'customer/orders',
    'customer/<customerHash:[a-zA-Z0-9_-]{8,64}>' => 'customer/view',

    'cart' => 'cart/index',
    'cart/<customerHash:[a-zA-Z0-9_-]{8,64}>/<orderId:[a-zA-Z0-9_-]{8,32}>' => 'cart/view',

    // NEW: canonical checkout flow
    'checkout/<hash:[a-zA-Z0-9_-]{16,128}>' => 'checkout/view',
    'POST checkout/<hash:[a-zA-Z0-9_-]{16,128}>/submit' => 'checkout/submit',
    'checkout/payment-return' => 'checkout/payment-return',

    '' => 'entry/index',
];