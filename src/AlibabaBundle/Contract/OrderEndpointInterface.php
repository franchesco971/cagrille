<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Contract;

use Cagrille\AlibabaBundle\Dto\OrderDto;
use Cagrille\AlibabaBundle\Dto\OrderRequestDto;

/**
 * Contrat pour la gestion des commandes via l'API Alibaba.
 * Principe ISP : focalisé uniquement sur les opérations de commande.
 */
interface OrderEndpointInterface
{
    /**
     * Crée une commande chez le fournisseur Alibaba.
     */
    public function create(OrderRequestDto $orderRequest): OrderDto;

    /**
     * Récupère les détails d'une commande existante.
     */
    public function getById(string $orderId): OrderDto;

    /**
     * Liste les commandes avec filtres optionnels.
     *
     * @param array $filters  Ex: ['status' => 'pending', 'from_date' => '2024-01-01']
     * @return OrderDto[]
     */
    public function list(array $filters = [], int $page = 1, int $pageSize = 20): array;

    /**
     * Annule une commande.
     */
    public function cancel(string $orderId, string $reason): bool;
}
