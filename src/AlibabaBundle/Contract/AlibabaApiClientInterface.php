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
     * @param string $endpoint  Endpoint relatif (ex: "/products/list")
     * @param array  $params    Paramètres de requête
     * @return array            Données décodées de la réponse JSON
     *
     * @throws \Cagrille\AlibabaBundle\Exception\AlibabaApiException
     */
    public function get(string $endpoint, array $params = []): array;

    /**
     * Effectue une requête POST authentifiée vers l'API Alibaba.
     *
     * @throws \Cagrille\AlibabaBundle\Exception\AlibabaApiException
     */
    public function post(string $endpoint, array $payload = []): array;
}
