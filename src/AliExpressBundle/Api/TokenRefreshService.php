<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api;

use Cagrille\AliExpressBundle\Contract\TokenStorageInterface;
use Cagrille\AliExpressBundle\Exception\AliExpressApiException;
use Cagrille\AliExpressBundle\IopSdk\IopClient;
use Cagrille\AliExpressBundle\IopSdk\IopRequest;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion du cycle de vie du token OAuth AliExpress.
 *
 * Protocole : AliExpress IOP OAuth2 (authorization code flow).
 * Documentation : https://developers.aliexpress-solution.com/dropshipping
 *
 * Flux :
 *   1. getAuthorizationUrl()  → redirige l'utilisateur vers AliExpress
 *   2. exchangeCode()         → échange le code de callback → access_token + refresh_token
 *   3. refresh()              → renouvelle le token via refresh_token (automatique ou manuel)
 */
class TokenRefreshService
{
    private const AUTH_URL           = 'https://oauth.aliexpress-solution.com/oauth/authorize';
    private const TOKEN_CREATE_PATH  = '/rest/auth/token/create';
    private const TOKEN_REFRESH_PATH = '/rest/auth/token/refresh';

    private readonly IopClient $iopClient;

    public function __construct(
        private readonly string                $appKey,
        private readonly string                $appSecret,
        private readonly string                $baseUrl,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface       $logger,
    ) {
        $this->iopClient = new IopClient(
            url:  $this->baseUrl,
            appkey:     $this->appKey,
            secretKey:  $this->appSecret,
            logger:     $this->logger,
        );
    }

    /**
     * Construit l'URL d'autorisation OAuth AliExpress.
     * L'utilisateur sera redirigé vers cette URL pour approuver l'accès.
     */
    public function getAuthorizationUrl(string $redirectUri): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'response_type' => 'code',
            'force_auth'    => 'true',
            'redirect_uri'  => $redirectUri,
            'client_id'     => $this->appKey,
        ]);
    }

    /**
     * Échange un code d'autorisation contre un access_token + refresh_token.
     *
     * @throws AliExpressApiException
     */
    public function exchangeCode(string $code): void
    {
        $data = $this->callAuthEndpoint(self::TOKEN_CREATE_PATH, ['code' => $code]);
        $this->saveFromResponse($data);

        $this->logger->info('[AliExpress] Nouveau token obtenu via authorization code.');
    }

    /**
     * Renouvelle le token d'accès à partir du refresh_token.
     *
     * @throws AliExpressApiException
     */
    public function refresh(): void
    {
        $refreshToken = $this->tokenStorage->getRefreshToken();

        if ($refreshToken === null || $refreshToken === '') {
            throw new AliExpressApiException('Impossible de rafraîchir le token : aucun refresh_token disponible. Lancez d\'abord le flux OAuth depuis l\'interface admin.');
        }

        $data = $this->callAuthEndpoint(self::TOKEN_REFRESH_PATH, ['refresh_token' => $refreshToken]);
        $this->saveFromResponse($data);

        $this->logger->info('[AliExpress] Token rafraîchi automatiquement.');
    }

    /**
     * Appelle un endpoint d'authentification AliExpress via le SDK IOP.
     *
     * @param array<string, string> $extraParams
     * @return array<string, mixed>
     * @throws AliExpressApiException
     */
    private function callAuthEndpoint(string $path, array $extraParams): array
    {
        $request = new IopRequest($path, 'POST');
        foreach ($extraParams as $key => $value) {
            $request->addApiParam($key, $value);
        }

        $response = $this->iopClient->execute($request);

        if ($response->isError()) {
            throw new AliExpressApiException(sprintf(
                'Erreur API token [%s] : %s',
                $response->getCode() ?? 'unknown',
                $response->getMessage() ?? 'Erreur inconnue',
            ));
        }

        $accessToken = $response->getString('access_token');
        if ($accessToken === null) {
            throw new AliExpressApiException('access_token absent de la réponse : ' . substr($response->getBody(), 0, 300));
        }

        return $response->getData();
    }

    /**
     * Persiste les données de token depuis la réponse de l'API.
     *
     * @param array<string, mixed> $data
     */
    private function saveFromResponse(array $data): void
    {
        $accessToken  = is_string($data['access_token']) ? $data['access_token'] : '';
        $refreshToken = isset($data['refresh_token']) && is_string($data['refresh_token'])
            ? $data['refresh_token']
            : null;

        $expiresAt = null;

        // expire_time : unix timestamp en millisecondes (format standard AliExpress)
        if (isset($data['expire_time']) && is_numeric($data['expire_time'])) {
            $ts        = (int) $data['expire_time'];
            $expiresAt = \DateTimeImmutable::createFromFormat('U', (string) intdiv($ts, 1000)) ?: null;
        }
        // expires_in : durée en secondes (format alternatif)
        elseif (isset($data['expires_in']) && is_numeric($data['expires_in'])) {
            $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', (int) $data['expires_in']));
        }

        $this->tokenStorage->save($accessToken, $refreshToken, $expiresAt);
    }
}
