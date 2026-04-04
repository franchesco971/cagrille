<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Controller\Admin;

use Cagrille\AliExpressBundle\Contract\AliExpressOrderPlacementServiceInterface;
use Cagrille\AliExpressBundle\Contract\AliExpressOrderRepositoryInterface;
use Cagrille\AliExpressBundle\Entity\AliExpressOrderStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur admin pour la gestion des commandes AliExpress.
 *
 * Permet : lister, voir le détail, relancer une commande échouée.
 * Principe SRP : gère uniquement la couche HTTP — délègue au service métier.
 */
#[Route('/aliexpress-orders', name: 'cagrille_admin_aliexpress_order_')]
final class AliExpressOrderController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly AliExpressOrderRepositoryInterface $orderRepository,
        private readonly AliExpressOrderPlacementServiceInterface $placementService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $total = $this->orderRepository->countAll();
        $orders = $this->orderRepository->findPaginated($page, self::PAGE_SIZE);
        $lastPage = (int) ceil($total / self::PAGE_SIZE) ?: 1;

        $stats = [
            'total' => $total,
            'pending' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Pending),
            'placed' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Placed),
            'failed' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Failed),
            'shipped' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Shipped),
            'delivered' => $this->orderRepository->countByStatus(AliExpressOrderStatus::Delivered),
        ];

        return $this->render('admin/aliexpress_order/index.html.twig', [
            'orders' => $orders,
            'stats' => $stats,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $order = $this->orderRepository->find($id);

        if ($order === null) {
            throw $this->createNotFoundException(sprintf('Commande AliExpress #%d introuvable.', $id));
        }

        return $this->render('admin/aliexpress_order/show.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * Relance une commande AliExpress échouée.
     */
    #[Route('/{id}/retry', name: 'retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('aliexpress_order_retry_' . $id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('cagrille_admin_aliexpress_order_show', ['id' => $id]);
        }

        $success = $this->placementService->retry($id);

        if ($success) {
            $this->addFlash('success', sprintf('Commande AliExpress #%d relancée avec succès.', $id));
        } else {
            $order = $this->orderRepository->find($id);
            $error = $order?->getErrorMessage() ?? 'Erreur inconnue.';
            $this->addFlash('error', sprintf('Échec de la relance : %s', $error));
        }

        return $this->redirectToRoute('cagrille_admin_aliexpress_order_show', ['id' => $id]);
    }
}
