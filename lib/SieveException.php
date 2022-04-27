<?php

declare(strict_types=1);

namespace Sieve;

use Exception;

class SieveException extends Exception
{
    /**
     * SieveException constructor.
     *
     * @param SieveToken $token
     * @param            $arg
     */
    public function __construct(protected SieveToken $token, $arg)
    {
        if (is_string($arg)) {
            $message = $arg;
        } else {
            if (is_array($arg)) {
                $type = SieveToken::typeString((int) array_shift($arg));
                foreach ($arg as $t) {
                    $type .= ' or ' . SieveToken::typeString((int) $t);
                }
            } else {
                $type = SieveToken::typeString((int) $arg);
            }

            $tokenType = SieveToken::typeString($token->type);
            $message = "$tokenType where $type expected near $token->text";
        }

        parent::__construct("line $token->line: $message");
    }

    public function getLineNo(): int
    {
        return $this->token->line;
    }
}
