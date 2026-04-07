<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api;

use Cagrille\AliExpressBundle\Contract\TokenStorageInterface;

/**
 * Stockage du token OAuth AliExpress dans un fichier JSON dans var/.
 *
 * Principe de fallback : si le fichier n'existe pas encore, utilise
 * le token fourni via la variable d'environnement ALIEXPRESS_ACCESS_TOKEN.
 * Dès qu'un token est obtenu via OAuth, il est écrit dans le fichier
 * et prend le dessus sur la variable d'environnement.
 */
class TokenStorage implements TokenStorageInterface
{
    private const FILE_NAME = 'aliexpress_token.json';

    private bool $loaded = false;

    private string $accessToken;

    private ?string $refreshToken = null;

    private ?\DateTimeImmutable $expiresAt = null;

    public function __construct(
        private readonly string $varDir,
        /** @phpstan-ignore property.onlyWritten */
        private readonly string $initialAccessToken,
    ) {
        $this->accessToken = $initialAccessToken;
    }

    public function getAccessToken(): string
    {
        $this->ensureLoaded();

        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        $this->ensureLoaded();

        return $this->refreshToken;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        $this->ensureLoaded();

        return $this->expiresAt;
    }

    public function isExpiringSoon(int $bufferSeconds = 300): bool
    {
        $this->ensureLoaded();

        if ($this->expiresAt === null) {
            return false;
        }

        $threshold = new \DateTimeImmutable(sprintf('+%d seconds', $bufferSeconds));

        return $this->expiresAt <= $threshold;
    }

    public function save(string $accessToken, ?string $refreshToken, ?\DateTimeImmutable $expiresAt): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresAt = $expiresAt;

        $data = json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt?->format(\DateTimeInterface::ATOM),
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);

        if ($data !== false) {
            file_put_contents($this->getFilePath(), $data);
        }
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $path = $this->getFilePath();

        if (!file_exists($path)) {
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return;
        }

        if (isset($data['access_token']) && is_string($data['access_token']) && $data['access_token'] !== '') {
            $this->accessToken = $data['access_token'];
        }

        if (isset($data['refresh_token']) && is_string($data['refresh_token'])) {
            $this->refreshToken = $data['refresh_token'];
        }

        if (isset($data['expires_at']) && is_string($data['expires_at'])) {
            try {
                $this->expiresAt = new \DateTimeImmutable($data['expires_at']);
            } catch (\Exception) {
                // Date invalide, on ignore
            }
        }
    }

    private function getFilePath(): string
    {
        return rtrim($this->varDir, '/') . '/' . self::FILE_NAME;
    }
}
