<?php

declare(strict_types=1);

namespace Cagrille\AlibabaBundle\Exception;

/**
 * Exception levée lors d'erreurs de communication avec l'API Alibaba.
 */
class AlibabaApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int    $statusCode  = 0,
        private readonly string $apiCode     = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getApiCode(): string
    {
        return $this->apiCode;
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public static function fromApiError(array $errorResponse, int $httpStatus = 0): self
    {
        $code    = (string) ($errorResponse['error_code'] ?? $errorResponse['code'] ?? '');
        $message = (string) ($errorResponse['error_msg'] ?? $errorResponse['message'] ?? 'Erreur API inconnue');

        return new self($message, $httpStatus, $code);
    }
}
