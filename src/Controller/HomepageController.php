<?php

declare(strict_types=1);

namespace App\Controller;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomepageController extends AbstractController
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {}

    #[Route('/_fragment/homepage/featured-products', name: 'cagrille_homepage_featured_products')]
    public function featuredProducts(): Response
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        $locale  = $this->localeContext->getLocaleCode();

        $products = $this->productRepository->findLatestByChannel($channel, $locale, 4);

        $rootTaxon     = $channel->getMenuTaxon();
        $rootTaxonSlug = $rootTaxon?->getSlug();

        return $this->render(
            'bundles/SyliusShopBundle/homepage/_featured_products.html.twig',
            [
                'products'       => $products,
                'rootTaxonSlug' => $rootTaxonSlug,
            ],
        );
    }
}
