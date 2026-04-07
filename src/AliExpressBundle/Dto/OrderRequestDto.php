<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO de création d'une commande AliExpress DS groupée.
 * Un seul appel API peut contenir plusieurs articles (product_items).
 */
final class OrderRequestDto
{
    /**
     * @param OrderItemDto[] $items
     */
    public function __construct(
        public readonly string $syliusOrderId,
        public readonly array $items,           // Liste des articles à commander
        public readonly string $shippingAddress,
        public readonly string $recipientName,
        public readonly string $recipientPhone,
        public readonly string $country,         // Code ISO 2 lettres (ex: "FR")
        public readonly string $city,
        public readonly string $zipCode,
        public readonly string $logisticsService, // ex: "CAINIAO_STANDARD"
    ) {
    }
}
