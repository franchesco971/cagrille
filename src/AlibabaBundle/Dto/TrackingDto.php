<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Dto;

/**
 * DTO pour les informations de suivi logistique.
 */
final class TrackingDto
{
    /** @param TrackingEventDto[] $events */
    public function __construct(
        public readonly string  $orderId,
        public readonly string  $trackingNumber,
        public readonly string  $carrier,
        public readonly string  $status,
        public readonly string  $currentLocation,
        public readonly array   $events,
        public readonly ?\DateTimeImmutable $estimatedDelivery,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromApiResponse(array $data): self
    {
        $events = array_map(
            static fn(array $e) => new TrackingEventDto(
                description: (string) ($e['description'] ?? ''),
                location: (string) ($e['location'] ?? ''),
                occurredAt: isset($e['time']) ? new \DateTimeImmutable($e['time']) : new \DateTimeImmutable(),
            ),
            (array) ($data['events'] ?? [])
        );

        return new self(
            orderId: (string) ($data['order_id'] ?? ''),
            trackingNumber: (string) ($data['tracking_number'] ?? ''),
            carrier: (string) ($data['carrier_name'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            currentLocation: (string) ($data['current_location'] ?? ''),
            events: $events,
            estimatedDelivery: isset($data['estimated_delivery'])
                ? new \DateTimeImmutable($data['estimated_delivery'])
                : null,
        );
    }
}
