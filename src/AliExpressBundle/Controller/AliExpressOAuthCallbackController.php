<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Controller;

use Cagrille\AliExpressBundle\Api\TokenRefreshService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reçoit le callback OAuth AliExpress à l'URL publique configurée dans les
 * paramètres de l'application AliExpress Open Platform.
 *
 * URL enregistrée chez AliExpress : https://cagrille.fr/aliexpress/callback
 *
 * Flux :
 *   AliExpress → GET /aliexpress/callback?code=XXX
 *     → échange du code → token sauvegardé
 *     → redirection vers le panneau admin AliExpress
 *   En cas d'erreur dans le callback (ex. accès refusé) :
 *     → flash error + redirection admin
 */
#[Route('/aliexpress/callback', name: 'cagrille_aliexpress_oauth_callback', methods: ['GET'])]
#[IsGranted('ROLE_ADMINISTRATION_ACCESS')]
final class AliExpressOAuthCallbackController extends AbstractController
{
    public function __construct(
        private readonly TokenRefreshService $tokenRefreshService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $error = $request->query->getString('error');

        if ($error !== '') {
            $description = $request->query->getString('error_description', 'Autorisation refusée');
            $this->addFlash('error', sprintf('AliExpress OAuth — accès refusé : %s', $description));

            return $this->redirectToRoute('cagrille_admin_aliexpress_sync_index');
        }

        $code = $request->query->getString('code');

        if ($code === '') {
            $this->addFlash('error', 'AliExpress OAuth — paramètre "code" manquant dans le callback.');

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
}
