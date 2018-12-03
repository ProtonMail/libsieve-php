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
        switch ($type) {
            case SieveToken::IDENTIFIER:
                return 'identifier';
            case SieveToken::WHITESPACE:
                return 'whitespace';
            case SieveToken::QUOTED_STRING:
                return 'quoted string';
            case SieveToken::TAG:
                return 'tag';
            case SieveToken::SEMICOLON:
                return 'semicolon';
            case SieveToken::LEFT_BRACKET:
                return 'left bracket';
            case SieveToken::RIGHT_BRACKET:
                return 'right bracket';
            case SieveToken::BLOCK_START:
                return 'block start';
            case SieveToken::BLOCK_END:
                return 'block end';
            case SieveToken::LEFT_PARENTHESIS:
                return 'left parenthesis';
            case SieveToken::RIGHT_PARENTHESIS:
                return 'right parenthesis';
            case SieveToken::COMMA:
                return 'comma';
            case SieveToken::NUMBER:
                return 'number';
            case SieveToken::COMMENT:
                return 'comment';
            case SieveToken::MULTILINE_STRING:
                return 'multiline string';
            case SieveToken::SCRIPT_END:
                return 'script end';
            case SieveToken::STRING:
                return 'string';
            case SieveToken::STRING_LIST:
                return 'string list';
            default:
                return 'unknown token';
        }
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
