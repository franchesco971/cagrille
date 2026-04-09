<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Dto;

/**
 * DTO représentant une commande Alibaba (réponse API).
 */
final class OrderDto
{
    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly string $supplierId,
        public readonly array $items,
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly ?string $trackingNumber,
        public readonly ?string $carrier,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $estimatedDelivery,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            orderId: (string) ($data['order_id'] ?? ''),
            status: (string) ($data['order_status'] ?? 'unknown'),
            supplierId: (string) ($data['supplier_id'] ?? ''),
            items: (array) ($data['product_items'] ?? []),
            totalAmount: (float) ($data['total_amount']['value'] ?? 0.0),
            currency: (string) ($data['total_amount']['currency_code'] ?? 'USD'),
            trackingNumber: $data['logistics']['tracking_number'] ?? null,
            carrier: $data['logistics']['carrier'] ?? null,
            createdAt: isset($data['created_date'])
                ? new \DateTimeImmutable($data['created_date'])
                : new \DateTimeImmutable(),
            estimatedDelivery: isset($data['estimated_delivery_date'])
                ? new \DateTimeImmutable($data['estimated_delivery_date'])
                : null,
        );
    }
}
