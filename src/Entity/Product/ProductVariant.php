<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductVariantInterface;
use Sylius\MolliePlugin\Entity\RecurringProductVariantTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant')]
class ProductVariant extends BaseProductVariant implements ProductVariantInterface
{
    use RecurringProductVariantTrait;

    /**
     * Attributs SKU AliExpress au format "propertyId:valueId;propertyId:valueId".
     * Utilisé lors du placement de commande dropship pour identifier le variant commandé.
     * Vide pour les produits non-AliExpress.
     */
    #[ORM\Column(name: 'aliexpress_sku_attr', type: 'string', length: 512, nullable: true, options: ['default' => null])]
    private ?string $aliExpressSkuAttr = null;

    public function getAliExpressSkuAttr(): string
    {
        return $this->aliExpressSkuAttr ?? '';
    }

    public function setAliExpressSkuAttr(string $skuAttr): void
    {
        $this->aliExpressSkuAttr = $skuAttr !== '' ? $skuAttr : null;
    }

    protected function createTranslation(): ProductVariantTranslationInterface
    {
        return new ProductVariantTranslation();
    }
}
