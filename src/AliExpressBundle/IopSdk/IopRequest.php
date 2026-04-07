<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\IopSdk;

class IopRequest
{
    /** @var string[] */
    public array $headerParams = [];

    /** @var string[] */
    public array $udfParams = [];

    /** @var array<string, array{name: string, type: string, content: string}> */
    public array $fileParams = [];

    public string $simplify = 'false';

    public string $format = 'json'; //支持TOP的xml

    public function __construct(public string $apiName, public string $httpMethod = 'POST')
    {
        if ($this->startWith($apiName, '//')) {
            throw new \Exception('api name is invalid. It should be start with /');
        }
    }

    public function addApiParam(string $key, string $value): void
    {
        $this->udfParams[$key] = $value;
    }

    public function addFileParam(?string $key, string $content, string $mimeType = 'application/octet-stream'): void
    {
        if (!is_string($key)) {
            throw new \Exception('api file param key should be string');
        }

        $file = [
            'type' => $mimeType,
            'content' => $content,
            'name' => $key,
        ];
        $this->fileParams[$key] = $file;
    }

    public function addHttpHeaderParam(?string $key, ?string $value): void
    {
        if (!is_string($key)) {
            throw new \Exception('http header param key should be string');
        }

        if (!is_string($value)) {
            throw new \Exception('http header param value should be string');
        }

        $this->headerParams[$key] = $value;
    }

    private function startWith(string $str, string $needle): bool
    {
        return strpos($str, $needle) === 0;
    }
}
