<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Contract;

use Cagrille\AlibabaBundle\Dto\TrackingDto;

/**
 * Contrat pour le suivi logistique des livraisons.
 * Principe ISP : séparé, focalisé sur la traçabilité.
 */
interface LogisticsEndpointInterface
{
    /**
     * Récupère les informations de suivi pour un numéro de commande.
     */
    public function trackByOrderId(string $orderId): TrackingDto;

    /**
     * Récupère les informations de suivi par numéro de tracking transporteur.
     */
    public function trackByNumber(string $trackingNumber, string $carrier): TrackingDto;
}
