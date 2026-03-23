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
     * @throws \Cagrille\AliExpressBundle\Exception\AliExpressApiException
     * @phpstan-ignore missingType.iterableValue
     */
    public function call(string $method, array $params = []): array;
}
