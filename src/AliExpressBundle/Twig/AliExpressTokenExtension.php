<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Twig;

use Cagrille\AliExpressBundle\Contract\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose les informations du token AliExpress dans les templates Twig.
 */
class AliExpressTokenExtension extends AbstractExtension
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('aliexpress_token_info', $this->getTokenInfo(...)),
        ];
    }

    /**
     * @return array{
     *     expires_at: \DateTimeImmutable|null,
     *     has_refresh_token: bool,
     *     expiring_soon: bool,
     *     callback_url: string
     * }
     */
    public function getTokenInfo(): array
    {
        return [
            'expires_at' => $this->tokenStorage->getExpiresAt(),
            'has_refresh_token' => $this->tokenStorage->getRefreshToken() !== null,
            'expiring_soon' => $this->tokenStorage->isExpiringSoon(3600),
            'callback_url' => $this->urlGenerator->generate(
                'cagrille_admin_aliexpress_sync_auth_callback',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ];
    }
}
