<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Contract;

/**
 * Interface du client HTTP AliExpress.
 * Principe ISP : seules les opérations réellement utilisées sont déclarées.
 */
interface AliExpressApiClientInterface
{
    /**
     * Appelle une méthode de l'API AliExpress DS via POST.
     *
     * @param  string $method Nom de la méthode API (ex: "aliexpress.ds.product.get")
     * @param  array  $params Paramètres métier de la méthode
     * @return array  Corps de réponse décodé
     *
     * @throws \Cagrille\AliExpressBundle\Exception\AliExpressApiException
     */
    public function call(string $method, array $params = []): array;
}
