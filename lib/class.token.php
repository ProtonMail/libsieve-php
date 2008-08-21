<?php

include_once('iface.dumpable.php');

class Token implements Dumpable
{
	const ScriptStart      = 0x00000;
	const ScriptEnd        = 0x00001;
	const Unknown          = 0x00002;
	const LeftBracket      = 0x00004;
	const RightBracket     = 0x00008;
	const BlockStart       = 0x00010;
	const BlockEnd         = 0x00020;
	const LeftParenthesis  = 0x00040;
	const RightParenthesis = 0x00080;
	const Comma            = 0x00100;
	const Semicolon        = 0x00200;
	const Whitespace       = 0x00400;
	const Tag              = 0x00800;
	const QuotedString     = 0x01000;
	const Number           = 0x02000;
	const Comment          = 0x04000;
	const MultilineString  = 0x08000;
	const Identifier       = 0x10000;

	const String        = 0x09000; // Quoted | Multiline
	const StringList    = 0x09004; // Quoted | Multiline | LeftBracket
	const StringListSep = 0x00108; // Comma | RightBracket
	const Unparsed      = 0x04400; // Comment | Whitespace
	const TestList      = 0x10040; // Identifier | LeftParenthesis

	public $type;
	public $text;
	public $line;

	public function __construct($type, $text, $line)
	{
		$this->text = $text;
		$this->type = $type;
		$this->line = intval($line);
	}

	public function dump()
	{
		return '<'. Token::escape($this->text) .'> type:'. Token::typeString($this->type) .' line:'. $this->line;
	}

	public function text()
	{
		return $this->text;
	}

	public function is($type)
	{
		return (bool)($this->type & $type);
	}

	public static function typeString($type)
	{
		switch ($type)
		{
		case Token::Identifier: return 'identifier';
		case Token::Whitespace: return 'whitespace';
		case Token::QuotedString: return 'quoted string';
		case Token::Tag: return 'tag';
		case Token::Semicolon: return 'semicolon';
		case Token::LeftBracket: return 'left bracket';
		case Token::RightBracket: return 'right bracket';
		case Token::BlockStart: return 'block start';
		case Token::BlockEnd: return 'block end';
		case Token::LeftParenthesis: return 'left parenthesis';
		case Token::RightParenthesis: return 'right parenthesis';
		case Token::Comma: return 'comma';
		case Token::Number: return 'number';
		case Token::Comment: return 'comment';
		case Token::MultilineString: return 'multiline string';
		case Token::ScriptStart: return 'script start';
		case Token::ScriptEnd: return 'script end';
		case Token::String: return 'string';
		case Token::StringList: return 'string list';
		default: return 'unknown token';
		}
	}

	protected static $tr_ = array("\r" => '\r', "\n" => '\n', "\t" => '\t');
	public static function escape($val)
	{
		return strtr($val, self::$tr_);
	}
}

?>