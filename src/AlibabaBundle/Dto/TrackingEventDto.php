<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Dto;

/**
 * Représente un événement de suivi de colis.
 */
final class TrackingEventDto
{
    public function __construct(
        public readonly string $description,
        public readonly string $location,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
