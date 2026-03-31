<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Iop;

use Cagrille\AliExpressBundle\Exception\AliExpressApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Client IOP AliExpress — portage fidèle du SDK officiel (IopClient.php).
 *
 * Algorithme de signature (protocole IOP) :
 *   1. Rassembler TOUS les paramètres (sys + api), incluant method, partner_id, format, simplify
 *   2. Trier par clé (ksort)
 *   3. Si apiName contient '/' : préfixer la chaîne par apiName (protocole REST)
 *   4. Concaténer TOUTES les paires clé+valeur (y compris les valeurs vides — idem SDK officiel)
 *   5. HMAC-SHA256(key=secretKey, msg=chaine), résultat en majuscules
 *
 * Transport (conforme au SDK officiel) :
 *   - Params système (app_key, sign, timestamp, method, etc.) → URL query string
 *   - Params API (code, refresh_token, session, etc.) → POST body multipart/form-data
 */
class IopClientImpl
{
    private const SIGN_METHOD = 'sha256';
    private const SDK_VERSION = 'iop-sdk-php-20220608';

    private readonly Client $httpClient;

    public function __construct(
        private readonly string          $serverUrl,
        private readonly string          $appKey,
        private readonly string          $appSecret,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->serverUrl, '/'),
            'timeout'  => 15,
        ]);
    }

    /**
     * Exécute une requête IOP signée.
     *
     * @throws AliExpressApiException
     */
    public function execute(IopRequest $request, ?string $session = null): IopResponse
    {
        // Params système (idem SDK officiel)
        $sysParams = [
            'app_key'     => $this->appKey,
            'sign_method' => self::SIGN_METHOD,
            'timestamp'   => $this->msectime(),
            'method'      => $request->getApiName(),
            'partner_id'  => self::SDK_VERSION,
            'simplify'    => 'false',
            'format'      => 'json',
        ];

        if ($session !== null && $session !== '') {
            $sysParams['session'] = $session;
        }

        // Params API (code, refresh_token, etc.)
        $apiParams = $request->getApiParams();

        // Signature sur la fusion des deux ensembles (idem SDK officiel)
        $sysParams['sign'] = $this->generateSign(
            $request->getApiName(),
            array_merge($apiParams, $sysParams),
        );

        $this->logger->debug('[AliExpress IOP] Requête', [
            'api'       => $request->getApiName(),
            'sysParams' => $sysParams,
            'apiParams' => $apiParams,
        ]);

        \var_dump($request->getApiName(), $sysParams, $apiParams); // DEBUG
        exit;

        // Transport : sys params → query string, api params → multipart POST body (idem SDK)
        try {
            $response = $this->httpClient->post($request->getApiName(), [
                'query'     => $sysParams,
                'multipart' => $this->toMultipart($apiParams),
            ]);
            $body = (string) $response->getBody();
        } catch (GuzzleException $e) {
            throw new AliExpressApiException('Erreur HTTP IOP : ' . $e->getMessage(), $e->getCode(), '', $e);
        }

        $this->logger->debug('[AliExpress IOP] Réponse', ['body' => $body]);

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new AliExpressApiException('Réponse non-JSON IOP : ' . substr($body, 0, 300));
        }

        /** @var array<string, mixed> $decoded */
        return new IopResponse($decoded, $body);
    }

    /**
     * Génère la signature HMAC-SHA256 selon le protocole IOP officiel.
     *
     * @param array<string, string> $params
     */
    private function generateSign(string $apiName, array $params): string
    {
        ksort($params);

        // Préfixe apiName si le chemin contient '/' (protocole REST — idem SDK officiel)
        $str = str_contains($apiName, '/') ? $apiName : '';

        foreach ($params as $k => $v) {
            $str .= $k . $v;
        }

        return strtoupper(hash_hmac('sha256', $str, $this->appSecret));
    }

    /**
     * Timestamp en millisecondes tronqué à la seconde (idem msectime() du SDK officiel).
     * Le SDK fait : $sec . '000' — pas de précision sub-secondaire.
     */
    private function msectime(): string
    {
        return time() . '000';
    }

    /**
     * Convertit un tableau de params en format multipart pour Guzzle.
     *
     * @param  array<string, string> $params
     * @return array<int, array{name: string, contents: string}>
     */
    private function toMultipart(array $params): array
    {
        $parts = [];
        foreach ($params as $name => $contents) {
            $parts[] = ['name' => $name, 'contents' => $contents];
        }

        return $parts;
    }
}
