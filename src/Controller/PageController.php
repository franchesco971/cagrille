<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/{_locale}/mentions-legales', name: 'cagrille_page_mentions_legales', requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])]
    public function mentionsLegales(): Response
    {
        return $this->render('page/mentions-legales.html.twig');
    }
}
