<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Cagrille\AliExpressBundle\Dto\ProductDto;

/**
 * Interface de persistance des produits AliExpress dans le catalogue Sylius.
 * Principe DIP : le service de sync dépend de cette abstraction, pas de Sylius.
 */
interface ProductPersistenceInterface
{
    /**
     * Crée ou met à jour un produit Sylius à partir d'un ProductDto AliExpress.
     */
    public function upsert(ProductDto $dto): void;

    /**
     * Vérifie si un produit existe déjà en base pour cet item_id.
     */
    public function existsByAliExpressId(string $aliExpressId): bool;
}
