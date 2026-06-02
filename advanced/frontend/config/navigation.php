<?php
return [
    'header' => [
        'page.about' => [
            'type' => 'external',
            'label' => 'Про нас',
            'url' => 'https://www.premabrand.com.ua/blank',
            'visible' => true,
            'target' => '_blank',
            'breadcrumbs' => false,
        ],

        'catalog' => [
            'type' => 'catalog',
            'label' => 'Каталог',
            'route' => ['/catalog/index'],
            'visible' => true,
            'breadcrumbs' => true,
            'childrenProvider' => 'catalogCategories',
        ],

        'page.contacts' => [
            'type' => 'external',
            'label' => 'Контакти',
            'url' => 'https://www.premabrand.com.ua/#comp-kbgakxmn_r_comp-mgum76un',
            'target' => '_blank',
            'visible' => true,
            'breadcrumbs' => false,
        ],

        // Пример внешней ссылки. Пока скрыто.
        'external.instagram' => [
            'type' => 'external',
            'label' => 'Instagram',
            'url' => 'https://instagram.com/',
            'visible' => false,
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'breadcrumbs' => false,
        ],
    ],

    'footer' => [
        'page.delivery' => [
            'type' => 'page',
            'label' => 'Доставка та оплата',
            'route' => ['/site/page', 'slug' => 'delivery'],
            'visible' => false,
            'breadcrumbs' => true,
        ],

        'page.privacy' => [
            'type' => 'page',
            'label' => 'Політика конфіденційності',
            'route' => ['/site/page', 'slug' => 'privacy'],
            'visible' => false,
            'breadcrumbs' => true,
        ],

        'page.terms' => [
            'type' => 'page',
            'label' => 'Публічна оферта',
            'route' => ['/site/page', 'slug' => 'terms'],
            'visible' => false,
            'breadcrumbs' => true,
        ],
    ],
];