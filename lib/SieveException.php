<?php

declare(strict_types=1);

namespace Sieve;

use Exception;

class SieveException extends Exception
{
    protected $token;

    /**
     * SieveException constructor.
     *
     * @param SieveToken $token
     * @param            $arg
     */
    public function __construct(SieveToken $token, $arg)
    {
        $this->token = $token;

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

    public function getLineNo()
    {
        return $this->token->line;
    }
}
