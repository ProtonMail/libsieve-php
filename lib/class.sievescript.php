<?php

include_once 'class.tree.php';
include_once 'class.scanner.php';

/*
	requirestring: envelope, fileinto, reject, comparator-*
	address-part: localpart, domain, all*
	match-type: is*, contains, matches
	comperator: i;octet, i;ascii-casemap

	<if>		<test:+>		<block:1>
	<elsif>		<test:+>		<block:1>
	<else>		<block:1>
	<require>	<requirestring:+>
	<reject>	<string:?>
	<fileinto>	<string:1>
	<redirect>	<string:1>
	<stop>
	<keep>
	<discard>

	<address>	<address-part,comperator,match-type:?>	<string:+>	<string:+>
	<envelope>	<address-part,comperator,match-type:?>	<string:+>	<string:+>
	<header>	<comperator,match-type:?>	<string:+>	<string:+>
	<size>		<:over|:under:1>	<number:1>
	<allof>		<test:+>
	<anyof>		<test:+>
	<exists>	<string:+>
	<not>		<test:1>
	<true>
	<false>
*/

class SieveScript
{
	var $_scanner;
	var $_script;
	var $_tree;
	var $_status;
	var $_noMoreRequires;

	var $_test_identifier = array('address', 'allof', 'anyof', 'envelope', 'exists', 'false', 'header', 'not', 'size', 'true');

	var $status_text;

	function parse($script)
	{
		$this->status_text = "incomplete";

		$root = 'script_start';
		$this->_noMoreRequires = false;
		$this->_script = $script;
		$this->_tree = new Tree($root);
		$this->_tree->setDumpFunc(array($this, '_dumpToken'));
		$this->_scanner = new Scanner($this->_script);
		$this->_scanner->setCommentFunc(array($this, '_comment'));

		if ($this->_commands($this->_tree->getRoot()) &&
		    $this->_scanner->nextTokenIs('script-end'))
		{
			return $this->_success('success');
		}

		return $this->_status;
	}

	function _dumpToken(&$token)
	{
		if (is_array($token))
		{
			$str = "&lt;" . chop(mb_substr($this->_script, $token['pos'], $token['len'])) . "&gt; ";
			foreach ($token as $k => $v)
			{
				$str .= " $k:$v";
			}
			return $str;
		}

		return strval($token);
	}

	function _tokenStringIs($token, $text)
	{
		return mb_substr($this->_script, $token['pos'], $token['len']) == $text;
	}

	function _success($text = null)
	{
		if ($text != null)
		{
			$this->status_text = $text;
		}

		return $this->_status = true;
	}

	function _error($text, $token = null)
	{
		if ($token != null)
		{
			$text = 'line '. $token['line'] .': '. $token['class'] . " where $text expected near ".
			        '"'. mb_substr($this->_script, $token['pos'], $token['len']) .'"';
		}

		$this->status_text = $text;
		return $this->_status = false;
	}

	function _done()
	{
		return false;
	}

	function _comment($token)
	{
		$this->_tree->addChild($token);
	}

	function _commands($parent_id)
	{
		while ($this->_command($parent_id));

		return $this->_status;
	}

