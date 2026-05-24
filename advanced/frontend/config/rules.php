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
    'POST cart/add' => 'cart/add',
    'POST cart/buy-now' => 'cart/buy-now',

    // SEO-ready catalog
    'catalog' => 'catalog/index',
    'catalog/<categorySlug:[a-z0-9-]+>' => 'catalog/category',
    'catalog/<categorySlug:[a-z0-9-]+>/<productSlug:[a-z0-9-]+>' => 'catalog/product',

    // canonical checkout flow
    'checkout/<hash:[a-zA-Z0-9_-]{16,128}>' => 'checkout/view',
    'POST checkout/<hash:[a-zA-Z0-9_-]{16,128}>/submit' => 'checkout/submit',
    'checkout/payment-return' => 'checkout/payment-return',
    'POST checkout/<hash:[a-zA-Z0-9_-]{16,128}>/clear' => 'checkout/clear',
    'POST checkout/<hash:[a-zA-Z0-9_-]{16,128}>/remove-item' => 'checkout/remove-item',

    '' => 'entry/index',
];