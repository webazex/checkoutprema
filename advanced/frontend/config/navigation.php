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
        'footer.help' => [
            'type' => 'group',
            'label' => 'Допомога',
            'visible' => true,
            'breadcrumbs' => false,
            'children' => [
                'page.delivery' => [
                    'type' => 'external',
                    'label' => 'Оплата та доставка',
                    'url' => 'https://www.premabrand.com.ua/blank-1',
                    'visible' => true,
                    'target' => '_blank',
                    'breadcrumbs' => false,
                ],
                'page.terms' => [
                    'type' => 'external',
                    'label' => 'Публічна оферта',
                    'url' => 'https://www.premabrand.com.ua/blank-2',
                    'visible' => true,
                    'target' => '_blank',
                    'breadcrumbs' => false,
                ],
                'page.privacy' => [
                    'type' => 'external',
                    'label' => 'Політика конфіденційності',
                    'url' => 'https://www.premabrand.com.ua/blank-3',
                    'visible' => true,
                    'target' => '_blank',
                    'breadcrumbs' => false,
                ],
                'footer.phone' => [
                    'type' => 'external',
                    'label' => '+38093 218 66 24',
                    'url' => 'tel:+380932186624',
                    'visible' => true,
                    'target' => false,
                    'rel' => false,
                    'breadcrumbs' => false,
                ],
                'footer.email' => [
                    'type' => 'external',
                    'label' => 'prema.brand.2025@gmail.com',
                    'url' => 'mailto:prema.brand.2025@gmail.com',
                    'visible' => true,
                    'target' => false,
                    'rel' => false,
                    'breadcrumbs' => false,
                ],
            ],
        ],

        'footer.company' => [
            'type' => 'group',
            'label' => 'Компанія',
            'visible' => true,
            'breadcrumbs' => false,
            'children' => [
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
                ],
                'page.contacts' => [
                    'type' => 'external',
                    'label' => 'Контакти',
                    'url' => 'https://www.premabrand.com.ua/#comp-kbgakxmn_r_comp-mgum76un',
                    'target' => '_blank',
                    'visible' => true,
                    'breadcrumbs' => false,
                ],
            ],
        ],

        'footer.social' => [
            'type' => 'group',
            'label' => 'Соціальні мережі',
            'visible' => true,
            'breadcrumbs' => false,
            'children' => [
                'external.instagram' => [
                    'type' => 'external',
                    'label' => 'Instagram',
                    'url' => 'https://instagram.com/',
                    'visible' => true,
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'breadcrumbs' => false,
                ],
            ],
        ],
    ],
];