<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO de suivi logistique d'un colis AliExpress.
 */
final class TrackingDto
{
    /**
     * @param TrackingEventDto[] $events
     */
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $carrier,
        public readonly string $status,
        public readonly array $events,
        public readonly ?\DateTimeImmutable $estimatedDelivery,
    ) {
    }
}
