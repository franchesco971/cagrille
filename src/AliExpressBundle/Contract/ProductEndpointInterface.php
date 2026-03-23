<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Cagrille\AliExpressBundle\Dto\ProductDto;

/**
 * Interface d'accès aux produits AliExpress DS.
 */
interface ProductEndpointInterface
{
    /**
     * Récupère les détails d'un produit par son item_id.
     */
    public function getById(string $itemId): ProductDto;

    /**
     * Recherche des produits par mot-clé (pagination par page/pageSize).
     *
     * @return ProductDto[]
     */
    public function search(string $keyword, int $page = 1, int $pageSize = 20): array;
}
