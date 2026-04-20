<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Dto;

/**
 * DTO représentant un variant (SKU) AliExpress.
 *
 * Immutable : tous les champs sont readonly.
 * Principe SRP : transport de données SKU uniquement.
 */
final class SkuDto
{
    /**
     * @param array<int, array{propertyId: int, propertyName: string, valueId: int, valueName: string}> $properties
     */
    public function __construct(
        public readonly string $skuId,
        public readonly float $offerSalePrice,  // prix de vente (PDD)
        public readonly float $skuPrice,         // prix original
        public readonly string $currencyCode,
        public readonly array $properties,       // options du variant (couleur, taille, …)
    ) {
    }

    /**
     * Construit un SkuDto depuis un élément ae_item_sku_info_d_t_o.
     *
     * Structure de référence :
     *   sku_id, offer_sale_price, sku_price, currency_code
     *   ae_sku_property_dtos.ae_sku_property_d_t_o[].sku_property_id
     *   ae_sku_property_dtos.ae_sku_property_d_t_o[].sku_property_name
     *   ae_sku_property_dtos.ae_sku_property_d_t_o[].property_value_id
     *   ae_sku_property_dtos.ae_sku_property_d_t_o[].property_value_definition_name
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromApiData(array $data, int $fallbackIndex = 0): self
    {
        $skuId = (string) ($data['sku_id'] ?? $data['id'] ?? (string) $fallbackIndex);
        $offerSalePrice = (float) ($data['offer_sale_price'] ?? $data['sku_price'] ?? 0.0);
        $skuPrice = (float) ($data['sku_price'] ?? $offerSalePrice);
        $currencyCode = (string) ($data['currency_code'] ?? 'EUR');

        $rawProperties = $data['ae_sku_property_dtos']['ae_sku_property_d_t_o'] ?? [];
        $properties = [];

        if (is_array($rawProperties)) {
            foreach ($rawProperties as $prop) {
                if (!is_array($prop)) {
                    continue;
                }

                $rawPropId = $prop['sku_property_id'] ?? null;
                $propertyId = is_scalar($rawPropId) ? (int) $rawPropId : 0;
                $rawValueId = $prop['property_value_id'] ?? null;
                $valueId = is_scalar($rawValueId) ? (int) $rawValueId : 0;

                if ($propertyId === 0 || $valueId === 0) {
                    continue;
                }

                $properties[] = [
                    'propertyId' => $propertyId,
                    'propertyName' => (string) ($prop['sku_property_name'] ?? ''),
                    'valueId' => $valueId,
                    'valueName' => (string) ($prop['property_value_definition_name'] ?? $prop['sku_property_value'] ?? ''),
                ];
            }
        }

        return new self(
            skuId:          $skuId,
            offerSalePrice: $offerSalePrice,
            skuPrice:       $skuPrice,
            currencyCode:   $currencyCode,
            properties:     $properties,
        );
    }
}
