<?php
return [
    'header' => [
        'page.about' => [
            'type' => 'page',
            'label' => 'Про нас',
            'route' => ['/site/about'],
            'visible' => true,
            'breadcrumbs' => true,
        ],

        'catalog' => [
            'type' => 'catalog',
            'label' => 'Каталог',
            'route' => ['/catalog/index'],
            'visible' => true,
            'breadcrumbs' => true,
            'childrenProvider' => 'catalogCategories',
        ],

        'page.bestsellers' => [
            'type' => 'page',
            'label' => 'Бестселери',
            'route' => ['/catalog/index'],
            'visible' => true,
            'breadcrumbs' => true,
        ],

        'page.contacts' => [
            'type' => 'page',
            'label' => 'Контакти',
            'route' => ['/site/contact'],
            'visible' => true,
            'breadcrumbs' => true,
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