<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Api;

use Cagrille\AlibabaBundle\Contract\AlibabaApiClientInterface;
use Cagrille\AlibabaBundle\Exception\AlibabaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP principal pour l'API officielle Alibaba.com International.
 *
 * Authentification : signature HMAC-MD5 sur chaque requête (standard TOP API).
 * Principe SRP : gère uniquement la communication HTTP et l'authentification.
 * Principe OCP : les endpoints sont des classes séparées injectant ce client.
 *
 * Documentation : https://developers.alibaba.com
 */
class AlibabaApiClient implements AlibabaApiClientInterface
{
    private readonly Client $httpClient;

    public function __construct(
        private readonly string          $appKey,
        private readonly string          $appSecret,
        private readonly string          $accessToken,
        private readonly string          $baseUrl,
        private readonly int             $timeout,
        private readonly LoggerInterface $logger,
    ) {
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout'  => $this->timeout,
            'headers'  => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function get(string $endpoint, array $params = []): array
    {
        $params = $this->addAuthParams($params, 'GET', $endpoint);

        try {
            $response = $this->httpClient->get('/rest' . $endpoint, [
                'query' => $params,
            ]);

            return $this->decodeResponse((string) $response->getBody(), $endpoint);
        } catch (GuzzleException $e) {
            $this->logger->error('[Alibaba] Erreur GET {endpoint}: {message}', [
                'endpoint' => $endpoint,
                'message'  => $e->getMessage(),
            ]);
            throw new AlibabaApiException($e->getMessage(), $e->getCode(), '', $e);
        }
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function post(string $endpoint, array $payload = []): array
    {
        $authParams = $this->addAuthParams([], 'POST', $endpoint);

        try {
            $response = $this->httpClient->post('/rest' . $endpoint, [
                'query' => $authParams,
                'json'  => $payload,
            ]);

            return $this->decodeResponse((string) $response->getBody(), $endpoint);
        } catch (GuzzleException $e) {
            $this->logger->error('[Alibaba] Erreur POST {endpoint}: {message}', [
                'endpoint' => $endpoint,
                'message'  => $e->getMessage(),
            ]);
            throw new AlibabaApiException($e->getMessage(), $e->getCode(), '', $e);
        }
    }

    /**
     * Ajoute les paramètres d'authentification signés selon le protocole IOP Alibaba.
     * La signature HMAC-SHA256 est calculée sur apiPath + sorted key-value pairs.
     * @phpstan-ignore missingType.iterableValue
     */
    private function addAuthParams(array $params, string $method, string $endpoint): array
    {
        $params['app_key']      = $this->appKey;
        $params['access_token'] = $this->accessToken;
        $params['timestamp']    = (string) (time() * 1000);
        $params['sign_method']  = 'sha256';

        ksort($params);

        $params['sign'] = $this->generateSignature($params, $endpoint);

        return $params;
    }

    /**
     * Génère la signature HMAC-SHA256 selon la spécification IOP Alibaba.
     * Format : HMAC-SHA256(key=appSecret, message=apiPath + sorted key+value pairs)
     *
     * @phpstan-ignore missingType.iterableValue
     */
    private function generateSignature(array $params, string $endpoint): string
    {
        $signStr = $endpoint;

        foreach ($params as $key => $value) {
            if ($key !== 'sign' && is_string($value) && $value !== '') {
                $signStr .= $key . $value;
            }
        }

        return strtoupper(hash_hmac('sha256', $signStr, $this->appSecret));
    }

    /**
     * Décode la réponse JSON et lève une exception si l'API retourne une erreur.
     *
     * @return array<string, mixed>
     */
    private function decodeResponse(string $body, string $endpoint): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->logger->debug('[Alibaba] Réponse brute {endpoint}: {body}', [
            'endpoint' => $endpoint,
            'body'     => $body,
        ]);

        // L'API IOP Alibaba retourne les erreurs sous deux formats possibles :
        // - { "error_response": { ... } }  (ancien TOP format)
        // - { "type": "ISV", "code": "...", "message": "..." }  (IOP format plat)
        if (isset($data['error_response']) && is_array($data['error_response'])) {
            /** @var array<string, mixed> $errResponse */
            $errResponse = $data['error_response'];
            $this->logger->warning('[Alibaba] Erreur API {endpoint}: {error}', [
                'endpoint' => $endpoint,
                'error'    => json_encode($errResponse),
            ]);
            throw AlibabaApiException::fromApiError($errResponse);
        }

        if (isset($data['code'], $data['message'])
            && is_string($data['code'])
            && $data['code'] !== '0'
            && is_string($data['message'])
        ) {
            $apiCode    = $data['code'];
            $apiMessage = $data['message'];
            $this->logger->warning('[Alibaba] Erreur IOP {endpoint}: [{code}] {message}', [
                'endpoint' => $endpoint,
                'code'     => $apiCode,
                'message'  => $apiMessage,
            ]);
            throw new AlibabaApiException($apiMessage, 0, $apiCode);
        }

        return $data;
    }
}
