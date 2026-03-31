<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Iop;

/**
 * Représente une requête vers l'API AliExpress IOP.
 *
 * Portage PHP du SDK officiel AliExpress IOP.
 */
class IopRequest
{
    private string $apiName;

    /** @var array<string, string> */
    private array $apiParams = [];

    private string $httpMethod;

    public function __construct(string $apiName, string $httpMethod = 'POST')
    {
        $this->apiName    = $apiName;
        $this->httpMethod = $httpMethod;
    }

    public function getApiName(): string
    {
        return $this->apiName;
    }

    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    public function addApiParam(string $key, string $value): void
    {
        $this->apiParams[$key] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getApiParams(): array
    {
        return $this->apiParams;
    }
}
