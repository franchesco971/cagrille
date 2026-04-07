<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO représentant une commande AliExpress DS (en lecture).
 * Immutable : reflète l'état retourné par l'API.
 */
final class OrderDto
{
    public function __construct(
        public readonly string $aliExpressOrderId,
        public readonly string $syliusOrderId,
        public readonly string $status,           // ex: "PLACE_ORDER_SUCCESS"
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly string $trackingNumber,
        public readonly string $carrier,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromApiResponse(array $data): self
    {
        $result = $data['result'] ?? $data;

        return new self(
            aliExpressOrderId: (string) ($result['order_id'] ?? ''),
            syliusOrderId:     '',
            status:            (string) ($result['order_status'] ?? ''),
            totalAmount:       (float) ($result['amount'] ?? 0.0),
            currency:          (string) ($result['currency_code'] ?? 'USD'),
            trackingNumber:    (string) ($result['logistics_no'] ?? ''),
            carrier:           (string) ($result['logistics_service_name'] ?? ''),
            createdAt:         new \DateTimeImmutable(),
        );
    }
}
