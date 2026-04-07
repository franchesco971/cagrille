<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api\Endpoint;

use Cagrille\AliExpressBundle\Contract\AliExpressApiClientInterface;
use Cagrille\AliExpressBundle\Contract\LogisticsEndpointInterface;
use Cagrille\AliExpressBundle\Dto\TrackingDto;
use Cagrille\AliExpressBundle\Dto\TrackingEventDto;

/**
 * Endpoint logistique AliExpress DS.
 *
 * Méthode API utilisée :
 *  - aliexpress.ds.trade.order.logistics.get → suivi colis
 *
 * Principe SRP : gère uniquement le suivi de livraison.
 */
class LogisticsEndpoint implements LogisticsEndpointInterface
{
    public function __construct(
        private readonly AliExpressApiClientInterface $client,
    ) {
    }

    public function getTracking(string $orderId, string $trackingNumber): TrackingDto
    {
        $data = $this->client->call('aliexpress.ds.trade.order.logistics.get', [
            'order_id' => $orderId,
        ]);

        $result = $data['aliexpress_ds_trade_order_logistics_get_response']['result'] ?? [];
        $rawEvents = $result['details']['module'] ?? [];

        $events = array_map(
            static function (array $event): TrackingEventDto {
                return new TrackingEventDto(
                    description: (string) ($event['activity_desc'] ?? ''),
                    location:    (string) ($event['activity_location'] ?? ''),
                    occurredAt:  isset($event['time'])
                        ? new \DateTimeImmutable($event['time'])
                        : new \DateTimeImmutable(),
                );
            },
            $rawEvents,
        );

        return new TrackingDto(
            trackingNumber:    $trackingNumber,
            carrier:           (string) ($result['official_website'] ?? ''),
            status:            (string) ($result['status'] ?? 'unknown'),
            events:            $events,
            estimatedDelivery: null,
        );
    }
}
