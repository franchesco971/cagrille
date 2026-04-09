<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Contract;

/**
 * Contrat pour le client HTTP Alibaba API.
 * Principe ISP : interface minimaliste, focalisée sur l'envoi de requêtes.
 * Principe DIP : les services dépendent de cette interface, non de l'implémentation.
 */
interface AlibabaApiClientInterface
{
    /**
     * Effectue une requête GET authentifiée vers l'API Alibaba.
     *
     * @throws \Cagrille\AlibabaBundle\Exception\AlibabaApiException
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public function get(string $endpoint, array $params = []): array;

    /**
     * Effectue une requête POST authentifiée vers l'API Alibaba.
     *
     * @throws \Cagrille\AlibabaBundle\Exception\AlibabaApiException
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public function post(string $endpoint, array $payload = []): array;
}
