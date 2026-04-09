<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Api\Endpoint;

use Cagrille\AlibabaBundle\Contract\AlibabaApiClientInterface;
use Cagrille\AlibabaBundle\Contract\ProductEndpointInterface;
use Cagrille\AlibabaBundle\Dto\ProductDto;

/**
 * Endpoint pour les opérations produits de l'API Alibaba.
 * Principe SRP : gère uniquement les appels API produits.
 * Principe OCP : extensible via l'interface ProductEndpointInterface.
 */
class ProductEndpoint implements ProductEndpointInterface
{
    private const ENDPOINT_SEARCH = '/alibaba/icbu/product/list';

    private const ENDPOINT_GET = '/icbu/product/get';

    public function __construct(
        private readonly AlibabaApiClientInterface $client,
    ) {
    }

    public function search(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $response = $this->client->get(self::ENDPOINT_SEARCH, [
            'subject' => $keyword,
            'page_no' => $page,
            'page_size' => min($pageSize, 50),
        ]);

        $items = $response['result']['product_list'] ?? [];

        return array_map(
            static fn (array $item) => ProductDto::fromApiResponse($item),
            $items,
        );
    }

    public function getById(string $productId): ProductDto
    {
        $response = $this->client->get(self::ENDPOINT_GET, [
            'product_get_request' => json_encode(['productId' => $productId]),
        ]);

        $product = $response['product'] ?? $response;

        return ProductDto::fromApiResponse($product + ['_alibaba_id' => $productId]);
    }

    public function getByCategory(string $categoryId, int $page = 1, int $pageSize = 20): array
    {
        $response = $this->client->get(self::ENDPOINT_SEARCH, [
            'category_id' => $categoryId,
            'page_no' => $page,
            'page_size' => min($pageSize, 50),
            'language' => 'ENGLISH',
        ]);

        $items = $response['result']['product_list'] ?? [];

        return array_map(
            static fn (array $item) => ProductDto::fromApiResponse($item),
            $items,
        );
    }
}
