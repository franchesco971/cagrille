<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\Exception;

/**
 * Exception typée pour les erreurs retournées par l'API AliExpress.
 * Encapsule le code et le sous-code métier de l'API pour faciliter le debug.
 */
class AliExpressApiException extends \RuntimeException
{
    public function __construct(
        string     $message,
        int        $code = 0,
        public readonly string $apiCode = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
