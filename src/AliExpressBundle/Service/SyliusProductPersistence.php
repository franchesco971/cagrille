<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

use App\Entity\Product\ProductImage;
use App\Entity\Product\ProductVariant as AppProductVariant;
use Cagrille\AliExpressBundle\Contract\ProductPersistenceInterface;
use Cagrille\AliExpressBundle\Dto\ProductDto;
use Cagrille\AliExpressBundle\Dto\SkuDto;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Persistance réelle des produits AliExpress dans Sylius.
 *
 * Crée ou met à jour un produit Sylius à partir d'un ProductDto.
 * Le code produit est préfixé "aliexpress_<id>" pour éviter les conflits
 * avec les produits Alibaba et les produits manuels.
 *
 * Variants :
 *   - Un ProductVariant est créé pour chaque SKU (ae_item_sku_info_d_t_o).
 *   - Si aucun SKU n'est disponible, un variant par défaut est créé.
 *   - Les options/valeurs produit Sylius (ProductOption / ProductOptionValue)
 *     sont créées ou réutilisées si elles existent déjà.
 *   - Pas de suivi de stock (setTracked(false)).
 *   - La catégorie de taxe est la première trouvée en base.
 *
 * Tarification canal :
 *   - Déléguée à ChannelPricingCalculator (formule configurable via env vars).
 *
 * Principe SRP : gère uniquement la persistence Sylius.
 * Principe DIP : implémente ProductPersistenceInterface (injectée par le container).
 */
class SyliusProductPersistence implements ProductPersistenceInterface
{
    /**
     * Cache des ProductOptionValue créées/trouvées pendant le upsert en cours.
     * Évite les doublons quand deux SKUs partagent la même valeur avant le flush().
     *
     * @var array<string, ProductOptionValueInterface>
     */
    private array $pendingOptionValues = [];

    /**
     * @param ProductFactoryInterface<\Sylius\Component\Core\Model\ProductInterface> $productFactory
     * @param ChannelRepositoryInterface<\Sylius\Component\Core\Model\ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ProductFactoryInterface $productFactory,
        private readonly FactoryInterface $channelPricingFactory,
        private readonly FactoryInterface $productVariantFactory,
        private readonly FactoryInterface $productOptionFactory,
        private readonly FactoryInterface $productOptionValueFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageUploaderInterface $imageUploader,
        private readonly ChannelPricingCalculator $pricingCalculator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function upsert(ProductDto $dto): void
    {
        $this->pendingOptionValues = [];
        $code = 'aliexpress_' . $dto->aliExpressId;
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
            $product = $this->productFactory->createNew();
            $product->setCode($code);

            $this->logger->info('[AliExpress] Création nouveau produit Sylius : {code}', ['code' => $code]);
        } else {
            $this->logger->info('[AliExpress] Mise à jour produit Sylius : {code}', ['code' => $code]);
        }

        $this->mapTranslation($product, $dto);
        $this->mapVariants($product, $dto);
        $this->mapImages($product, $dto);

        return $product;
    }

    // ── Traductions ──────────────────────────────────────────────────────────

    private function mapTranslation(ProductInterface $product, ProductDto $dto): void
    {
        $name = $dto->name ?: ('Produit AliExpress ' . $dto->aliExpressId);
        $slug = $this->buildSlug($dto);
        $description = $this->buildDescription($dto);
        $shortDescription = $this->buildShortDescription($dto);

        foreach (['fr_FR', 'en_US'] as $locale) {
            /** @var ProductTranslationInterface $translation */
            $translation = $product->getTranslation($locale);

            if ($translation->getLocale() === null) {
                $translation->setLocale($locale);
            }

