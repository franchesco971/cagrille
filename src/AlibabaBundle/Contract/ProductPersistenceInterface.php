<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Contract;

use Cagrille\AlibabaBundle\Dto\ProductDto;

/**
 * Contrat de persistance produit.
 * Le bundle Alibaba dépend de cette interface (DIP),
 * pas de l'implémentation Sylius concrète.
 */
interface ProductPersistenceInterface
{
    /**
     * Crée ou met à jour un produit à partir d'un DTO Alibaba.
     */
    public function upsert(ProductDto $dto): void;

    /**
     * Vérifie si un produit avec ce code Alibaba existe déjà.
     */
    public function existsByAlibabaId(string $alibabaId): bool;
}
