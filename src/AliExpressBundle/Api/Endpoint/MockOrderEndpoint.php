<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api\Endpoint;

use Cagrille\AliExpressBundle\Contract\OrderEndpointInterface;
use Cagrille\AliExpressBundle\Dto\OrderDto;
use Cagrille\AliExpressBundle\Dto\OrderRequestDto;
use Psr\Log\LoggerInterface;

/**
 * Implémentation factice de OrderEndpointInterface pour l'environnement de développement.
 *
 * Simule une réponse réussie de l'API AliExpress DS sans aucun appel réseau.
 * Activé automatiquement en env `dev` via services.yaml (when@dev).
 */
final class MockOrderEndpoint implements OrderEndpointInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(OrderRequestDto $request): OrderDto
    {
        $mockOrderId = 'MOCK-' . strtoupper(substr(md5($request->syliusOrderId . microtime()), 0, 10));

        $this->logger->info('[AliExpress][MOCK] Commande simulée créée : {orderId} pour Sylius#{syliusId} ({count} article(s))', [
            'orderId'   => $mockOrderId,
            'syliusId'  => $request->syliusOrderId,
            'count'     => count($request->items),
        ]);

        return new OrderDto(
            aliExpressOrderId: $mockOrderId,
            syliusOrderId:     $request->syliusOrderId,
            status:            'PLACE_ORDER_SUCCESS',
            totalAmount:       9.99 * count($request->items),
            currency:          'USD',
            trackingNumber:    '',
            carrier:           '',
            createdAt:         new \DateTimeImmutable(),
        );
    }

    public function getById(string $orderId): OrderDto
    {
        $this->logger->info('[AliExpress][MOCK] Consultation commande simulée : {orderId}', [
            'orderId' => $orderId,
        ]);

        return new OrderDto(
            aliExpressOrderId: $orderId,
            syliusOrderId:     '',
            status:            'PLACE_ORDER_SUCCESS',
            totalAmount:       9.99,
            currency:          'USD',
            trackingNumber:    '',
            carrier:           '',
            createdAt:         new \DateTimeImmutable(),
        );
    }
}
