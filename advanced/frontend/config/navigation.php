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
            'type' => 'external',
            'label' => 'Доставка та оплата',
            'url' => 'https://www.premabrand.com.ua/blank-1',
            'visible' => true,
            'target' => '_blank',
            'breadcrumbs' => false,
        ],

        'page.privacy' => [
            'type' => 'external',
            'label' => 'Політика конфіденційності',
            'url' => 'https://www.premabrand.com.ua/blank-3',
            'visible' => true,
            'breadcrumbs' => false,
            'target' => '_blank',
        ],

        'page.terms' => [
            'type' => 'external',
            'label' => 'Публічна оферта',
            'url' => 'https://www.premabrand.com.ua/blank-2',
            'visible' => false,
            'breadcrumbs' => false,
            'target' => '_blank',
        ],
    ],
];