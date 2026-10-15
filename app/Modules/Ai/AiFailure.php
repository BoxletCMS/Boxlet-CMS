<?php

namespace App\Modules\Ai;

use RuntimeException;

/**
 * Why a question to the AI provider got no usable answer: a sentence for the owner as the
 * message, and the provider's own words, when there were any, for the log.
 */
final class AiFailure extends RuntimeException
{
    public function __construct(string $message, public readonly string $provider = '')
    {
        parent::__construct($message);
    }
}
