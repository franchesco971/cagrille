<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Service;

/**
 * Calcule le prix canal Sylius (en centimes) à partir du prix d'achat AliExpress.
 *
 * Formule :
 *   cp   = pdd × advertisingRate       (ex. 1 %)
 *   fdp  = pdd × paymentRate + paymentFixed  (ex. 1,2 % + 0,10 €)
 *   taxes = pdd × taxRate              (ex. 12,8 %)
 *   marge = pdd × marginRate           (ex. 10 %)
 *   prix  = pdd + cp + fdp + taxes + marge
 *
 * Tous les taux sont des variables d'environnement pour permettre un ajustement
 * sans redéploiement du code.
 *
 * Principe SRP : calcul de prix uniquement.
 */
class ChannelPricingCalculator
{
    public function __construct(
        private readonly float $advertisingRate,  // ALIEXPRESS_PRICING_ADVERTISING_RATE
        private readonly float $paymentRate,      // ALIEXPRESS_PRICING_PAYMENT_RATE
        private readonly float $paymentFixed,     // ALIEXPRESS_PRICING_PAYMENT_FIXED (€)
        private readonly float $taxRate,          // ALIEXPRESS_PRICING_TAX_RATE
        private readonly float $marginRate,       // ALIEXPRESS_PRICING_MARGIN_RATE
    ) {
    }

    /**
     * Retourne le prix de vente final en centimes d'euro.
     *
     * @param float $pdd Prix de départ (offer_sale_price AliExpress, en devise source)
     */
    public function computePrice(float $pdd): int
    {
        if ($pdd <= 0.0) {
            return 100; // valeur minimale de sécurité : 1,00 €
        }

        $cp = $pdd * $this->advertisingRate;
        $fdp = $pdd * $this->paymentRate + $this->paymentFixed;
        $taxes = $pdd * $this->taxRate;
        $marge = $pdd * $this->marginRate;
        $prix = $pdd + $cp + $fdp + $taxes + $marge;

        return (int) round($prix * 100);
    }

    /**
     * Retourne le prix barré (prix d'origine) en centimes d'euro.
     *
     * @param float $pdd Prix de départ (sku_price AliExpress, en devise source)
     */
    public function computeOriginalPrice(float $pdd): ?int
    {
        if ($pdd <= 0.0) {
            return null;
        }

        return (int) round($pdd * 100);
    }
}
