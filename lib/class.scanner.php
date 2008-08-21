<?php

include_once('class.token.php');

class Scanner
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
					array_push($this->tokens_, new Token($type, $match[0], $line));

					if ($type == Token::Unknown)
						return;

					$pos += mb_strlen($match[0]);
					$line += mb_substr_count($match[0], "\n");
					break;
				}
			}
		}

		array_push($this->tokens_, new Token(Token::ScriptEnd, '', $line));
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
		} while ($next->is(Token::Comment|Token::Whitespace));

		return $next;
	}

	public function nextToken()
	{
		$token = $this->tokens_[$this->tokenPos_++];

		while ($token->is(Token::Comment|Token::Whitespace))
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
		Token::LeftBracket       =>  '\[',
		Token::RightBracket      =>  '\]',
		Token::BlockStart        =>  '\{',
		Token::BlockEnd          =>  '\}',
		Token::LeftParenthesis   =>  '\(',
		Token::RightParenthesis  =>  '\)',
		Token::Comma             =>  ',',
		Token::Semicolon         =>  ';',
		Token::Whitespace        =>  '[ \r\n\t]+',
		Token::Tag               =>  ':[[:alpha:]_][[:alnum:]_]*(?=\b)',
		Token::QuotedString      =>  '"(?:\\[\\"]|[^\x00"])*"',
		Token::Number            =>  '[[:digit:]]+(?:[KMG])?(?=\b)',
		Token::Comment           =>  '(?:\/\*(?:[^\*]|\*(?=[^\/]))*\*\/|#[^\r\n]*\r?(\n|$))',
		Token::MultilineString   =>  'text:[ \t]*(?:#[^\r\n]*)?\r?\n(\.[^\r\n]+\r?\n|[^\.]*\r?\n)*\.\r?(\n|$)',
		Token::Identifier        =>  '[[:alpha:]_][[:alnum:]_]*(?=\b)',
		Token::Unknown           =>  '[^ \r\n\t]+'
	);
}

?>
