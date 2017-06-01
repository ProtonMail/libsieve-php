<?php namespace Sieve;

include_once('SieveToken.php');

class SieveScanner
{
    public function __construct(&$script)
    {
        if ($script === null)
            return;

        $this->tokenize($script);
    }

    public function setPassthroughFunc($callback)
    {
        if ($callback == null || is_callable($callback))
            $this->ptFn_ = $callback;
    }

    public function tokenize(&$script)
    {
        $pos = 0;
        $line = 1;

        $script_length = mb_strlen($script);

        while ($pos < $script_length)
        {
            foreach ($this->tokenMatch_ as $type => $regex)
            {
                if (preg_match('/^'. $regex .'/', mb_substr($script, $pos), $match))
                {
                    array_push($this->tokens_, new SieveToken($type, $match[0], $line));

                    if ($type == SieveToken::Unknown)
                        return;

                    $pos += mb_strlen($match[0]);
                    $line += mb_substr_count($match[0], "\n");
                    break;
                }
            }
        }

        array_push($this->tokens_, new SieveToken(SieveToken::ScriptEnd, '', $line));
    }

    public function nextTokenIs($type)
    {
        return $this->peekNextToken()->is($type);
    }

    public function peekNextToken()
    {
        $offset = 0;
        do {
            $next = $this->tokens_[$this->tokenPos_ + $offset++];
        } while ($next->is(SieveToken::Comment|SieveToken::Whitespace));

        return $next;
    }

    public function nextToken()
    {
        $token = $this->tokens_[$this->tokenPos_++];

        while ($token->is(SieveToken::Comment|SieveToken::Whitespace))
        {
            if ($this->ptFn_ != null)
                call_user_func($this->ptFn_, $token);

            $token = $this->tokens_[$this->tokenPos_++];
        }

        return $token;
    }

    protected $ptFn_ = null;
    protected $tokenPos_ = 0;
    protected $tokens_ = array();
    protected $tokenMatch_ = array (
        SieveToken::LeftBracket       =>  '\[',
        SieveToken::RightBracket      =>  '\]',
        SieveToken::BlockStart        =>  '\{',
        SieveToken::BlockEnd          =>  '\}',
        SieveToken::LeftParenthesis   =>  '\(',
        SieveToken::RightParenthesis  =>  '\)',
        SieveToken::Comma             =>  ',',
        SieveToken::Semicolon         =>  ';',
        SieveToken::Whitespace        =>  '[ \r\n\t]+',
        SieveToken::Tag               =>  ':[[:alpha:]_][[:alnum:]_]*(?=\b)',
        SieveToken::QuotedString      =>  '"(.*?[^\\])??((\\\\)+)?+"',
        SieveToken::Number            =>  '[[:digit:]]+(?:[KMG])?(?=\b)',
        SieveToken::Comment           =>  '(?:\/\*(?:[^\*]|\*(?=[^\/]))*\*\/|#[^\r\n]*\r?(\n|$))',
        SieveToken::MultilineString   =>  'text:[ \t]*(?:#[^\r\n]*)?\r?\n(\.[^\r\n]+\r?\n|[^\.][^\r\n]*\r?\n)*\.\r?(\n|$)',
        SieveToken::Identifier        =>  '[[:alpha:]_][[:alnum:]_]*(?=\b)',
        SieveToken::Unknown           =>  '[^ \r\n\t]+'
    );
}
