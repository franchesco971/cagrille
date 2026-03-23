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
    public function __construct(
        public readonly string             $aliExpressId,  // item_id AliExpress
        public readonly string             $name,
        public readonly string             $description,
        public readonly float              $price,         // Prix d'achat fournisseur (USD)
        public readonly string             $currency,
        public readonly int                $stock,
        public readonly array              $images,        // URLs des images
        public readonly array              $skus,          // Variants (couleur, taille, etc.)
        public readonly string             $categoryId,
        public readonly string             $storeId,
        public readonly string             $storeName,
        public readonly string             $shipsFrom,     // Code pays expédition (ex: "CN")
        public readonly int                $shippingDays,  // Délai livraison estimé (jours)
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Construit un ProductDto depuis la réponse brute de aliexpress.ds.product.get.
     */
    public static function fromApiResponse(array $data): self
    {
        $result   = $data['result'] ?? $data;
        $ae_item  = $result['ae_item_base_info_dto'] ?? $result;
        $sku_info = $result['ae_item_sku_info_dtos']['ae_item_sku_info_d_t_o'] ?? [];
        $price_range = $result['ae_multimedia_info_dto'] ?? [];

        $firstSku   = $sku_info[0] ?? [];
        $skuPrice   = $firstSku['offer_sale_price'] ?? $firstSku['sku_price'] ?? '0';
        $price      = (float) $skuPrice;

        $images = self::extractImages($result);

        return new self(
            aliExpressId:  (string) ($ae_item['product_id'] ?? $result['product_id'] ?? ''),
            name:          (string) ($ae_item['subject'] ?? $result['subject'] ?? ''),
            description:   (string) ($ae_item['detail'] ?? $result['detail'] ?? ''),
            price:         $price,
            currency:      'USD',
            stock:         (int) ($ae_item['totalAvailQuantity'] ?? $result['totalAvailQuantity'] ?? 0),
            images:        $images,
            skus:          $sku_info,
            categoryId:    (string) ($ae_item['categoryId'] ?? $result['categoryId'] ?? ''),
            storeId:       (string) ($ae_item['ownerMemberId'] ?? $result['ownerMemberId'] ?? ''),
            storeName:     (string) ($ae_item['ownerMemberSeqLong'] ?? ''),
            shipsFrom:     'CN',
            shippingDays:  (int) ($result['shippingLeadTime'] ?? 15),
            updatedAt:     new \DateTimeImmutable(),
        );
    }

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
