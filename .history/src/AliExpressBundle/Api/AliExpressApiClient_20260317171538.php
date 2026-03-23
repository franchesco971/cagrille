<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Api;

use Cagrille\AliExpressBundle\Contract\AliExpressApiClientInterface;
use Cagrille\AliExpressBundle\Exception\AliExpressApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Client HTTP pour l'API AliExpress Open Platform (DS / Dropshipping).
 *
 * Authentification : signature HMAC-SHA256.
 * Toutes les requêtes sont des POST multipart/form-data vers /sync.
 *
 * Principe SRP : gère uniquement la communication HTTP et la signature.
 * Principe OCP : les endpoints métier sont des classes séparées injectant ce client.
 *
 * Documentation : https://developers.aliexpress-solution.com/dropshipping
 */
class AliExpressApiClient implements AliExpressApiClientInterface
{
    private const API_VERSION = '2.0';
    private const SIGN_METHOD = 'sha256';

    private readonly Client $httpClient;

    public function __construct(
        private readonly string          $appKey,
        private readonly string          $appSecret,
        private readonly string          $accessToken,
        private readonly string          $baseUrl,
        private readonly int             $timeout,
        private readonly string          $targetCurrency,
        private readonly string          $targetLanguage,
        private readonly LoggerInterface $logger,
    ) {
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout'  => $this->timeout,
        ]);
    }

    /**
     * Envoie une requête POST signée à l'API AliExpress DS.
     *
     * {@inheritdoc}
     */
    public function call(string $method, array $params = []): array
    {
        $commonParams = [
            'app_key'      => $this->appKey,
            'method'       => $method,
            'session'      => $this->accessToken,
            'timestamp'    => date('Y-m-d H:i:s'),
            'format'       => 'json',
            'v'            => self::API_VERSION,
            'sign_method'  => self::SIGN_METHOD,
        ];

        $allParams              = array_merge($commonParams, $params);
        $allParams['sign']      = $this->generateSignature($allParams);

        try {
            $response = $this->httpClient->post('/sync', [
                'form_params' => $allParams,
            ]);

            $body = (string) $response->getBody();
            $this->logger->debug('[AliExpress] {method} → {status}', [
                'method' => $method,
                'status' => $response->getStatusCode(),
            ]);

            return $this->decodeResponse($body, $method);
        } catch (GuzzleException $e) {
            $this->logger->error('[AliExpress] Erreur {method}: {message}', [
                'method'  => $method,
                'message' => $e->getMessage(),
            ]);
            throw new AliExpressApiException($e->getMessage(), $e->getCode(), '', $e);
        }
    }

    /**
     * Retourne les paramètres d'enrichissement communs à toutes les requêtes produit.
     * Exposé pour les endpoints qui en ont besoin.
     */
    public function getProductQueryDefaults(): array
    {
        return [
            'target_currency' => $this->targetCurrency,
            'target_language' => $this->targetLanguage,
        ];
    }

    /**
     * Génère la signature HMAC-SHA256 selon le protocole AliExpress Open Platform.
     * Format : HMAC-SHA256(key=appSecret, message=sorted_key+value_pairs)
     */
    private function generateSignature(array $params): string
    {
        ksort($params);

        $signStr = '';
        foreach ($params as $key => $value) {
            if ($key !== 'sign' && $value !== '' && $value !== null) {
                $signStr .= $key . $value;
            }
        }

        return strtoupper(hash_hmac('sha256', $signStr, $this->appSecret));
    }

    /**
     * Décode la réponse JSON et lève une exception si l'API retourne une erreur.
     *
     * @throws AliExpressApiException
     */
    private function decodeResponse(string $body, string $method): array
    {
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new AliExpressApiException(
                sprintf('[AliExpress] Réponse non-JSON pour %s : %s', $method, substr($body, 0, 200))
            );
        }

        // L'API AliExpress retourne les erreurs sous error_response
        if (isset($data['error_response'])) {
            $err = $data['error_response'];
            throw new AliExpressApiException(
                sprintf('[AliExpress] Erreur API %s : %s — %s', $method, $err['code'] ?? '', $err['msg'] ?? ''),
                (int) ($err['code'] ?? 0),
                (string) ($err['sub_code'] ?? ''),
            );
        }

        return $data;
    }
}
