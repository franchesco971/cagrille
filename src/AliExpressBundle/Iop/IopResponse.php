<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Iop;

/**
 * Encapsule la réponse brute de l'API AliExpress IOP.
 *
 * Portage PHP du SDK officiel AliExpress IOP.
 */
class IopResponse
{
    /** @var array<string, mixed> */
    private array $data;

    private string $body;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, string $body)
    {
        $this->data = $data;
        $this->body = $body;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getCode(): ?string
    {
        $code = $this->data['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    public function getType(): ?string
    {
        $type = $this->data['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    public function getMessage(): ?string
    {
        $msg = $this->data['message'] ?? null;

        return is_string($msg) ? $msg : null;
    }

    public function isError(): bool
    {
        // Réponse d'erreur IOP : {"type":"ISV","code":"...","message":"..."}
        return isset($this->data['code']) && isset($this->data['type']);
    }

    public function getString(string $key): ?string
    {
        $val = $this->data[$key] ?? null;

        return is_scalar($val) ? (string) $val : null;
    }
}
