<?php

declare(strict_types=1);

namespace common\services\catalog;

use common\mappers\catalog\PublicProductMapper;
use common\models\product\ProductModel;
use yii\data\Pagination;
use yii\web\NotFoundHttpException;

final class PublicCatalogService
{
    public function __construct(
        private readonly PublicProductMapper $productMapper,
    ) {
    }

    public function getList(int $page = 1, int $perPage = 20, bool $onlyAvailable = false): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $query = ProductModel::find()
            ->notArchived()
            ->ordered();

        if ($onlyAvailable) {
            $query->andWhere(['>', 'quantity', 0]);
        }

        $countQuery = clone $query;

        $pagination = new Pagination([
            'totalCount' => (int)$countQuery->count('*'),
            'pageSize' => $perPage,
            'page' => $page - 1,
            'pageSizeLimit' => [1, 100],
            'validatePage' => false,
        ]);

        $products = $query
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        return [
            'items' => $this->productMapper->mapMany($products),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => (int)$pagination->totalCount,
                'pageCount' => (int)$pagination->getPageCount(),
                'hasNextPage' => $page < (int)$pagination->getPageCount(),
                'hasPrevPage' => $page > 1,
            ],
        ];
    }

    public function getItemById(int $id): array
    {
        $product = ProductModel::find()
            ->notArchived()
            ->byId($id)
            ->one();

        if (!$product instanceof ProductModel) {
            throw new NotFoundHttpException(sprintf('Product with id %d was not found.', $id));
        }

        return $this->productMapper->mapOne($product)->toArray();
    }
}