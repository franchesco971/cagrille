<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Controller\Admin;

use Cagrille\AliExpressBundle\Contract\ProductSyncServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/aliexpress-sync', name: 'cagrille_admin_aliexpress_sync_')]
final class AliExpressSyncController extends AbstractController
{
    public function __construct(
        private readonly ProductSyncServiceInterface $syncService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/aliexpress_sync/index.html.twig');
    }

    #[Route('/import-one', name: 'import_one', methods: ['POST'])]
    public function importOne(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('aliexpress_import_one', $request->request->getString('_token'))) {
            $this->addFlash('error', 'sylius.ui.invalid_csrf_token');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        $itemId = trim($request->request->getString('item_id'));

        if ($itemId === '') {
            $this->addFlash('error', 'cagrille.aliexpress_sync.item_id_required');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        try {
            $this->syncService->importOne($itemId);
            $this->addFlash('success', sprintf('Produit AliExpress "%s" importé avec succès.', $itemId));
        } catch (\Throwable $e) {
            $this->addFlash('error', sprintf('Erreur lors de l\'import du produit "%s" : %s', $itemId, $e->getMessage()));
        }

        return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
    }

    #[Route('/sync-keyword', name: 'sync_keyword', methods: ['POST'])]
    public function syncKeyword(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('aliexpress_sync_keyword', $request->request->getString('_token'))) {
            $this->addFlash('error', 'sylius.ui.invalid_csrf_token');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        $keyword = trim($request->request->getString('keyword'));

        if ($keyword === '') {
            $this->addFlash('error', 'cagrille.aliexpress_sync.keyword_required');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        try {
            $count = $this->syncService->syncByKeyword($keyword);
            $this->addFlash('success', sprintf('%d produits synchronisés pour le mot-clé "%s".', $count, $keyword));
        } catch (\Throwable $e) {
            $this->addFlash('error', sprintf('Erreur lors de la synchronisation par mot-clé "%s" : %s', $keyword, $e->getMessage()));
        }

        return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
    }

    #[Route('/sync-all', name: 'sync_all', methods: ['POST'])]
    public function syncAll(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('aliexpress_sync_all', $request->request->getString('_token'))) {
            $this->addFlash('error', 'sylius.ui.invalid_csrf_token');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        try {
            $count = $this->syncService->syncAll();
            $this->addFlash('success', sprintf('%d produits synchronisés au total (tous les mots-clés).', $count));
        } catch (\Throwable $e) {
            $this->addFlash('error', sprintf('Erreur lors de la synchronisation complète : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
    }
}
