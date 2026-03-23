<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api\Endpoint;

use Cagrille\AliExpressBundle\Contract\OrderEndpointInterface;
use Cagrille\AliExpressBundle\Contract\AliExpressApiClientInterface;
use Cagrille\AliExpressBundle\Dto\OrderDto;
use Cagrille\AliExpressBundle\Dto\OrderRequestDto;

/**
 * Endpoint commandes AliExpress DS.
 *
 * Méthodes API utilisées :
 *  - aliexpress.ds.order.create → création commande dropship
 *  - aliexpress.ds.order.query  → consultation d'une commande
 *
 * Principe SRP : gère uniquement le cycle de vie des commandes.
 */
class OrderEndpoint implements OrderEndpointInterface
{
    public function __construct(
        private readonly AliExpressApiClientInterface $client,
    ) {
    }

    public function create(OrderRequestDto $request): OrderDto
    {
        $data = $this->client->call('aliexpress.ds.order.create', [
            'product_items' => json_encode([[
                'product_id'   => $request->productId,
                'product_count' => $request->quantity,
                'sku_attr'     => $request->skuAttr,
            ]]),
            'logistics_service_name' => $request->logisticsService,
            'international_transport_mode' => 'AIR',
            'out_order_id'   => $request->syliusOrderId,
            'address'        => json_encode([
                'contact_person'   => $request->recipientName,
                'mobile_no'        => $request->recipientPhone,
                'address'          => $request->shippingAddress,
                'city'             => $request->city,
                'zip'              => $request->zipCode,
                'country'          => $request->country,
            ]),
        ]);

        return OrderDto::fromApiResponse($data['aliexpress_ds_order_create_response'] ?? $data);
    }

    public function getById(string $orderId): OrderDto
    {
        $data = $this->client->call('aliexpress.ds.order.query', [
            'order_id' => $orderId,
        ]);

        return OrderDto::fromApiResponse($data['aliexpress_ds_order_query_response']['result'] ?? $data);
    }
}
