<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Controller\Admin;

use Cagrille\AliExpressBundle\Api\TokenRefreshService;
use Cagrille\AliExpressBundle\Contract\ProductSyncServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/aliexpress-sync', name: 'cagrille_admin_aliexpress_sync_')]
final class AliExpressSyncController extends AbstractController
{
    public function __construct(
        private readonly ProductSyncServiceInterface $syncService,
        private readonly TokenRefreshService $tokenRefreshService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/aliexpress_sync/index.html.twig');
    }

    // ── OAuth2 ──────────────────────────────────────────────────────────────

    /**
     * Étape 1 : redirige l'utilisateur vers AliExpress pour l'autorisation OAuth.
     */
    #[Route('/auth', name: 'auth_start', methods: ['GET'])]
    public function authStart(): Response
    {
        $callbackUrl = $this->generateUrl(
            'cagrille_admin_aliexpress_sync_auth_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $authUrl = $this->tokenRefreshService->getAuthorizationUrl($callbackUrl);

        return $this->redirect($authUrl);
    }

    /**
     * Étape 2 : AliExpress redirige ici avec le code d'autorisation.
     * Échange le code contre un access_token + refresh_token.
     */
    #[Route('/auth/callback', name: 'auth_callback', methods: ['GET'])]
    public function authCallback(Request $request): Response
    {
        $code = $request->query->getString('code');

        if ($code === '') {
            $error = $request->query->getString('error');
            $this->addFlash('error', sprintf('AliExpress OAuth : erreur de callback — %s', $error !== '' ? $error : 'code manquant'));

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        try {
            $this->tokenRefreshService->exchangeCode($code);
            $this->addFlash('success', 'Token AliExpress obtenu et sauvegardé avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'échange du code OAuth : ' . $e->getMessage());
        }

        return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
    }

    /**
     * Rafraîchit manuellement le token via le refresh_token.
     */
    #[Route('/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function authRefresh(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('aliexpress_auth_refresh', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        try {
            $this->tokenRefreshService->refresh();
            $this->addFlash('success', 'Token AliExpress rafraîchi avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible de rafraîchir le token : ' . $e->getMessage());
        }

        return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
    }

    // ── Synchronisation ─────────────────────────────────────────────────────

    #[Route('/import-one', name: 'import_one', methods: ['POST'])]
    public function importOne(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('aliexpress_import_one', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        $itemId = trim($request->request->getString('item_id'));

        if ($itemId === '') {
            $this->addFlash('error', 'L\'identifiant du produit est requis.');

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
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        $keyword = trim($request->request->getString('keyword'));

        if ($keyword === '') {
            $this->addFlash('error', 'Le mot-clé est requis.');

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
            $this->addFlash('error', 'Token CSRF invalide.');

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
