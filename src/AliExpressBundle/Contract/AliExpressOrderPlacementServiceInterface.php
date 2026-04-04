<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Interface de placement des commandes AliExpress depuis une commande Sylius.
 *
 * Responsabilité unique : générer les commandes AliExpress pour les items
 * d'une commande Sylius payée dont les produits proviennent d'AliExpress.
 */
interface AliExpressOrderPlacementServiceInterface
{
    /**
     * Génère les commandes AliExpress pour chaque item AliExpress de la commande Sylius.
     * Retourne le nombre de commandes créées.
     */
    public function placeForOrder(OrderInterface $order): int;

    /**
     * Retente le placement d'une commande AliExpress échouée par son ID interne.
     */
    public function retry(int $aliExpressOrderId): bool;
}
