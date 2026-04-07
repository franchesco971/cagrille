<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * Représente un article dans une commande AliExpress DS groupée.
 */
final class OrderItemDto
{
    public function __construct(
        public readonly string $productId,
        public readonly int    $quantity,
        public readonly string $skuAttr,
    ) {
    }
}
