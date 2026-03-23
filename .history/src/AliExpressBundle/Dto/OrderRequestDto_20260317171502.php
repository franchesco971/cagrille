<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO de création d'une commande AliExpress DS.
 * Contient les informations nécessaires pour passer une commande dropship.
 */
final class OrderRequestDto
{
    public function __construct(
        public readonly string $syliusOrderId,
        public readonly string $productId,       // item_id AliExpress
        public readonly int    $quantity,
        public readonly string $skuAttr,         // Attributs SKU (ex: "200000182:193;200007763:201336100")
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
