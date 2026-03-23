<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface;
use Cagrille\AliExpressBundle\Dto\ProductDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Persistance réelle des produits AliExpress dans Sylius.
 *
 * Crée ou met à jour un produit Sylius à partir d'un ProductDto.
 * Le code produit est préfixé "aliexpress_<id>" pour éviter les conflits
 * avec les produits Alibaba et les produits manuels.
 *
 * Principe SRP : gère uniquement la persistence Sylius.
 * Principe DIP : implémente ProductPersistenceInterface (injectée par le container).
 */
class SyliusProductPersistence implements ProductPersistenceInterface
{
    public function __construct(
        private readonly ProductFactoryInterface    $productFactory,
        private readonly FactoryInterface           $channelPricingFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly EntityManagerInterface     $entityManager,
        private readonly LoggerInterface            $logger,
    ) {
    }

    public function upsert(ProductDto $dto): void
    {
        $code    = 'aliexpress_' . $dto->aliExpressId;
        $product = $this->findOrCreate($code, $dto);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->logger->info('[AliExpress] Produit persisté dans Sylius : {code}', ['code' => $code]);
    }

    public function existsByAliExpressId(string $aliExpressId): bool
    {
        return $this->entityManager
            ->getRepository(ProductInterface::class)
            ->findOneBy(['code' => 'aliexpress_' . $aliExpressId]) !== null;
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

            $this->logger->info('[AliExpress] Création nouveau produit Sylius : {code}', ['code' => $code]);
        } else {
            $this->logger->info('[AliExpress] Mise à jour produit Sylius : {code}', ['code' => $code]);
        }

        $this->mapTranslation($product, $dto);
        $this->mapVariant($product, $dto);

        return $product;
    }

    private function mapTranslation(ProductInterface $product, ProductDto $dto): void
    {
        $product->setCurrentLocale('fr_FR');
        $product->setFallbackLocale('fr_FR');

        $name = $dto->name ?: ('Produit AliExpress ' . $dto->aliExpressId);
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
            return;
        }

        // Le prix est en USD — on stocke en centimes (pas de conversion, utiliser un taux si besoin)
        $price = (int) round($dto->price * 100);

        $variant->setCode('aliexpress_' . $dto->aliExpressId . '_default');
        $variant->setCurrentLocale('fr_FR');

        foreach ($this->channelRepository->findAll() as $channel) {
            $channelCode    = $channel->getCode();
            $channelPricing = $variant->getChannelPricingForChannel($channel);

            if ($channelPricing === null) {
                /** @var ChannelPricingInterface $channelPricing */
                $channelPricing = $this->channelPricingFactory->createNew();
                $channelPricing->setChannelCode($channelCode);
                $variant->addChannelPricing($channelPricing);
            }

            $channelPricing->setPrice($price > 0 ? $price : 100);
        }
    }

    private function buildSlug(ProductDto $dto): string
    {
        $base = $dto->name ?: $dto->aliExpressId;
        $slug = strtolower(trim($base));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 200);

        return $slug . '-ae-' . $dto->aliExpressId;
    }
}
