<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Contract;

/**
 * Contrat pour la synchronisation des produits vers Sylius.
 */
interface ProductSyncServiceInterface
{
    /**
     * Synchronise tous les produits des catégories configurées vers Sylius.
     *
     * @return int Nombre de produits synchronisés
     */
    public function syncAll(): int;

    /**
     * Synchronise un produit spécifique par son ID Alibaba.
     */
    public function syncOne(string $alibabaProductId): void;

    /**
     * Synchronise les produits d'une catégorie donnée.
     *
     * @return int Nombre de produits synchronisés
     */
    public function syncByCategory(string $categoryId): int;
}
