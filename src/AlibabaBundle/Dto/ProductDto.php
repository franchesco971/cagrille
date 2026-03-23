<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Dto;

/**
 * DTO représentant un produit Alibaba.
 * Immutable par convention : setters absents, utiliser le constructeur.
 * Principe SRP : transport de données produit uniquement.
 */
final class ProductDto
{
    public function __construct(
        public readonly string $alibabaId,
        public readonly string $name,
        public readonly string $description,
        public readonly float  $price,
        public readonly string $currency,
        public readonly int    $moq,           // Minimum Order Quantity
        public readonly int    $stock,
        public readonly string $unit,
        public readonly array  $images,        // URLs des images
        public readonly array  $attributes,    // Caractéristiques techniques
        public readonly string $categoryId,
        public readonly string $supplierId,
        public readonly string $supplierName,
        public readonly string $originCountry,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Construit un ProductDto depuis un tableau de données brutes de l'API Alibaba.
     */
    public static function fromApiResponse(array $data): self
    {
        $trade  = $data['wholesale_trade'] ?? [];
        $sku    = $data['product_sku']['skus'][0] ?? [];

        // Prix : wholesale_trade.price, sinon premier SKU bulk_discount_price
        $price  = (float) ($trade['price'] ?? $sku['bulk_discount_prices'][0]['bulk_discount_price'] ?? 0.0);

        return new self(
            alibabaId:     (string) ($data['_alibaba_id'] ?? $data['product_id'] ?? $data['id'] ?? ''),
            name:          (string) ($data['subject'] ?? $data['name'] ?? ''),
            description:   (string) ($data['description'] ?? ''),
            price:         $price,
            currency:      'USD',
            moq:           (int) ($trade['min_order_quantity'] ?? $data['min_order_quantity'] ?? 1),
            stock:         (int) ($data['amount_on_hand'] ?? 0),
            unit:          (string) ($trade['unit_type'] ?? $data['unit'] ?? 'piece'),
            images:        self::extractImages($data),
            attributes:    (array) ($data['attributes'] ?? []),
            categoryId:    (string) ($data['category_id'] ?? ''),
            supplierId:    (string) ($data['owner_member'] ?? $data['company_id'] ?? ''),
            supplierName:  (string) ($data['owner_member_display_name'] ?? $data['company_name'] ?? ''),
            originCountry: (string) ($data['origin_country'] ?? 'CN'),
            updatedAt:     isset($data['gmt_modified'])
                ? new \DateTimeImmutable($data['gmt_modified'])
                : new \DateTimeImmutable(),
        );
    }

    private static function extractImages(array $data): array
    {
        // Format IOP : main_image.images[] ou image_list[]
        $mainImage = $data['main_image']['images'] ?? [];
        if (!empty($mainImage)) {
            return array_values(array_filter((array) $mainImage));
        }

        $images = $data['image_list'] ?? $data['images'] ?? [];

        if (is_string($images)) {
            return [$images];
        }

        return array_map(
            static fn(array|string $img) => is_array($img) ? ($img['url'] ?? '') : $img,
            (array) $images
        );
    }
}
