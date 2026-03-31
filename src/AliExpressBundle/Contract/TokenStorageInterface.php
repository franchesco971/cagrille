<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

/**
 * Contrat de stockage du token OAuth AliExpress.
 *
 * Abstraction permettant de swapper l'implémentation (fichier JSON, BDD, Redis…).
 */
interface TokenStorageInterface
{
    public function getAccessToken(): string;

    public function getRefreshToken(): ?string;

    public function getExpiresAt(): ?\DateTimeImmutable;

    /**
     * Indique si le token expire dans moins de $bufferSeconds secondes.
     * Retourne false si la date d'expiration est inconnue (token env brut).
     */
    public function isExpiringSoon(int $bufferSeconds = 300): bool;

    public function save(string $accessToken, ?string $refreshToken, ?\DateTimeImmutable $expiresAt): void;
}
