<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Service;

use Cagrille\AlibabaBundle\Contract\OrderEndpointInterface;
use Cagrille\AlibabaBundle\Dto\OrderDto;
use Cagrille\AlibabaBundle\Dto\OrderRequestDto;
use Psr\Log\LoggerInterface;

/**
 * Service métier pour la gestion des commandes fournisseurs Alibaba.
 * Principe SRP : gère uniquement le cycle de vie des commandes.
 */
class OrderService
{
    public function __construct(
        private readonly OrderEndpointInterface $orderEndpoint,
        private readonly LoggerInterface        $logger,
    ) {
    }

    /**
     * Passe une commande chez un fournisseur Alibaba.
     */
    public function placeOrder(OrderRequestDto $request): OrderDto
    {
        $this->logger->info('[Alibaba] Passage de commande fournisseur : {supplier}', [
            'supplier' => $request->supplierId,
            'items'    => count($request->items),
        ]);

        $order = $this->orderEndpoint->create($request);

        $this->logger->info('[Alibaba] Commande créée : {orderId} (statut: {status})', [
            'orderId' => $order->orderId,
            'status'  => $order->status,
        ]);

        return $order;
    }

    /**
     * Récupère l'état actuel d'une commande.
     */
    public function getOrderStatus(string $orderId): OrderDto
    {
        return $this->orderEndpoint->getById($orderId);
    }

    /**
     * Annule une commande avec un motif.
     */
    public function cancelOrder(string $orderId, string $reason): bool
    {
        $success = $this->orderEndpoint->cancel($orderId, $reason);

        $this->logger->info('[Alibaba] Annulation commande {orderId} : {result}', [
            'orderId' => $orderId,
            'result'  => $success ? 'succès' : 'échec',
        ]);

        return $success;
    }

    /**
     * Liste les commandes en attente.
     *
     * @return OrderDto[]
     */
    public function getPendingOrders(): array
    {
        return $this->orderEndpoint->list(['status' => 'pending']);
    }
}
