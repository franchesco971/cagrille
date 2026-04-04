<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Cagrille\AliExpressBundle\Entity\AliExpressOrder;

/**
 * Interface de synchronisation des informations de livraison AliExpress
 * vers les expéditions Sylius.
 *
 * Responsabilité unique : mettre à jour le tracking number sur les Shipments
 * Sylius et synchroniser le statut logistique depuis l'API AliExpress.
 */
interface AliExpressShipmentSyncServiceInterface
{
    /**
     * Synchronise le tracking d'une commande AliExpress depuis l'API,
     * puis met à jour le Shipment Sylius correspondant.
     */
    public function syncTracking(AliExpressOrder $aliExpressOrder): void;

    /**
     * Synchronise le tracking pour toutes les commandes placées sans suivi.
     * Retourne le nombre de commandes traitées.
     */
    public function syncAllPendingTracking(): int;
}
