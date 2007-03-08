<?php

$requires_ = array();

class Semantics
{
	var $command_;
	var $comparator_;
	var $matchType_;
	var $s_;
	var $unknown;
	var $message;
	var $nonTestCommands_ = '(require|if|elsif|else|reject|fileinto|redirect|stop|keep|discard|mark|unmark|setflag|addflag|removeflag)';
	var $testCommands_ = '(address|envelope|header|size|allof|anyof|exists|not|true|false)';
	var $requireStrings_ = '(envelope|fileinto|reject|vacation|relational|subaddress|regex|imapflags|copy)';

	function Semantics($command)
	{
		$this->command_ = $command;
		$this->unknown = false;
		switch ($command)
		{

		/********************
		 * control commands
		 */
		case 'require':
			/* require <capabilities: string-list> */
			$this->s_ = array(
				'valid_after' => '(script-start|require)',
				'arguments' => array(
					array('class' => 'string', 'list' => true, 'name' => 'require-string', 'occurrences' => '1', 'call' => 'setRequire_', 'values' => array(
						array('occurrences' => '+', 'regex' => '"'. $this->requireStrings_ .'"'),
						array('occurrences' => '+', 'regex' => '"comparator-i;(octet|ascii-casemap|ascii-numeric)"')
					))
				)
			);
			break;

		case 'if':
			/* if <test> <block> */
			$this->s_ = array(
				'valid_after' => str_replace('(', '(script-start|', $this->nonTestCommands_),
				'arguments' => array(
					array('class' => 'identifier', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => $this->testCommands_, 'name' => 'test')
					)),
					array('class' => 'block-start', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '{', 'name' => 'block')
					))
				)
			);
			break;

		case 'elsif':
			/* elsif <test> <block> */
			$this->s_ = array(
				'valid_after' => '(if|elsif)',
				'arguments' => array(
					array('class' => 'identifier', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => $this->testCommands_, 'name' => 'test')
					)),
					array('class' => 'block-start', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '{', 'name' => 'block')
					))
				)
			);
			break;

		case 'else':
			/* else <block> */
			$this->s_ = array(
				'valid_after' => '(if|elsif)',
				'arguments' => array(
					array('class' => 'block-start', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '{', 'name' => 'block')
					))
				)
			);
			break;


		/*******************
		 * action commands
		 */
		case 'discard':
		case 'keep':
		case 'stop':
			/* discard / keep / stop */
			$this->s_ = array(
				'valid_after' => str_replace('(', '(script-start|', $this->nonTestCommands_)
			);
			break;

		case 'fileinto':
			/* fileinto [":copy"] <folder: string> */
			$this->s_ = array(
				'requires' => 'fileinto',
				'valid_after' => $this->nonTestCommands_,
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '?', 'values' => array(
						array('occurrences' => '?', 'regex' => ':copy', 'requires' => 'copy', 'name' => 'copy')
					)),
					array('class' => 'string', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '".*"', 'name' => 'folder')
					))
				)
			);
			break;

		case 'mark':
		case 'unmark':
			/* mark / unmark */
			$this->s_ = array(
				'requires' => 'imapflags',
				'valid_after' => $this->nonTestCommands_
			);
			break;

		case 'redirect':
			/* redirect [":copy"] <address: string> */
			$this->s_ = array(
				'valid_after' => str_replace('(', '(script-start|', $this->nonTestCommands_),
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '?', 'values' => array(
						array('occurrences' => '?', 'regex' => ':copy', 'requires' => 'copy', 'name' => 'size-type')
					)),
					array('class' => 'string', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '".*"', 'name' => 'address')
					))
				)
			);
			break;

		case 'reject':
			/* reject <reason: string> */
			$this->s_ = array(
				'requires' => 'reject',
				'valid_after' => $this->nonTestCommands_,
				'arguments' => array(
					array('class' => 'string', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '".*"', 'name' => 'reason')
					))
				)
			);
			break;

		case 'setflag':
		case 'addflag':
		case 'removeflag':
			/* setflag <flag-list: string-list> */
			/* addflag <flag-list: string-list> */
			/* removeflag <flag-list: string-list> */
			$this->s_ = array(
				'requires' => 'imapflags',
				'valid_after' =>$this->nonTestCommands_,
				'arguments' => array(
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'key')
					))
				)
			);
			break;

		case 'vacation':
			/* vacation [":days" number] [":addresses" string-list] [":subject" string] [":mime"] <reason: string> */
			$this->s_ = array(
				'requires' => 'vacation',
				'valid_after' => $this->nonTestCommands_,
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '*', 'values' => array(
						array('occurrences' => '?', 'regex' => ':days', 'name' => 'days',
							'add' => array(
								array('class' => 'number', 'occurrences' => '1', 'values' => array(
									array('occurrences' => '1', 'regex' => '.*', 'name' => 'period')
								))
							)
						),
						array('occurrences' => '?', 'regex' => ':addresses', 'name' => 'addresses',
							'add' => array(
								array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
									array('occurrences' => '+', 'regex' => '".*"', 'name' => 'address')
								))
							)
						),
						array('occurrences' => '?', 'regex' => ':subject', 'name' => 'subject',
							'add' => array(
								array('class' => 'string', 'occurrences' => '1', 'values' => array(
									array('occurrences' => '1', 'regex' => '".*"', 'name' => 'subject')
								))
							)
						),
						array('occurrences' => '?', 'regex' => ':mime', 'name' => 'mime')
					)),
					array('class' => 'string', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '".*"', 'name' => 'reason')
					))
				)
			);
			break;


		/*****************
		 * test commands
		 */
		case 'address':
			/* address [address-part: tag] [comparator: tag] [match-type: tag] <header-list: string-list> <key-list: string-list> */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '*', 'post-call' => 'checkTags_', 'values' => array(
						array('occurrences' => '?', 'regex' => ':(is|contains|matches|count|value|regex)', 'call' => 'setMatchType_', 'name' => 'match-type'),
						array('occurrences' => '?', 'regex' => ':(all|localpart|domain|user|detail)', 'call' => 'checkAddrPart_', 'name' => 'address-part'),
						array('occurrences' => '?', 'regex' => ':comparator', 'name' => 'comparator',
							'add' => array(
								array('class' => 'string', 'occurrences' => '1', 'call' => 'setComparator_', 'values' => array(
									array('occurrences' => '1', 'regex' => '"i;(octet|ascii-casemap)"', 'name' => 'comparator-string'),
									array('occurrences' => '1', 'regex' => '"i;ascii-numeric"', 'requires' => 'comparator-i;ascii-numeric', 'name' => 'comparator-string')
								))
							)
						)
					)),
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'header')
					)),
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'key')
					))
				)
			);
			break;

		case 'allof':
		case 'anyof':
			/* allof <tests: test-list>
			   anyof <tests: test-list> */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'left-parant', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '\(', 'name' => 'test-list')
					)),
					array('class' => 'identifier', 'occurrences' => '+', 'values' => array(
						array('occurrences' => '+', 'regex' => $this->testCommands_, 'name' => 'test')
					))
				)
			);
			break;

		case 'envelope':
			/* envelope [address-part: tag] [comparator: tag] [match-type: tag] <envelope-part: string-list> <key-list: string-list> */
			$this->s_ = array(
				'requires' => 'envelope',
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '*', 'post-call' => 'checkTags_', 'values' => array(
						array('occurrences' => '?', 'regex' => ':(is|contains|matches|count|value|regex)', 'call' => 'setMatchType_', 'name' => 'match-type'),
						array('occurrences' => '?', 'regex' => ':(all|localpart|domain|user|detail)', 'call' => 'checkAddrPart_', 'name' => 'address-part'),
						array('occurrences' => '?', 'regex' => ':comparator', 'name' => 'comparator',
							'add' => array(
								array('class' => 'string', 'occurrences' => '1', 'call' => 'setComparator_', 'values' => array(
									array('occurrences' => '1', 'regex' => '"i;(octet|ascii-casemap)"', 'name' => 'comparator-string'),
									array('occurrences' => '1', 'regex' => '"i;ascii-numeric"', 'requires' => 'comparator-i;ascii-numeric', 'name' => 'comparator-string')
								))
							)
						)
					)),
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'envelope-part')
					)),
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'key')
					))
				)
			);
			break;

		case 'exists':
			/* exists <header-names: string-list> */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'header')
					))
				)
			);
			break;

		case 'header':
			/* header [comparator: tag] [match-type: tag] <header-names: string-list> <key-list: string-list> */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '*', 'post-call' => 'checkTags_', 'values' => array(
						array('occurrences' => '?', 'regex' => ':(is|contains|matches|count|value|regex)', 'call' => 'setMatchType_', 'name' => 'match-type'),
						array('occurrences' => '?', 'regex' => ':comparator', 'name' => 'comparator',
							'add' => array(
								array('class' => 'string', 'occurrences' => '1', 'call' => 'setComparator_', 'values' => array(
									array('occurrences' => '1', 'regex' => '"i;(octet|ascii-casemap)"', 'name' => 'comparator-string'),
									array('occurrences' => '1', 'regex' => '"i;ascii-numeric"', 'requires' => 'comparator-i;ascii-numeric', 'name' => 'comparator-string')
								))
							)
						)
					)),
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'header')
					)),
					array('class' => 'string', 'list' => true, 'occurrences' => '1', 'values' => array(
						array('occurrences' => '+', 'regex' => '".*"', 'name' => 'key')
					))
				)
			);
			break;

		case 'not':
			/* not <test> */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'identifier', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => $this->testCommands_, 'name' => 'test')
					))
				)
			);
			break;

		case 'size':
			/* size <":over" / ":under"> <limit: number> */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not'),
				'arguments' => array(
					array('class' => 'tag', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => ':(over|under)', 'name' => 'size-type')
					)),
					array('class' => 'number', 'occurrences' => '1', 'values' => array(
						array('occurrences' => '1', 'regex' => '.*', 'name' => 'limit')
					))
				)
			);
			break;

		case 'true':
		case 'false':
			/* true / false */
			$this->s_ = array(
				'valid_after' => array('if', 'elsif', 'anyof', 'allof', 'not')
			);
			break;


		/********************
		 * unknown commands
		 */
		default:
			$this->unknown = true;
		}
	}

	function setRequire_($text)
	{
		global $requires_;
		array_push($requires_, $text);
		return true;
	}

	function setMatchType_($text)
	{
		global $requires_;
		// Do special processing for relational test extension
		if ($text == ':count' || $text == ':value')
		{
			if (!in_array('"relational"', $requires_))
			{
				$this->message = 'missing require for match-type '. $text;
				return false;
			}

			array_unshift($this->s_['arguments'],
				array('class' => 'string', 'occurrences' => '1', 'values' => array(
					array('occurrences' => '1', 'regex' => '"(lt|le|eq|ge|gt|ne)"', 'name' => 'relation-string'),
				))
			);
		}
		// Do special processing for regex match-type extension
		else if ($text == ':regex' && !in_array('"regex"', $requires_))
		{
			$this->message = 'missing require for match-type '. $text;
			return false;
		}
		$this->matchType_ = $text;
		return true;
	}

	function setComparator_($text)
	{
		$this->comparator_ = $text;
		return true;
	}

	function checkAddrPart_($text)
	{
		if ($text == ':user' || $text == ':detail')
		{
			global $requires_;
			if (!in_array('"subaddress"', $requires_))
			{
				$this->message = 'missing require for tag '. $text;
				return false;
			}
		}
		return true;
	}

	function checkTags_()
	{
		if (isset($this->matchType_) &&
		    $this->matchType_ == ':count' &&
		    $this->comparator_ != '"i;ascii-numeric"')
		{
			$this->message = 'match-type :count needs comparator i;ascii-numeric';
			return false;
		}
		return true;
	}

	function validCommand($prev, $line)
	{
		// Check if command is known
		if ($this->unknown)
		{
			$this->message = 'line '. $line .': unknown command "'. $this->command_ .'"';
			return false;
		}

		// Check if the command needs to be required
		global $requires_;
		if (isset($this->s_['requires']) &&
		    !in_array('"'. $this->s_['requires'] .'"', $requires_))
		{
			$this->message = 'line '. $line .': missing require for command "'. $this->command_ .'"';
			return false;
		}

		// Check if command may appear here
		if (!ereg($this->s_['valid_after'], $prev))
		{
			$this->message = 'line '. $line .': "'. $this->command_ .'" may not appear after "'. $prev .'"';
			return false;
		}

		return true;
	}

	function validClass_($class, $id)
	{
		// Check if command expects any arguments
		if (!isset($this->s_['arguments']))
		{
			$this->message = $id .' where semicolon expected';
			return false;
		}

		foreach ($this->s_['arguments'] as $arg)
		{
			if ($class == $arg['class'])
			{
				return true;
			}

			// Is the argument required
			if ($arg['occurrences'] != '?' && $arg['occurrences'] != '*')
			{
				$this->message = $id .' where '. $arg['class'] .' expected';
				return false;
			}

			if (isset($arg['post-call']) &&
				!call_user_func(array(&$this, $arg['post-call'])))
			{
				return false;
			}
			array_shift($this->s_['arguments']);
		}

		$this->message = 'unexpected '. $id;
		return false;
	}

	function startStringList($line)
	{
		if (!$this->validClass_('string', 'string'))
		{
			$this->message = 'line '. $line .': '. $this->message;
			return false;
		}
		else if (!isset($this->s_['arguments'][0]['list']))
		{
			$this->message = 'line '. $line .': '. 'left bracket where '. $this->s_['arguments'][0]['class'] .' expected';
			return false;
		}

		$this->s_['arguments'][0]['occurrences'] = '+';
		return true;
	}

	function endStringList()
	{
		array_shift($this->s_['arguments']);
	}

	function validToken($class, &$text, &$line)
	{
		global $requires_;
		$name = $class . ($class != $text ? " $text" : '');

		// Make sure the argument has a valid class
		if (!$this->validClass_($class, $name))
		{
			$this->message = 'line '. $line .': '. $this->message;
			return false;
		}

		$arg = &$this->s_['arguments'][0];
		foreach ($arg['values'] as $val)
		{
			if (preg_match('/^'. $val['regex'] .'$/m', $text))
			{
				// Check if the argument value needs a 'require'
				if (isset($val['requires']) &&
					!in_array('"'.$val['requires'].'"', $requires_))
				{
					$this->message = 'line '. $line .': missing require for '. $val['name'] .' '. $text;
					return false;
				}

				// Check if a possible value of this argument may occur
				if ($val['occurrences'] == '?' || $val['occurrences'] == '1')
				{
					$val['occurrences'] = '0';
				}
				else if ($val['occurrences'] == '+')
				{
					$val['occurrences'] = '*';
				}
				else if ($val['occurrences'] == '0')
				{
					$this->message = 'line '. $line .': too many '. $val['name'] .' '. $class .'s near '. $text;
					return false;
				}

				// Call extra processing function if defined
				if (isset($val['call']) && !call_user_func(array(&$this, $val['call']), $text) ||
					isset($arg['call']) && !call_user_func(array(&$this, $arg['call']), $text))
				{
					$this->message = 'line '. $line .': '. $this->message;
					return false;
				}

				// Set occurrences appropriately
				if ($arg['occurrences'] == '?' || $arg['occurrences'] == '1')
				{
					array_shift($this->s_['arguments']);
				}
				else
				{
					$arg['occurrences'] = '*';
				}

				// Add argument(s) expected to follow right after this one
				if (isset($val['add']))
				{
					while ($add_arg = array_pop($val['add']))
					{
						array_unshift($this->s_['arguments'], $add_arg);
					}
				}

				return true;
			}
		}

		$this->message = 'line '. $line .': unexpected '. $name;
		return false;
	}

	function done($class, $text, $line)
	{
		if (isset($this->s_['arguments']))
		{
			foreach ($this->s_['arguments'] as $arg)
			{
				if ($arg['occurrences'] == '+' || $arg['occurrences'] == '1')
				{
					$this->message = 'line '. $line .': '. $class .' '. $text .' where '. $arg['class'] .' expected';
					return false;
				}
			}
		}
		return true;
	}
}

?>