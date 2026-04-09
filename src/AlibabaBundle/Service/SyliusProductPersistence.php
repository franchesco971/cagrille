<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Service;

use Cagrille\AlibabaBundle\Contract\ProductPersistenceInterface;
use Cagrille\AlibabaBundle\Dto\ProductDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Persistance réelle des produits Alibaba dans Sylius.
 *
 * Crée ou met à jour un produit Sylius à partir d'un ProductDto.
 * Le code produit est préfixé "alibaba_<id>" pour éviter les conflits.
 */
class SyliusProductPersistence implements ProductPersistenceInterface
{
    /**
     * @param ProductFactoryInterface<\Sylius\Component\Core\Model\ProductInterface> $productFactory
     * @param ChannelRepositoryInterface<\Sylius\Component\Core\Model\ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ProductFactoryInterface $productFactory,
        private readonly FactoryInterface $channelPricingFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function upsert(ProductDto $dto): void
    {
        $code = 'alibaba_' . $dto->alibabaId;
        $product = $this->findOrCreate($code, $dto);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->logger->info('[Alibaba] Produit persisté dans Sylius : {code}', ['code' => $code]);
    }

    public function existsByAlibabaId(string $alibabaId): bool
    {
        return $this->entityManager
            ->getRepository(ProductInterface::class)
            ->findOneBy(['code' => 'alibaba_' . $alibabaId]) !== null;
    }

    private function findOrCreate(string $code, ProductDto $dto): ProductInterface
    {
        /** @var ProductInterface|null $product */
        $product = $this->entityManager
            ->getRepository(ProductInterface::class)
            ->findOneBy(['code' => $code]);

        if ($product === null) {
            /** @var ProductInterface $product */
            $product = $this->productFactory->createWithVariant();
            $product->setCode($code);

            $this->logger->info('[Alibaba] Création nouveau produit Sylius : {code}', ['code' => $code]);
        } else {
            $this->logger->info('[Alibaba] Mise à jour produit Sylius : {code}', ['code' => $code]);
        }

        $this->mapTranslation($product, $dto);
        $this->mapVariant($product, $dto);

        return $product;
    }

    private function mapTranslation(ProductInterface $product, ProductDto $dto): void
    {
        $product->setCurrentLocale('fr_FR');
        $product->setFallbackLocale('fr_FR');

        $name = $dto->name ?: ('Produit Alibaba ' . $dto->alibabaId);
        $product->setName($name);
        $product->setDescription($dto->description ?: null);
        $product->setSlug($this->buildSlug($dto));
        $product->setEnabled(true);
    }

    private function mapVariant(ProductInterface $product, ProductDto $dto): void
    {
        /** @var ProductVariantInterface|null $variant */
        $variant = $product->getVariants()->first() ?: null;

        if ($variant === null) {
            return; // createWithVariant() a déjà créé le variant master
        }

        $price = (int) round($dto->price * 100); // centimes

        $variant->setCode('alibaba_' . $dto->alibabaId . '_default');
        $variant->setCurrentLocale('fr_FR');

        foreach ($this->channelRepository->findAll() as $channel) {
            /** @var \Sylius\Component\Core\Model\ChannelInterface $channel */
            $channelCode = $channel->getCode();

            // Cherche un channelPricing existant pour ce canal
            $channelPricing = $variant->getChannelPricingForChannel($channel);

            if ($channelPricing === null) {
                /** @var ChannelPricingInterface $channelPricing */
                $channelPricing = $this->channelPricingFactory->createNew();
                $channelPricing->setChannelCode($channelCode);
                $variant->addChannelPricing($channelPricing);
            }

            $channelPricing->setPrice($price > 0 ? $price : 100); // 1€ minimum si pas de prix
        }
    }

    private function buildSlug(ProductDto $dto): string
    {
        $base = $dto->name ?: $dto->alibabaId;
        $slug = strtolower(trim($base));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 200);

        return $slug . '-' . $dto->alibabaId;
    }
}
