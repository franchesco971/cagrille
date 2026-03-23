<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface;
use Cagrille\AliExpressBundle\Dto\ProductDto;

/**
 * Null Object pattern : persistance de produit sans effet de bord.
 *
 * Permet de démarrer le bundle sans connexion Sylius (tests, dev initial).
 * Remplacée par SyliusProductPersistence en production via l'alias DI.
 */
class NullProductPersistence implements ProductPersistenceInterface
{
    public function upsert(ProductDto $dto): void
    {
        // no-op intentionnel (Null Object)
    }

    public function existsByAliExpressId(string $aliExpressId): bool
    {
        return false;
    }
}
