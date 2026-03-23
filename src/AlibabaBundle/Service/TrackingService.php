<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Service;

use Cagrille\AlibabaBundle\Contract\LogisticsEndpointInterface;
use Cagrille\AlibabaBundle\Dto\TrackingDto;
use Psr\Log\LoggerInterface;

/**
 * Service de suivi de livraisons via l'API Alibaba.
 * Principe SRP : gère uniquement la traçabilité logistique.
 */
class TrackingService
{
    public function __construct(
        private readonly LogisticsEndpointInterface $logisticsEndpoint,
        private readonly LoggerInterface            $logger,
    ) {
    }

    /**
     * Retourne le suivi complet pour une commande.
     */
    public function getTrackingForOrder(string $orderId): TrackingDto
    {
        $this->logger->debug('[Alibaba] Récupération suivi pour commande {orderId}', [
            'orderId' => $orderId,
        ]);

        return $this->logisticsEndpoint->trackByOrderId($orderId);
    }

    /**
     * Retourne le suivi par numéro de colis et transporteur.
     */
    public function getTrackingByParcel(string $trackingNumber, string $carrier): TrackingDto
    {
        return $this->logisticsEndpoint->trackByNumber($trackingNumber, $carrier);
    }

    /**
     * Vérifie si une commande est livrée.
     */
    public function isDelivered(string $orderId): bool
    {
        $tracking = $this->getTrackingForOrder($orderId);

        return strtolower($tracking->status) === 'delivered';
    }
}