	function _command($parent_id)
	{
		if (!$this->_scanner->nextTokenIs('identifier'))
		{
			if ($this->_scanner->nextTokenIs(array('right-curly', 'script-end')))
			{
				return $this->_done();
			}
			return $this->_error('identifier', $this->_scanner->peekNextToken());
		}

		// Get and check a command token
		$token = $this->_scanner->nextToken();
		$command = mb_substr($this->_script, $token['pos'], $token['len']);

		if (!in_array($command, array('if', 'elsif', 'else', 'require', 'stop', 'reject', 'fileinto', 'redirect', 'keep', 'discard')))
		{
			return $this->_error('unknown command: '. $command);
		}

		if ($command != 'require')
		{
			$this->_noMoreRequires = true;
		}
		else if ($this->_noMoreRequires)
		{
			return $this->_error('misplaced require');
		}

		$this_node = $this->_tree->addChildTo($parent_id, $token);

		if (in_array($command, array('if', 'elsif', 'require', 'reject', 'fileinto', 'redirect')))
		{
			// TODO: handle optional arguments in reject
			if ($this->_arguments($this_node) == false)
			{
				return false;
			}
		}

		$token = $this->_scanner->nextToken();
		if (in_array($command, array('if', 'elsif', 'else')))
		{
			if ($token['class'] != 'left-curly')
			{
				return $this->_error('block', $token);
			}
			$this->_tree->addChildTo($this_node, $token);
			return $this->_block($this_node);
		}
		else if ($token['class'] != 'semicolon')
		{
			return $this->_error('semicolon', $token);
		}

		$this->_tree->addChildTo($this_node, $token);
		return $this->_success();
	}

	function _block($parent_id)
	{
		//TODO: test if cmd is ok w/ block
		if ($this->_commands($parent_id))
		{
			$token = $this->_scanner->nextToken();
	
			if ($token['class'] != 'right-curly')
			{
				return $this->_error('closing curly brace', $token);
			}
	
			$this->_tree->addChildTo($parent_id, $token);
			return $this->_success();
		}
		return $this->_status;
	}

	function _arguments($parent_id)
	{
		while ($this->_argument($parent_id));
		if ($this->_status == true)
		{
			$this->_testlist($parent_id);
		}
		return $this->_status;
	}

	function _argument($parent_id)
	{
		if (!$this->_scanner->nextTokenIs(array('number', 'tag')))
		{
			return $this->_stringlist($parent_id);
		}
		else
		{
			if ($this->_tokenStringIs($this->_tree->getNode($parent_id), 'require'))
			{
				return $this->_error('stringlist', $this->_scanner->nextToken());
			}
		}

		$token = $this->_scanner->nextToken();
		$this->_tree->addChildTo($parent_id, $token);

		return $this->_success();
	}

	function _test($parent_id)
	{
		if (!$this->_scanner->nextTokenIs('identifier'))
		{
			return $this->_done();
		}

		$token = $this->_scanner->nextToken();
		$this_node = $this->_tree->addChildTo($parent_id, $token);

		$this->_arguments($this_node);

		//TODO: check test for validity here

		return $this->_status;
	}

	function _testlist($parent_id)
	{
		if (!$this->_scanner->nextTokenIs('left-parant'))
		{
			return $this->_test($parent_id);
		}

		$token = $this->_scanner->nextToken();
		$this->_tree->addChildTo($parent_id, $token);

		while ($token['class'] != 'right-parant')
		{
			if (!$this->_test($parent_id))
			{
				return $this->_status;
			}

			$token = $this->_scanner->nextToken();

			if ($token['class'] != 'comma' && $token['class'] != 'right-parant')
			{
				return $this->_error('comma or closing paranthesis', $token);
			}

			$this->_tree->addChildTo($parent_id, $token);
		}

		return $this->_success();
	}

	function _string($parent_id)
	{
		if (!$this->_scanner->nextTokenIs(array('quoted-string', 'multi-line')))
		{
			return $this->_done();
		}

		$this->_tree->addChildTo($parent_id, $this->_scanner->nextToken());

		return $this->_success();
	}

	function _stringlist($parent_id)
	{
		if (!$this->_scanner->nextTokenIs('left-bracket'))
		{
			return $this->_string($parent_id);
		}

		$token = $this->_scanner->nextToken();
		$this->_tree->addChildTo($parent_id, $token);

		while ($token['class'] != 'right-bracket')
		{
			if (!$this->_string($parent_id))
			{
				return $this->_status;
			}

			$token = $this->_scanner->nextToken();

			if ($token['class'] != 'comma' && $token['class'] != 'right-bracket')
			{
				return $this->_error('comma or closing bracket', $token);
			}

			$this->_tree->addChildTo($parent_id, $token);
		}

		return $this->_success();
	}
}

?>
