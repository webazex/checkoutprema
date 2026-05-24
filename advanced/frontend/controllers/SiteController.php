<?php

declare(strict_types=1);

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class SiteController extends Controller
{
    /**
     * System actions: error/captcha.
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => \yii\web\ErrorAction::class,
            ],
            'captcha' => [
                'class' => \yii\captcha\CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Главная страница сайта.
     *
     * Сейчас это временная публичная страница.
     * Позже сюда можно перенести полноценную главную из Wix/дизайна.
     */
    public function actionIndex(): string
    {
        $this->view->title = 'Prema — товари для йоги, пілатесу та wellness';

        $this->registerPageMeta(
            description: 'Prema — товари для йоги, пілатесу, спорту, ароматерапії та подарунків. Перегляньте каталог і замовляйте онлайн з доставкою по Україні.',
            canonicalUrl: Yii::$app->urlManager->createAbsoluteUrl(['/site/index'])
        );

        return $this->render('index');
    }

    /**
     * Простые публичные страницы:
     * /about
     * /contacts
     * /delivery
     * /payment
     * etc.
     */
    public function actionPage(string $slug): string
    {
        $page = $this->getStaticPageConfig($slug);

        if ($page === null) {
            throw new NotFoundHttpException('Сторінку не знайдено.');
        }

        $this->view->title = $page['title'];

        $this->registerPageMeta(
            description: $page['description'],
            canonicalUrl: Yii::$app->urlManager->createAbsoluteUrl(['/site/page', 'slug' => $slug])
        );

        return $this->render('page', [
            'slug' => $slug,
            'page' => $page,
        ]);
    }

    /**
     * Временный источник статических страниц.
     *
     * На следующем этапе это можно заменить на:
     * - таблицу static_page;
     * - config-файл;
     * - админку;
     * - импорт из markdown/json.
     */
    private function getStaticPageConfig(string $slug): ?array
    {
        $pages = [
            'about' => [
                'title' => 'Про Prema',
                'h1' => 'Про Prema',
                'description' => 'Дізнайтеся більше про Prema, бренд товарів для йоги, пілатесу, спорту та щоденних wellness-практик.',
                'body' => 'Цю сторінку буде наповнено після підготовки контенту.',
            ],
            'contacts' => [
                'title' => 'Контакти Prema',
                'h1' => 'Контакти',
                'description' => 'Контактна інформація Prema для питань щодо товарів, замовлень, доставки та співпраці.',
                'body' => 'Контактну інформацію буде додано після узгодження з замовником.',
            ],
            'delivery' => [
                'title' => 'Доставка і оплата — Prema',
                'h1' => 'Доставка і оплата',
                'description' => 'Інформація про доставку та оплату замовлень Prema по Україні.',
                'body' => 'Умови доставки та оплати буде додано після узгодження.',
            ],
        ];

        return $pages[$slug] ?? null;
    }

    private function registerPageMeta(string $description, string $canonicalUrl): void
    {
        $view = $this->view;

        $view->registerMetaTag(['name' => 'description', 'content' => $description], 'description');
        $view->registerMetaTag(['name' => 'robots', 'content' => 'index, follow'], 'robots');
        $view->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl], 'canonical');

        $view->registerMetaTag(['property' => 'og:title', 'content' => (string)$view->title], 'og:title');
        $view->registerMetaTag(['property' => 'og:description', 'content' => $description], 'og:description');
        $view->registerMetaTag(['property' => 'og:url', 'content' => $canonicalUrl], 'og:url');
        $view->registerMetaTag(['property' => 'og:type', 'content' => 'website'], 'og:type');

        $view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image'], 'twitter:card');
        $view->registerMetaTag(['name' => 'twitter:title', 'content' => (string)$view->title], 'twitter:title');
        $view->registerMetaTag(['name' => 'twitter:description', 'content' => $description], 'twitter:description');
    }
}