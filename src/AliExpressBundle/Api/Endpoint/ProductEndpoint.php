<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api\Endpoint;

use Cagrille\AliExpressBundle\Api\AliExpressApiClient;
use Cagrille\AliExpressBundle\Contract\ProductEndpointInterface;
use Cagrille\AliExpressBundle\Dto\ProductDto;

/**
 * Endpoint produits AliExpress DS.
 *
 * Méthodes API utilisées :
 *  - aliexpress.ds.product.get    → détail d'un produit
 *  - aliexpress.affiliate.product.query → recherche de produits
 *
 * Principe SRP : délègue la signature/HTTP à AliExpressApiClient.
 */
class ProductEndpoint implements ProductEndpointInterface
{
    public function __construct(
        private readonly AliExpressApiClient $client,
    ) {
    }

    public function getById(string $itemId): ProductDto
    {
        $data = $this->client->call('aliexpress.ds.product.get', array_merge(
            $this->client->getProductQueryDefaults(),
            ['product_id' => $itemId],
        ));

        // Structure réelle : aliexpress_ds_product_get_response.result
        $result = $data['aliexpress_ds_product_get_response']['result']
            ?? $data['result']
            ?? $data;

        return ProductDto::fromApiResponse($result);
    }

    /**
     * {@inheritdoc}
     *
     * Utilise aliexpress.affiliate.product.query qui retourne des listes
     * de produits avec filtrages par mots-clés.
     *
     * @return ProductDto[]
     */
    public function search(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $data = $this->client->call('aliexpress.affiliate.product.query', array_merge(
            $this->client->getProductQueryDefaults(),
            [
                'keywords' => $keyword,
                'page_no' => $page,
                'page_size' => $pageSize,
            ],
        ));

        $items = $data['aliexpress_affiliate_product_query_response']['resp_result']['result']['products']['product'] ?? [];

        return array_map(
            static fn (array $item) => ProductDto::fromApiResponse($item),
            $items,
        );
    }
}
