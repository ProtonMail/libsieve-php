<?php

declare(strict_types=1);

namespace Sieve;

class SieveScanner
{
    /**
     * SieveScanner constructor.
     *
     * @param $script
     */
    public function __construct(&$script)
    {
        if ($script === null) {
            return;
        }

        $this->tokenize($script);
    }

    /**
     * Set passthrough func.
     *
     * @param $callback
     */
    public function setPassthroughFunc(callable $callback): void
    {
        if ($callback === null || is_callable($callback)) {
            $this->ptFn = $callback;
        }
    }

    /**
     * Tokenizes a script.
     *
     * @param $script
     */
    public function tokenize(string &$script): void
    {
        $pos = 0;
        $line = 1;

        $scriptLength = strlen($script);

        $unprocessedScript = $script;

        //create one regex to find the right match
        //avoids looping over all possible tokens: increases performance
        $nameToType = [];
        $regex = [];
        // chr(65) === 'A'
        $i = 65;

        foreach ($this->tokenMatch as $type => $subregex) {
            $nameToType[chr($i)] = $type;
            $regex[] = '(?P<' . chr($i) . ">\G$subregex)";
            $i++;
        }

        $regex = '/' . implode('|', $regex) . '/';

        while ($pos < $scriptLength) {
            if (preg_match($regex, $unprocessedScript, $match, 0, $pos)) {
                // only keep the group that match and we only want matches with group names
                // we can use the group name to find the token type using nameToType
                $filterMatch = array_filter(
                    $match,
                    function ($value, $key) {
                        return is_string($key) && isset($value) && $value !== '';
                    },
                    ARRAY_FILTER_USE_BOTH
                );

                // the first element in filterMatch will contain the matched group and the key will be the name
                $type = $nameToType[key($filterMatch)];
                $currentMatch = current($filterMatch);

                //create the token
                $token = new SieveToken($type, $currentMatch, $line);
                $this->tokens[] = $token;

                if ($type === SieveToken::UNKNOWN) {
                    return;
                }

                // just remove the part that we parsed: don't extract the new substring using script length
                // as mb_strlen is \theta(pos)  (it's linear in the position)
                $pos += strlen($currentMatch);
                $line += mb_substr_count($currentMatch, "\n");
            } else {
                $this->tokens[] = new SieveToken(SieveToken::UNKNOWN, '', $line);

                return;
            }
        }

        $this->tokens[] = new SieveToken(SieveToken::SCRIPT_END, '', $line);
    }

    /**
     * Get current token.
     *
     * @return SieveToken|null
     */
    public function getCurrentToken(): ?SieveToken
    {
        return $this->tokens[$this->tokenPos - 1] ?? null;
    }

    /**
     * Check if next token is of given type.
     *
     * @param int $type
     * @return bool
     */
    public function nextTokenIs(int $type): bool
    {
        return $this->peekNextToken()->is($type);
    }

    /**
     * Check is current token is of type.
     *
     * @param $type
     * @return bool
     */
    public function currentTokenIs(int $type): bool
    {
        $currentToken = $this->getCurrentToken();
        return isset($currentToken) ? $currentToken->is($type) : false;
    }

    /**
     * Peek next token. (not moving the position)
     *
     * @return SieveToken
     */
    public function peekNextToken(): SieveToken
    {
        $offset = 0;
        do {
            $next = $this->tokens[$this->tokenPos + $offset++];
        } while ($next->is(SieveToken::COMMENT | SieveToken::WHITESPACE));

        return $next;
    }

    /**
     * Get next token. (and moving the position)
     *
     * @return SieveToken
     */
    public function nextToken(): SieveToken
    {
        $token = $this->tokens[$this->tokenPos++];

        while ($token->is(SieveToken::COMMENT | SieveToken::WHITESPACE)) {
            if (isset($this->ptFn)) {
                ($this->ptFn)($token);
            }

            $token = $this->tokens[$this->tokenPos++];
        }

        return $token;
    }

    protected $ptFn = null;
    protected $tokenPos = 0;
    protected $tokens = [];
    protected $tokenMatch = [
        SieveToken::LEFT_BRACKET       =>  '\[',
        SieveToken::RIGHT_BRACKET      =>  '\]',
        SieveToken::BLOCK_START        =>  '\{',
        SieveToken::BLOCK_END          =>  '\}',
        SieveToken::LEFT_PARENTHESIS   =>  '\(',
        SieveToken::RIGHT_PARENTHESIS  =>  '\)',
        SieveToken::COMMA             =>  ',',
        SieveToken::SEMICOLON         =>  ';',
        SieveToken::WHITESPACE        =>  '[ \r\n\t]+',
        SieveToken::TAG               =>  ':[[:alpha:]_][[:alnum:]_]*(?=\b)',
        /*
        "                           # match a quotation mark
        (                           # start matching parts that include an escaped quotation mark
        ([^"]*[^"\\\\])             # match a string without quotation marks and not ending with a backlash
        ?                           # this also includes the empty string
        (\\\\\\\\)*                 # match any groups of even number of backslashes
                                    # (thus the character after these groups are not escaped)
        \\\\"                       # match an escaped quotation mark
        )*                          # accept any number of strings that end with an escaped quotation mark
        [^"]*                       # accept any trailing part that does not contain any quotation marks
        "                           # end of the quoted string
        */
        SieveToken::QUOTED_STRING      =>  '"(([^"]*[^"\\\\])?(\\\\\\\\)*\\\\")*[^"]*"',
        SieveToken::NUMBER            =>  '[[:digit:]]+(?:[KMG])?(?=\b)',
        SieveToken::COMMENT           =>  '(?:\/\*(?:[^\*]|\*(?=[^\/]))*\*\/|#[^\r\n]*\r?(\n|$))',
        SieveToken::MULTILINE_STRING =>
            'text:[ \t]*(?:#[^\r\n]*)?\r?\n(\.[^\r\n]+\r?\n|[^\.][^\r\n]*\r?\n)*\.\r?(\n|$)',
        SieveToken::IDENTIFIER        =>  '[[:alpha:]_][[:alnum:]_]*(?=\b)',
        SieveToken::UNKNOWN           =>  '[^ \r\n\t]+',
    ];
}
