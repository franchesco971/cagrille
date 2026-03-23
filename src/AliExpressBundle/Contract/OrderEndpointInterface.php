<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

use Cagrille\AliExpressBundle\Dto\OrderDto;
use Cagrille\AliExpressBundle\Dto\OrderRequestDto;

/**
 * Interface de placement et suivi des commandes AliExpress DS.
 */
interface OrderEndpointInterface
{
    /**
     * Crée une commande dropship sur AliExpress.
     */
    public function create(OrderRequestDto $request): OrderDto;

    /**
     * Récupère l'état d'une commande par son identifiant AliExpress.
     */
    public function getById(string $orderId): OrderDto;
}
