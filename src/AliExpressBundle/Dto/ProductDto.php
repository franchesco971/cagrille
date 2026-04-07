<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO représentant un produit AliExpress DS.
 *
 * Immutable : tous les champs sont readonly.
 * Principe SRP : transport de données produit uniquement.
 */
final class ProductDto
{
    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function __construct(
        public readonly string $aliExpressId,  // item_id AliExpress
        public readonly string $name,
        public readonly string $description,
        public readonly float $price,         // Prix d'achat fournisseur (USD)
        public readonly string $currency,
        public readonly int $stock,
        public readonly array $images,        // URLs des images
        public readonly array $skus,          // Variants (couleur, taille, etc.)
        public readonly string $categoryId,
        public readonly string $storeId,
        public readonly string $storeName,
        public readonly string $shipsFrom,     // Code pays expédition (ex: "CN")
        public readonly int $shippingDays,  // Délai livraison estimé (jours)
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Construit un ProductDto depuis le contenu de aliexpress_ds_product_get_response.result.
     *
     * Structure de référence :
     *   ae_item_base_info_dto.product_id, subject, detail, categoryId, totalAvailQuantity
     *   ae_item_sku_info_dtos.ae_item_sku_info_d_t_o[].sku_price, offer_sale_price, sku_available_stock
     *   ae_multimedia_info_dto.image_urls  (séparés par ;)
     *   ae_store_info.store_id, store_name, store_country_code
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromApiResponse(array $result): self
    {
        $baseInfo = $result['ae_item_base_info_dto'] ?? [];
        $skus = $result['ae_item_sku_info_dtos']['ae_item_sku_info_d_t_o'] ?? [];
        $storeInfo = $result['ae_store_info'] ?? [];

        $firstSku = $skus[0] ?? [];
        $price = (float) ($firstSku['offer_sale_price'] ?? $firstSku['sku_price'] ?? 0.0);

        // Stock : somme des sku_available_stock ou totalAvailQuantity si présent
        $stock = (int) ($baseInfo['totalAvailQuantity'] ?? array_sum(
            array_column($skus, 'sku_available_stock'),
        ));

        return new self(
            aliExpressId:  (string) ($baseInfo['product_id'] ?? ''),
            name:          (string) ($baseInfo['subject'] ?? ''),
            description:   (string) ($baseInfo['detail'] ?? ''),
            price:         $price,
            currency:      (string) ($firstSku['currency_code'] ?? 'EUR'),
            stock:         $stock,
            images:        self::extractImages($result),
            skus:          $skus,
            categoryId:    (string) ($baseInfo['categoryId'] ?? ''),
            storeId:       (string) ($storeInfo['store_id'] ?? ''),
            storeName:     (string) ($storeInfo['store_name'] ?? ''),
            shipsFrom:     (string) ($storeInfo['store_country_code'] ?? 'CN'),
            shippingDays:  15,
            updatedAt:     new \DateTimeImmutable(),
        );
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    private static function extractImages(array $data): array
    {
        $mediaDto = $data['ae_multimedia_info_dto'] ?? [];
        $imageUrls = $mediaDto['image_urls'] ?? $data['image_urls'] ?? '';

        if (is_string($imageUrls) && $imageUrls !== '') {
            return array_filter(explode(';', $imageUrls));
        }

        if (is_array($imageUrls)) {
            return array_values(array_filter($imageUrls));
        }

        return [];
    }
}
