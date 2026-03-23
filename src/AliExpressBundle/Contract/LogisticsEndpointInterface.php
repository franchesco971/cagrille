<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Cagrille\AliExpressBundle\Dto\TrackingDto;

/**
 * Interface de suivi logistique des colis AliExpress.
 */
interface LogisticsEndpointInterface
{
    /**
     * Récupère les informations de tracking pour une commande et son colis.
     *
     * @param string $orderId     Identifiant commande AliExpress
     * @param string $trackingNumber Numéro de suivi transporteur
     */
    public function getTracking(string $orderId, string $trackingNumber): TrackingDto;
}
