<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Api\Endpoint;

use Cagrille\AlibabaBundle\Contract\AlibabaApiClientInterface;
use Cagrille\AlibabaBundle\Contract\LogisticsEndpointInterface;
use Cagrille\AlibabaBundle\Dto\TrackingDto;

/**
 * Endpoint pour le suivi logistique des livraisons.
 * Principe SRP : gère uniquement les appels API de traçabilité.
 */
class LogisticsEndpoint implements LogisticsEndpointInterface
{
    private const ENDPOINT_TRACK_ORDER = '/alibaba.icbu.logistics.order.track';

    private const ENDPOINT_TRACK_NUMBER = '/alibaba.icbu.logistics.tracking';

    public function __construct(
        private readonly AlibabaApiClientInterface $client,
    ) {
    }

    public function trackByOrderId(string $orderId): TrackingDto
    {
        $response = $this->client->get(self::ENDPOINT_TRACK_ORDER, [
            'order_id' => $orderId,
        ]);

        return TrackingDto::fromApiResponse($response['result'] ?? $response);
    }

    public function trackByNumber(string $trackingNumber, string $carrier): TrackingDto
    {
        $response = $this->client->get(self::ENDPOINT_TRACK_NUMBER, [
            'tracking_number' => $trackingNumber,
            'carrier_code' => $carrier,
        ]);

        return TrackingDto::fromApiResponse($response['result'] ?? $response);
    }
}
