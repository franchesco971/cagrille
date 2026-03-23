<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Contract;

use Cagrille\AlibabaBundle\Dto\ProductDto;

/**
 * Contrat pour l'import et la synchronisation des produits Alibaba.
 * Principe ISP : séparé de OrderEndpointInterface et LogisticsEndpointInterface.
 */
interface ProductEndpointInterface
{
    /**
     * Recherche des produits Alibaba par mot-clé ou catégorie.
     *
     * @param string $keyword   Mot-clé de recherche
     * @param int    $page      Numéro de page (pagination)
     * @param int    $pageSize  Nombre de résultats par page
     * @return ProductDto[]
     */
    public function search(string $keyword, int $page = 1, int $pageSize = 20): array;

    /**
     * Récupère le détail complet d'un produit par son identifiant Alibaba.
     */
    public function getById(string $productId): ProductDto;

    /**
     * Récupère les produits d'une catégorie donnée.
     *
     * @return ProductDto[]
     */
    public function getByCategory(string $categoryId, int $page = 1, int $pageSize = 20): array;
}
