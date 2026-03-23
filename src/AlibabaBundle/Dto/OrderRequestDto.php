<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Dto;

/**
 * DTO pour créer une commande chez un fournisseur Alibaba.
 */
final class OrderRequestDto
{
    public function __construct(
        public readonly string $supplierId,
        public readonly array  $items,          // [['product_id' => '...', 'quantity' => 5], ...]
        public readonly array  $shippingAddress,
        public readonly string $shippingMethod  = 'standard',
        public readonly string $buyerMessage    = '',
    ) {
    }

    public function toApiPayload(): array
    {
        return [
            'supplier_id'      => $this->supplierId,
            'product_items'    => $this->items,
            'delivery_address' => $this->shippingAddress,
            'shipping_method'  => $this->shippingMethod,
            'buyer_message'    => $this->buyerMessage,
        ];
    }
}
