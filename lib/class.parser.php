<?php

include_once 'class.tree.php';
include_once 'class.scanner.php';
include_once 'class.semantics.php';
include_once 'class.exception.php';

class Parser
{
	protected $scanner_;
	protected $script_;
	protected $tree_;
	protected $status_;

	public function __construct($script = null)
	{
		if (isset($script))
			$this->parse($script);
	}

	public function dumpParseTree()
	{
		return $this->tree_->dump();
	}

	public function getScriptText()
	{
		return $this->tree_->getText();
	}

	protected function getPrevToken_($parent_id)
	{
		$childs = $this->tree_->getChilds($parent_id);

		for ($i = count($childs); $i > 0; --$i)
		{
			$prev = $this->tree_->getNode($childs[$i-1]);
			if ($prev->is(Token::Comment|Token::Whitespace))
				continue;

			// use command owning a block or list instead of previous
			if ($prev->is(Token::BlockStart|Token::Comma|Token::LeftParenthesis))
				$prev = $this->tree_->getNode($parent_id);

			return $prev;
		}

		return $this->tree_->getNode($parent_id);
	}

	/*******************************************************************************
	 * methods for recursive descent start below
	 */

	public function commentOrWhitespace_($token)
	{
		$this->tree_->addChild($token);
	}

	public function parse($script)
	{
		$this->script_ = $script;

		$this->scanner_ = new Scanner($this->script_);
		$this->scanner_->setPassthroughFunc(array($this, 'commentOrWhitespace_'));
		$this->tree_ = new Tree('parse tree');

		$this->commands_($this->tree_->getRoot());

		if (!$this->scanner_->nextTokenIs(Token::ScriptEnd))
			throw new SieveException($token, Token::ScriptEnd);
	}

	protected function commands_($parent_id)
	{
		while (true)
		{
			if (!$this->scanner_->nextTokenIs(Token::Identifier))
				break;

			// Get and check a command token
			$token = $this->scanner_->nextToken();
			$semantics = new Semantics($token, $this->getPrevToken_($parent_id));

			// Process eventual arguments
			$this_node = $this->tree_->addChildTo($parent_id, $token);
			$this->arguments_($this_node, $semantics);

			$token = $this->scanner_->nextToken();
			if (!$token->is(Token::Semicolon))
			{
				// TODO: check if/when semcheck is needed here
				$semantics->validateToken($token);

				if ($token->is(Token::BlockStart))
				{
					$this->tree_->addChildTo($this_node, $token);
					$this->block_($this_node, $semantics);
					continue;
				}

				throw new SieveException($token, Token::Semicolon);
			}

			$semantics->done($token);
			$this->tree_->addChildTo($this_node, $token);
		}
	}

	protected function arguments_($parent_id, &$semantics)
	{
		while (true)
		{
			if ($this->scanner_->nextTokenIs(Token::Number|Token::Tag))
			{
				// Check if semantics allow a number or tag
				$token = $this->scanner_->nextToken();
				$semantics->validateToken($token);
				$this->tree_->addChildTo($parent_id, $token);
			}
			else if ($this->scanner_->nextTokenIs(Token::StringList))
			{
				$this->stringlist_($parent_id, &$semantics);
			}
			else
			{
				break;
			}
		}

		if ($this->scanner_->nextTokenIs(Token::TestList))
		{
			$this->testlist_($parent_id, $semantics);
		}
	}

	protected function stringlist_($parent_id, &$semantics)
	{
		if (!$this->scanner_->nextTokenIs(Token::LeftBracket))
		{
			$this->string_($parent_id, &$semantics);
			return;
		}

		$token = $this->scanner_->nextToken();
		$semantics->startStringList($token);
		$this->tree_->addChildTo($parent_id, $token);

		do
		{
			$this->string_($parent_id, &$semantics);
			$token = $this->scanner_->nextToken();

			if (!$token->is(Token::Comma|Token::RightBracket))
				throw new SieveException($token, array(Token::Comma, Token::RightBracket));

			if ($token->is(Token::Comma))
				$semantics->continueStringList();

			$this->tree_->addChildTo($parent_id, $token);
		}
		while (!$token->is(Token::RightBracket));

		$semantics->endStringList();
	}

	protected function string_($parent_id, &$semantics)
	{
		$token = $this->scanner_->nextToken();
		$semantics->validateToken($token);
		$this->tree_->addChildTo($parent_id, $token);
	}

	protected function testlist_($parent_id, &$semantics)
	{
		if (!$this->scanner_->nextTokenIs(Token::LeftParenthesis))
		{
			$this->test_($parent_id, $semantics);
			return;
		}

		$token = $this->scanner_->nextToken();
		$semantics->validateToken($token);
		$this->tree_->addChildTo($parent_id, $token);

		do
		{
			$this->test_($parent_id, $semantics);

			$token = $this->scanner_->nextToken();
			if (!$token->is(Token::Comma|Token::RightParenthesis))
			{
				throw new SieveException($token, array(Token::Comma, Token::RightParenthesis));
			}
			$this->tree_->addChildTo($parent_id, $token);
		}
		while (!$token->is(Token::RightParenthesis));
	}

	protected function test_($parent_id, &$semantics)
	{
		// Check if semantics allow an identifier
		$token = $this->scanner_->nextToken();
		$semantics->validateToken($token);

		// Get semantics for this test command
		$this_semantics = new Semantics($token, $this->getPrevToken_($parent_id));
		$this_node = $this->tree_->addChildTo($parent_id, $token);

		// Consume eventual argument tokens
		$this->arguments_($this_node, $this_semantics);

		// Check that all required arguments were there
		$token = $this->scanner_->peekNextToken();
		$this_semantics->done($token);
	}

	protected function block_($parent_id, &$semantics)
	{
		$this->commands_($parent_id, $semantics);

		$token = $this->scanner_->nextToken();
		if (!$token->is(Token::BlockEnd))
		{
			throw new SieveException($token, Token::BlockEnd);
		}
		$this->tree_->addChildTo($parent_id, $token);
	}
}

?>
