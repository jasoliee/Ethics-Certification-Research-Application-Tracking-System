<?php

namespace App\Exceptions;

use RuntimeException;

class CertificateGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $failureCode = 'generation_failed',
    ) {
        parent::__construct($message);
    }
}
