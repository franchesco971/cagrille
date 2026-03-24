<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TaxonMenuExtension extends AbstractExtension
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cagrille_menu_taxons', $this->getMenuTaxons(...)),
        ];
    }

    /** @return TaxonInterface[] */
    public function getMenuTaxons(): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->taxonRepository->findChildrenByChannelMenuTaxon(
            $channel->getMenuTaxon(),
            $this->localeContext->getLocaleCode(),
        );
    }
}
