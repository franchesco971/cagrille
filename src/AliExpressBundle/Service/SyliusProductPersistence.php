<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface;
use Cagrille\AliExpressBundle\Dto\ProductDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use App\Entity\Product\ProductImage;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\File\File;

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
    /**
     * @param ProductFactoryInterface<\Sylius\Component\Core\Model\ProductInterface> $productFactory
     * @param ChannelRepositoryInterface<\Sylius\Component\Core\Model\ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ProductFactoryInterface    $productFactory,
        private readonly FactoryInterface           $channelPricingFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly EntityManagerInterface     $entityManager,
        private readonly ImageUploaderInterface     $imageUploader,
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
        $this->mapImages($product, $dto);

        return $product;
    }

    private function mapTranslation(ProductInterface $product, ProductDto $dto): void
    {
        $locale = 'fr_FR';

        // Récupère la traduction existante ou en crée une nouvelle explicitement.
        // On passe par getTranslation() qui gère le cache interne de Sylius
        // (TranslatableTrait) et crée le ProductTranslation si absent.
        /** @var ProductTranslationInterface $translation */
        $translation = $product->getTranslation($locale);

        // Garantit que la locale est bien positionnée (cas d'une nouvelle traduction).
        if ($translation->getLocale() === null) {
            $translation->setLocale($locale);
        }

        $name = $dto->name ?: ('Produit AliExpress ' . $dto->aliExpressId);

        $translation->setName($name);
        $translation->setSlug($this->buildSlug($dto));
        $translation->setDescription($this->buildDescription($dto));
        $translation->setShortDescription($this->buildShortDescription($dto));

        $product->setCurrentLocale($locale);
        $product->setFallbackLocale($locale);
        $product->setEnabled(true);
    }

    /**
     * Description complète : contenu HTML de l'API, nettoyé des balises script/style
     * pour sécuriser le rendu Twig avec |raw.
     */
    private function buildDescription(ProductDto $dto): ?string
    {
        if ($dto->description === '') {
            return null;
        }

        return strip_tags($dto->description, '<h1><h2><h3><p><ul><ol><li><br><strong><em><img><a>');
    }

    /**
     * Description courte : texte brut (sans balises) tronqué à 255 caractères.
     * Utilisée dans les listings produits de Sylius.
     */
    private function buildShortDescription(ProductDto $dto): ?string
    {
        if ($dto->description === '') {
            return null;
        }

        $plain = strip_tags($dto->description);
        $plain = preg_replace('/\s+/', ' ', trim($plain)) ?? '';

        return mb_substr($plain, 0, 255);
    }

    /**
     * Synchronise les images du produit AliExpress.
     *
     * - Supprime les ProductImage de type "aliexpress_*" déjà attachées
     *   (orphan-removal Doctrine = DELETE automatique en base).
     * - Télécharge chaque URL dans un fichier temporaire.
     * - Délègue à ImageUploaderInterface (sylius.uploader.image) qui :
     *     génère un hash aléatoire (ex: ab/cd/abcd1234.jpeg),
     *     écrit le fichier dans Flysystem (public/media/image/),
     *     positionne image->path avec ce chemin relatif.
     * - La première image est de type "aliexpress_main", les suivantes "aliexpress_thumbnail".
     */
    private function mapImages(ProductInterface $product, ProductDto $dto): void
    {
        if (empty($dto->images)) {
            return;
        }

        // Supprime toutes les images AliExpress précédentes (evite les doublons en re-import)
        foreach ($product->getImages() as $existing) {
            if (str_starts_with((string) $existing->getType(), 'aliexpress_')) {
                $product->removeImage($existing);
            }
        }

        foreach (array_values($dto->images) as $position => $url) {
            $tmpPath = $this->downloadToTempFile((string) $url);

            if ($tmpPath === null) {
                $this->logger->warning('[AliExpress] Image non téléchargeable : {url}', ['url' => $url]);
                continue;
            }

            try {
                $image = new ProductImage();
                $image->setFile(new File($tmpPath));
                $image->setType($position === 0 ? 'aliexpress_main' : 'aliexpress_thumbnail');

                $this->imageUploader->upload($image);
                $product->addImage($image);

                $this->logger->debug('[AliExpress] Image persistée : {path}', ['path' => $image->getPath()]);
            } finally {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Télécharge une URL distante dans un fichier temporaire.
     * Retourne le chemin du fichier ou null en cas d'échec.
     */
    private function downloadToTempFile(string $url): ?string
    {
        $content = @file_get_contents($url);

        if ($content === false || $content === '') {
            return null;
        }

        $ext     = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $tmpPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.' . $ext;

        file_put_contents($tmpPath, $content);

        return $tmpPath;
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
            /** @var \Sylius\Component\Core\Model\ChannelInterface $channel */
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
