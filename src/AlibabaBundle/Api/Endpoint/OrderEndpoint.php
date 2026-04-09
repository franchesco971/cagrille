<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Api\Endpoint;

use Cagrille\AlibabaBundle\Contract\AlibabaApiClientInterface;
use Cagrille\AlibabaBundle\Contract\OrderEndpointInterface;
use Cagrille\AlibabaBundle\Dto\OrderDto;
use Cagrille\AlibabaBundle\Dto\OrderRequestDto;

/**
 * Endpoint pour la gestion des commandes via l'API Alibaba.
 * Principe SRP : gère uniquement les opérations commandes.
 */
class OrderEndpoint implements OrderEndpointInterface
{
    private const ENDPOINT_CREATE = '/alibaba.icbu.order.create';

    private const ENDPOINT_GET = '/alibaba.icbu.order.get';

    private const ENDPOINT_LIST = '/alibaba.icbu.order.list';

    private const ENDPOINT_CANCEL = '/alibaba.icbu.order.cancel';

    public function __construct(
        private readonly AlibabaApiClientInterface $client,
    ) {
    }

    public function create(OrderRequestDto $orderRequest): OrderDto
    {
        $response = $this->client->post(self::ENDPOINT_CREATE, $orderRequest->toApiPayload());

        return OrderDto::fromApiResponse($response['result'] ?? $response);
    }

    public function getById(string $orderId): OrderDto
    {
        $response = $this->client->get(self::ENDPOINT_GET, [
            'order_id' => $orderId,
        ]);

        return OrderDto::fromApiResponse($response['result'] ?? $response);
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function list(array $filters = [], int $page = 1, int $pageSize = 20): array
    {
        $params = array_merge($filters, [
            'page_no' => $page,
            'page_size' => min($pageSize, 50),
        ]);

        $response = $this->client->get(self::ENDPOINT_LIST, $params);

        $orders = $response['result']['order_list'] ?? [];

        return array_map(
            static fn (array $order) => OrderDto::fromApiResponse($order),
            $orders,
        );
    }

    public function cancel(string $orderId, string $reason): bool
    {
        $response = $this->client->post(self::ENDPOINT_CANCEL, [
            'order_id' => $orderId,
            'reason' => $reason,
        ]);

        return (bool) ($response['result']['success'] ?? false);
    }
}
