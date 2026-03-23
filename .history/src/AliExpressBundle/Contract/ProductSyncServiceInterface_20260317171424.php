<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

/**
 * Interface du service de synchronisation des produits AliExpress.
 */
interface ProductSyncServiceInterface
{
    /**
     * Synchronise tous les produits selon les mots-clés configurés.
     *
     * @return int Nombre de produits traités
     */
    public function syncAll(): int;

    /**
     * Synchronise les produits correspondant à un mot-clé.
     *
     * @return int Nombre de produits traités
     */
    public function syncByKeyword(string $keyword): int;

    /**
     * Importe ou met à jour un seul produit par son item_id AliExpress.
     */
    public function importOne(string $aliExpressItemId): void;
}
