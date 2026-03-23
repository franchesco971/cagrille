<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO représentant un événement de tracking (étape de livraison).
 */
final class TrackingEventDto
{
    public function __construct(
        public readonly string             $description,
        public readonly string             $location,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
