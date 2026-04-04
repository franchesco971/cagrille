<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Twig;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Cagrille\AliExpressBundle\Entity\AliExpressOrder;
use Cagrille\AliExpressBundle\Entity\AliExpressOrderStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose les données des commandes AliExpress dans les templates Twig.
 *
 * Nécessaire car le système de hooks Sylius rend les templates de contenu
 * dans un contexte isolé, sans accès aux variables du contrôleur.
 */
final class AliExpressOrderExtension extends AbstractExtension
{
    private const DEFAULT_PAGE_SIZE = 20;

    public function __construct(
        private readonly AliExpressOrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('aliexpress_order_stats', $this->getOrderStats(...)),
            new TwigFunction('aliexpress_orders_paginated', $this->getOrdersPaginated(...)),
            new TwigFunction('aliexpress_order_find', $this->findOrder(...)),
        ];
    }

    /**
     * @return array{total: int, pending: int, placed: int, failed: int, shipped: int, delivered: int}
     */
    public function getOrderStats(): array
    {
        return [
            'total' => $this->orderRepository->countAll(),
            'pending' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Pending),
            'placed' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Placed),
            'failed' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Failed),
            'shipped' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Shipped),
            'delivered' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Delivered),
        ];
    }

    /**
     * @return array{orders: AliExpressOrder[], page: int, lastPage: int, total: int}
     */
    public function getOrdersPaginated(int $page = 1, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        $page = max(1, $page);
        $total = $this->orderRepository->countAll();
        $orders = $this->orderRepository->findPaginated($page, $limit);
        $lastPage = (int) ceil($total / $limit) ?: 1;

        return [
            'orders' => $orders,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ];
    }

    public function findOrder(int $id): ?AliExpressOrder
    {
        return $this->orderRepository->find($id);
    }
}
