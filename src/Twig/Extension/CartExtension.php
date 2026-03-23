<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Context\CartContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cagrille_cart_count', $this->getCartCount(...)),
        ];
    }

    public function getCartCount(): int
    {
        try {
            $cart = $this->cartContext->getCart();
            return $cart->getItems()->count();
        } catch (CartNotFoundException) {
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