            $translation->setName($name);
            $translation->setSlug($slug);
            $translation->setDescription($description);
            $translation->setShortDescription($shortDescription);
        }

        $product->setCurrentLocale('fr_FR');
        $product->setFallbackLocale('fr_FR');
        $product->setEnabled(true);
    }

    /**
     * Description complète : texte brut sans aucune balise HTML.
     */
    private function buildDescription(ProductDto $dto): ?string
    {
        if ($dto->description === '') {
            return null;
        }

        $plain = html_entity_decode(strip_tags($dto->description), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/', ' ', trim($plain)) ?: null;
    }

    /**
     * Description courte : texte brut tronqué à 255 caractères.
     */
    private function buildShortDescription(ProductDto $dto): ?string
    {
        $plain = $this->buildDescription($dto);

        return $plain !== null ? mb_substr($plain, 0, 255) : null;
    }

    // ── Variants ─────────────────────────────────────────────────────────────

    /**
     * Crée ou met à jour un ProductVariant Sylius par SKU AliExpress.
     * Si aucun SKU n'est disponible, crée un variant par défaut.
     */
    private function mapVariants(ProductInterface $product, ProductDto $dto): void
    {
        $taxCategory = $this->findFirstTaxCategory();

        if ($dto->skus === []) {
            // Pas de données SKU : variant par défaut unique (cas affiliate search)
            $this->upsertDefaultVariant($product, $dto, $taxCategory);

            return;
        }

        foreach ($dto->skus as $skuDto) {
            $this->upsertVariant($product, $dto->aliExpressId, $skuDto, $taxCategory);
        }
    }

    /**
     * Variant par défaut sans option (utilisé quand l'API ne retourne pas de SKU détaillé).
     */
    private function upsertDefaultVariant(ProductInterface $product, ProductDto $dto, ?TaxCategoryInterface $taxCategory): void
    {
        $variantCode = 'aliexpress_' . $dto->aliExpressId . '_default';
        $variant = $this->findOrCreateVariant($product, $variantCode);

        $variant->setTracked(false);

        if ($taxCategory !== null) {
            $variant->setTaxCategory($taxCategory);
        }

        $this->applyChannelPricing($variant, $dto->price, $dto->price);
    }

    /**
     * Crée ou met à jour un variant correspondant à un SKU AliExpress.
     */
    private function upsertVariant(
        ProductInterface $product,
        string $aliExpressId,
        SkuDto $skuDto,
        ?TaxCategoryInterface $taxCategory,
    ): void {
        $variantCode = 'aliexpress_' . $aliExpressId . '_sku_' . $skuDto->skuId;
        $variant = $this->findOrCreateVariant($product, $variantCode);

        $variant->setTracked(false);

        if ($taxCategory !== null) {
            $variant->setTaxCategory($taxCategory);
        }

        // Options / valeurs du variant
        foreach ($skuDto->properties as $prop) {
            $optionValue = $this->findOrCreateOptionValue($product, $prop);

            if (!$variant->hasOptionValue($optionValue)) {
                $variant->addOptionValue($optionValue);
            }
        }

        // Stocke le skuAttr au format AliExpress : "propertyId:valueId;propertyId:valueId"
        if ($variant instanceof AppProductVariant) {
            $skuAttr = implode(';', array_map(
                static fn (array $p): string => $p['propertyId'] . ':' . $p['valueId'],
                $skuDto->properties,
            ));
            $variant->setAliExpressSkuAttr($skuAttr);
        }

        $this->applyChannelPricing($variant, $skuDto->offerSalePrice, $skuDto->skuPrice);
    }

    /**
     * Retourne le variant existant par code ou en crée un nouveau attaché au produit.
     */
    private function findOrCreateVariant(ProductInterface $product, string $code): ProductVariantInterface
    {
        foreach ($product->getVariants() as $existing) {
            if ($existing->getCode() === $code) {
                assert($existing instanceof ProductVariantInterface);

                return $existing;
            }
        }

        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantFactory->createNew();
        $variant->setCode($code);
        $product->addVariant($variant);

        return $variant;
    }

    /**
     * Applique le prix canal calculé (formule PDD) sur toutes les chaînes Sylius.
     */
    private function applyChannelPricing(
        ProductVariantInterface $variant,
        float $pddSalePrice,
        float $pddOriginalPrice,
    ): void {
        $price = $this->pricingCalculator->computePrice($pddSalePrice);
        $originalPrice = $this->pricingCalculator->computeOriginalPrice($pddOriginalPrice);

        foreach ($this->channelRepository->findAll() as $channel) {
            /** @var \Sylius\Component\Core\Model\ChannelInterface $channel */
            $channelCode = $channel->getCode();
            $channelPricing = $variant->getChannelPricingForChannel($channel);

            if ($channelPricing === null) {
                /** @var ChannelPricingInterface $channelPricing */
                $channelPricing = $this->channelPricingFactory->createNew();
                $channelPricing->setChannelCode($channelCode);
                $variant->addChannelPricing($channelPricing);
            }

            $channelPricing->setPrice($price);
            $channelPricing->setOriginalPrice($originalPrice);
        }
    }

    // ── Options produit ──────────────────────────────────────────────────────

    /**
     * Retourne ou crée un ProductOption + ProductOptionValue pour une propriété SKU.
     *
     * @param array{propertyId: int, propertyName: string, valueId: int, valueName: string} $prop
     */
    private function findOrCreateOptionValue(
        ProductInterface $product,
        array $prop,
    ): ProductOptionValueInterface {
        $optionCode = 'aliexpress_prop_' . $prop['propertyId'];
        $valueCode = 'aliexpress_propval_' . $prop['propertyId'] . '_' . $prop['valueId'];

        $option = $this->findOrCreateOption($product, $optionCode, $prop['propertyName']);

        // 1. Cache in-process (entités créées dans ce upsert mais pas encore flushées)
        if (isset($this->pendingOptionValues[$valueCode])) {
            return $this->pendingOptionValues[$valueCode];
        }

        // 2. Chercher dans les valeurs déjà liées à l'option
        foreach ($option->getValues() as $existing) {
            if ($existing->getCode() === $valueCode) {
                $this->pendingOptionValues[$valueCode] = $existing;

                return $existing;
            }
        }

        // 3. Chercher en base (valeur d'un autre produit partageant la même option)
        /** @var ProductOptionValueInterface|null $optionValue */
        $optionValue = $this->entityManager
            ->getRepository(ProductOptionValueInterface::class)
            ->findOneBy(['code' => $valueCode]);

        if ($optionValue === null) {
            /** @var ProductOptionValueInterface $optionValue */
            $optionValue = $this->productOptionValueFactory->createNew();
            $optionValue->setCode($valueCode);
            $optionValue->setOption($option);
            $this->entityManager->persist($optionValue);
        }

        $this->pendingOptionValues[$valueCode] = $optionValue;

        // Initialise ou met à jour les traductions fr_FR et en_US
        // Les deux locales reçoivent la même valeur : property_value_definition_name
        $valueName = $prop['valueName'] !== '' ? $prop['valueName'] : $valueCode;
        $optionValue->setFallbackLocale('fr_FR');

        foreach (['fr_FR', 'en_US'] as $locale) {
            $optionValue->setCurrentLocale($locale);
            $optionValue->setValue($valueName);
        }

        return $optionValue;
    }

    /**
     * Retourne ou crée un ProductOption et l'associe au produit si nécessaire.
     */
    private function findOrCreateOption(
        ProductInterface $product,
        string $code,
        string $name,
    ): ProductOptionInterface {
        // Chercher parmi les options déjà attachées au produit
        foreach ($product->getOptions() as $existing) {
            if ($existing->getCode() === $code) {
                return $existing;
            }
        }

        // Chercher en base
        /** @var ProductOptionInterface|null $option */
        $option = $this->entityManager
            ->getRepository(ProductOptionInterface::class)
            ->findOneBy(['code' => $code]);

        if ($option === null) {
            /** @var ProductOptionInterface $option */
            $option = $this->productOptionFactory->createNew();
            $option->setCode($code);
            $this->entityManager->persist($option);
        }

        // Initialise ou met à jour les traductions fr_FR et en_US
        $optionName = $name !== '' ? $name : $code;
        $option->setFallbackLocale('fr_FR');

        foreach (['fr_FR', 'en_US'] as $locale) {
            $option->setCurrentLocale($locale);
            $option->setName($optionName);
        }

        if (!$product->hasOption($option)) {
            $product->addOption($option);
        }

        return $option;
    }

    // ── Catégorie de taxe ────────────────────────────────────────────────────

    /**
     * Retourne la première catégorie de taxe disponible en base.
     */
    private function findFirstTaxCategory(): ?TaxCategoryInterface
    {
        /** @var TaxCategoryInterface|null $taxCategory */
        $taxCategory = $this->entityManager
            ->getRepository(TaxCategoryInterface::class)
            ->findOneBy([]);

        return $taxCategory;
    }

    // ── Images ───────────────────────────────────────────────────────────────

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

        // Supprime toutes les images AliExpress précédentes (évite les doublons en re-import)
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

        $ext = pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION) ?: 'jpg';
        $tmpPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.' . $ext;

        file_put_contents($tmpPath, $content);

        return $tmpPath;
    }

    // ── Utilitaires ──────────────────────────────────────────────────────────

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
