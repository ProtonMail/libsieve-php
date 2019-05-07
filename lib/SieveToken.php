<?php

declare(strict_types=1);

namespace Sieve;

class SieveToken implements SieveDumpable
{
    public const UNKNOWN = 0x0000;
    public const SCRIPT_END = 0x0001;
    public const LEFT_BRACKET = 0x0002;
    public const RIGHT_BRACKET = 0x0004;
    public const BLOCK_START = 0x0008;
    public const BLOCK_END = 0x0010;
    public const LEFT_PARENTHESIS = 0x0020;
    public const RIGHT_PARENTHESIS = 0x0040;
    public const COMMA = 0x0080;
    public const SEMICOLON = 0x0100;
    public const WHITESPACE = 0x0200;
    public const TAG = 0x0400;
    public const QUOTED_STRING = 0x0800;
    public const NUMBER = 0x1000;
    public const COMMENT = 0x2000;
    public const MULTILINE_STRING = 0x4000;
    public const IDENTIFIER = 0x8000;

    public const STRING = 0x4800; // Quoted | Multiline
    public const STRING_LIST = 0x4802; // Quoted | Multiline | LeftBracket
    public const STRING_LIST_SEP = 0x0084; // Comma | RightBracket
    public const UNPARSED = 0x2200; // Comment | Whitespace
    public const TEST_LIST = 0x8020; // Identifier | LeftParenthesis

    public $type;
    public $text;
    public $line;

    protected static $tr_ = ["\r" => '\r', "\n" => '\n', "\t" => '\t'];

    protected const TYPE_STR = [
        SieveToken::IDENTIFIER => 'identifier',
        SieveToken::WHITESPACE => 'whitespace',
        SieveToken::QUOTED_STRING => 'quoted string',
        SieveToken::TAG => 'tag',
        SieveToken::SEMICOLON => 'semicolon',
        SieveToken::LEFT_BRACKET => 'left bracket',
        SieveToken::RIGHT_BRACKET => 'right bracket',
        SieveToken::BLOCK_START => 'block start',
        SieveToken::BLOCK_END => 'block end',
        SieveToken::LEFT_PARENTHESIS => 'left parenthesis',
        SieveToken::RIGHT_PARENTHESIS => 'right parenthesis',
        SieveToken::COMMA => 'comma',
        SieveToken::NUMBER => 'number',
        SieveToken::COMMENT => 'comment',
        SieveToken::MULTILINE_STRING => 'multiline string',
        SieveToken::SCRIPT_END => 'script end',
        SieveToken::STRING => 'string',
        SieveToken::STRING_LIST => 'string list',
    ];

    /**
     * SieveToken constructor.
     *
     * @param int $type
     * @param string $text
     * @param int $line
     */
    public function __construct(int $type, string $text, int $line)
    {
        $this->text = $text;
        $this->type = $type;
        $this->line = $line;
    }

    /**
     * Dump the current token.
     *
     * @return string
     */
    public function dump(): string
    {
        return '<' . SieveToken::escape($this->text) . '> type:' . SieveToken::typeString(
            $this->type
        ) . ' line:' . $this->line;
    }

    /**
     * Get the Sieve Text of the current token.
     *
     * @return string
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Check if a token type is the given type.
     *
     * @param int $type
     * @return bool
     */
    public function is(int $type): bool
    {
        return (bool) ($this->type & $type);
    }

    /**
     * Get the string value of a Type.
     *
     * @param int $type
     * @return string
     */
    public static function typeString(int $type): string
    {
        return static::TYPE_STR[$type] ?? 'unknown token';
    }

    /**
     * Escapes a value.
     *
     * @param string $val
     * @return string
     */
    public static function escape(string $val): string
    {
        return strtr($val, self::$tr_);
    }
}
