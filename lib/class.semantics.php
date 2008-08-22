<?php

require_once('class.extensions.php');
require_once('class.token.php');
require_once('class.exception.php');

class Semantics
{
	protected static $requiredExtensions_ = array();

	protected $command_;
	protected $comparator_;
	protected $matchType_;
	protected $s_;
	protected $deps_ = array();
	protected $commands_ = 'require|if|elsif|else|redirect|stop|keep|discard';
	protected $testsValidAfter_ = '(if|elsif|anyof|allof|not)';
	protected $tests_ = 'address|header|size|allof|anyof|exists|not|true|false';
	protected $comparators_ = 'i;(octet|ascii-casemap)';
	protected $addressParts_ = 'all|localpart|domain';
	protected $matchTypes_ = 'is|contains|matches';

	public function __construct($token, $prevToken)
	{
		$this->registry_ = ExtensionRegistry::get();
		$this->command_ = strtolower($token->text);

		switch ($this->command_)
		{

		/********************
		 * control commands
		 */
		case 'require':
			/* require <capabilities: string-list> */
			$this->s_ = array(
				'valid_after' => '(_start_|require)',
				'arguments'   => array(
					array(
						'occurrence' => '1',
						'type'       => Token::StringList,
						'name'       => 'require string',
						'call'       => 'setRequire_',
						'regex'      => $this->requireStringsRegex_()
					)
				)
			);
			break;

		case 'if':
			/* if <test> <block> */
			$this->s_ = array(
				'valid_after' => str_replace('(', '(_start_|', $this->commandsRegex_()),
				'arguments'   => array(
					array(
						'type'       => Token::Identifier,
						'occurrence' => '1',
						'regex'      => $this->testsRegex_(),
						'name'       => 'test'
					), array(
						'type'       => Token::BlockStart,
						'occurrence' => '1',
						'regex'      => '{',
						'name'       => 'block'
					)
				)
			);
			break;

		case 'elsif':
			/* elsif <test> <block> */
			$this->s_ = array(
				'valid_after' => '(if|elsif)',
				'arguments'   => array(
					array(
						'type'       => Token::Identifier,
						'occurrence' => '1',
						'regex'      => $this->testsRegex_(),
						'name'       => 'test'
					), array(
						'type'       => Token::BlockStart,
						'occurrence' => '1',
						'regex'      => '{',
						'name'       => 'block'
					)
				)
			);
			break;

		case 'else':
			/* else <block> */
			$this->s_ = array(
				'valid_after' => '(if|elsif)',
				'arguments'   => array(
					array(
						'type'       => Token::BlockStart,
						'occurrence' => '1',
						'regex'      => '{',
						'name'       => 'block'
					)
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
				'valid_after' => str_replace('(', '(_start_|', $this->commandsRegex_()),
				'arguments'   => array()
			);
			break;

		case 'redirect':
			/* redirect <address: string> */
			$this->s_ = array(
				'valid_after' => str_replace('(', '(_start_|', $this->commandsRegex_()),
				'arguments'   => array(
					array(
						'type'       => Token::String,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'address'
					)
				)
			);
			break;


		/*****************
		 * test commands
		 */
		case 'address':
			/* address [address-part: tag] [comparator: tag] [match-type: tag] <header-list: string-list> <key-list: string-list> */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array(
					array(
						'type'       => Token::Tag,
						'occurrence' => '?',
						'regex'      => $this->matchTypeRegex_(),
						'call'       => 'matchTypeHook_',
						'name'       => 'match type'
					), array(
						'type'       => Token::Tag,
						'occurrence' => '?',
						'regex'      => $this->addressPartRegex_(),
						'name'       => 'address part'
					), array(
						'type'       => Token::Tag,
						'occurrence' => '?',
						'regex'      => 'comparator',
						'name'       => 'comparator',
						'subArgs'    => array( array(
							'type'       => Token::String,
							'occurrence' => '1',
							'call'       => 'comparatorHook_',
							'regex'      => $this->comparatorRegex_(),
							'name'       => 'comparator string'
						))
					), array(
						'type'       => Token::StringList,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'header'
					), array(
						'type'       => Token::StringList,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'key'
					)
				)
			);
			break;

		case 'allof':
		case 'anyof':
			/* allof <tests: test-list>
			   anyof <tests: test-list> */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array(
					array(
						'type'       => Token::LeftParenthesis,
						'occurrence' => '1',
						'regex'      => '\(',
						'name'       => 'test list'
					), array(
						'type'       => Token::Identifier,
						'occurrence' => '+',
						'regex'      => $this->testsRegex_(),
						'name'       => 'test'
					)
				)
			);
			break;

		case 'exists':
			/* exists <header-names: string-list> */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array(
					array(
						'type'       => Token::StringList,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'header'
					)
				)
			);
			break;

		case 'header':
			/* header [comparator: tag] [match-type: tag] <header-names: string-list> <key-list: string-list> */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array(
					array(
						'type'       => Token::Tag,
						'occurrence' => '?',
						'regex'      => $this->matchTypeRegex_(),
						'call'       => 'matchTypeHook_',
						'name'       => 'match type'
					), array(
						'type'       => Token::Tag,
						'occurrence' => '?',
						'regex'      => 'comparator',
						'name'       => 'comparator',
						'subArgs'    => array( array(
							'type'       => Token::String,
							'occurrence' => '1',
							'call'       => 'comparatorHook_',
							'regex'      => $this->comparatorRegex_(),
							'name'       => 'comparator string'
						))
					), array(
						'type'       => Token::StringList,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'header'
					), array(
						'type'       => Token::StringList,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'key'
					)
				)
			);
			break;

		case 'not':
			/* not <test> */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array(
					array(
						'type'       => Token::Identifier,
						'occurrence' => '1',
						'regex'      => $this->testsRegex_(),
						'name'       => 'test'
					)
				)
			);
			break;

		case 'size':
			/* size <":over" / ":under"> <limit: number> */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array(
					array(
						'type'       => Token::Tag,
						'occurrence' => '1',
						'regex'      => '(over|under)',
						'name'       => 'size type'
					), array(
						'type'       => Token::Number,
						'occurrence' => '1',
						'regex'      => '.*',
						'name'       => 'limit'
					)
				)
			);
			break;

		case 'true':
		case 'false':
			/* true / false */
			$this->s_ = array(
				'valid_after' => $this->testsValidAfter_,
				'arguments'   => array()
			);
			break;

		default:
			// Check for extension command
			if ($this->registry_->isCommand($this->command_))
			{
				$xml = $this->registry_->command($this->command_);
				$this->s_ = array(
					'valid_after' => $this->commandsRegex_(),
					'arguments'   => $this->makeArguments_($xml)
				);
				break;
			}

			// Check for extension test command
			if ($this->registry_->isTest($this->command_))
			{
				$xml = $this->registry_->test($this->command_);
				$this->s_ = array(
					'valid_after' => $this->testsValidAfter_,
					'arguments'   => $this->makeArguments_($xml)
				);
				break;
			}

			throw new SieveException($token, 'unknown command '. $this->command_);
		}

		// Check new extension arguments for the command
		foreach ($this->registry_->arguments($this->command_) as $arguments)
		{
			foreach ($arguments->parameter as $p)
			{
				switch ((string) $p['type'])
				{
				case 'tag':
					$tag = array(
						'type'       => Token::Tag,
						'occurrence' => $this->occurrence_($p),
						'regex'      => $this->regex_($p),
						'name'       => $this->name_($p),
						'subArgs'    => $this->makeArguments_($p->children())
					);

					array_unshift($this->s_['arguments'], $tag);
					break;

				default:
					trigger_error('not implemented');
				}
			}
		}

		// Check if command may appear here
		$prev_text = (is_null($prevToken) ? '_start_' : $prevToken->text);
		if (!preg_match('/'. $this->s_['valid_after'] .'/i', $prev_text))
			throw new SieveException($token, $this->command_ .' may not appear after '. $prev_text);
	}

	public function __destruct()
	{
		$this->registry_->put();
	}

	// TODO: the *Regex functions could possibly also be static properties
	protected function requireStringsRegex_()
	{
		$extensions = implode('|', $this->registry_->requireStrings());
		return '(comparator-'. $this->comparators_ .(empty($extensions) ? '' : "|$extensions"). ')';
	}

	protected function matchTypeRegex_()
	{
		$extensions = implode('|', $this->registry_->matchTypes());
		return '('. $this->matchTypes_ . (empty($extensions) ? ')' : "|$extensions)");
	}

	protected function addressPartRegex_()
	{
		$extensions = implode('|', $this->registry_->addressParts());
		return '('. $this->addressParts_ . (empty($extensions) ? ')' : "|$extensions)");
	}

	protected function commandsRegex_()
	{
		$extensions = implode('|', $this->registry_->commands());
		return '('. $this->commands_ . (empty($extensions) ?')' : "|$extensions)");
	}

	protected function testsRegex_()
	{
		$extensions = implode('|', $this->registry_->tests());
		return '('. $this->tests_ . (empty($extensions) ? ')' : "|$extensions)");
	}

	protected function comparatorRegex_()
	{
		$extensions = implode('|', $this->registry_->comparators());
		return '('. $this->comparators_ . (empty($extensions) ? '' : "|$extensions") . ')';
	}

	protected function occurrence_($arg)
	{
		if (isset($arg['occurrence']))
		{
			switch ((string) $arg['occurrence'])
			{
			case 'optional':
				return '?';
			case 'any':
				return '*';
			case 'some':
				return '+';
			}
		}
		return '1';
	}

	protected function name_($arg)
	{
		if (isset($arg['name']))
		{
			return (string) $arg['name'];
		}
		return 'undefined';
	}

	protected function regex_($arg)
	{
		if (isset($arg['regex']))
		{
			return (string) $arg['regex'];
		}
		return '.*';
	}

	protected function makeValue_($arg)
	{
		if (isset($arg->value))
		{
			$res = $this->makeArguments_($arg->value);
			return array_shift($res);
		}
		return null;
	}

	/**
	 * Convert an extension (test) commands parameters from XML to
	 * a PHP array the {@see Semantics} class understands.
	 * @param array(SimpleXMLElement) $parameters
	 * @return array
	 */
	protected function makeArguments_($parameters)
	{
		$arguments = array();

		foreach ($parameters as $arg)
		{
			// Ignore anything not a <parameter>
			if ($arg->getName() != 'parameter')
				continue;

			switch ((string) $arg['type'])
			{
			case 'matchtype':
				array_push($arguments, array(
					'type'       => Token::Tag,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->matchTypeRegex_(),
					'call'       => 'matchTypeHook_',
					'name'       => 'match type',
					'subArgs'    => $this->makeArguments_($arg)
				));
				break;

			case 'comparator':
				array_push($arguments, array(
					'type'       => Token::Tag,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => 'comparator',
					'name'       => 'comparator',
					'subArgs'    => array( array(
						'type'       => Token::String,
						'occurrence' => '1',
						'call'       => 'comparatorHook_',
						'regex'      => $this->comparatorRegex_(),
						'name'       => 'comparator string'
					))
				));
				break;

			case 'addresspart':
				array_push($arguments, array(
					'type'       => Token::Tag,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->addressPartRegex_(),
					'name'       => 'address part'
				));
				break;

			case 'tag':
				array_push($arguments, array(
					'type'       => Token::Tag,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->regex_($arg),
					'name'       => $this->name_($arg),
					'subArgs'    => $this->makeArguments_($arg->children())
				));
				break;

			case 'number':
				array_push($arguments, array(
					'type'       => Token::Number,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->regex_($arg),
					'name'       => $this->name_($arg)
				));
				break;

			case 'string':
				array_push($arguments, array(
					'type'       => Token::String,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->regex_($arg),
					'name'       => $this->name_($arg)
				));
				break;

			case 'stringlist':
				array_push($arguments, array(
					'type'       => Token::StringList,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->regex_($arg),
					'name'       => $this->name_($arg)
				));
				break;
			}
		}

		return $arguments;
	}

	/**
	 * Add argument(s) expected / allowed to appear next.
	 * @param array $value
	 */
	protected function addArguments_($subArgs)
	{
		for ($i = count($subArgs); $i > 0; $i--)
		{
			array_unshift($this->s_['arguments'], $subArgs[$i-1]);
		}
	}

	/**
	 * Add dependency that is expected to be fullfilled when parsing
	 * of the current command is {@see done}.
	 * @param array $dependency
	 */
	protected function addDependency_($type, $name, $dependencies)
	{
		foreach ($dependencies as $d)
		{
			array_push($this->deps_, array(
				'o_type' => $type,
				'o_name' => $name,
				'type'   => $d['type'],
				'name'   => $d['name'],
				'regex'  => $d['regex']
			));
		}
	}

	protected function invoke_($token, $func, $arg = array())
	{
		if (!is_array($arg))
		{
			$arg = array($arg);
		}

		$err = call_user_func_array(array(&$this, $func), $arg);

		if ($err)
		{
			throw new SieveException($token, $err);
		}
	}

	protected function setRequire_($extension)
	{
		$extension = str_replace('"', '', $extension);
		array_push(self::$requiredExtensions_, $extension);
		$this->registry_->activate($extension);
	}

	/**
	 * Hook function that is called after a address part match was found
	 * in a command. The kind of address part is remembered in case it's
	 * needed later {@see done}. For address parts from a extension
	 * dependency information and valid values are looked up as well.
	 * @param string $addresspart
	 */
	protected function addressPartHook_($addresspart)
	{
		$this->addressPart_ = substr($addresspart, 1);
		$xml = $this->registry_->addresspart($this->addressPart_);

		if (isset($xml))
		{
			// Add possible value and depedency
			$this->addArguments_($this->makeArguments_($xml));
			$this->addDependency_('address part', $this->addressPart_, $xml->requires);
		}
	}

	/**
	 * Hook function that is called after a match type was found in a
	 * command. The kind of match type is remembered in case it's
	 * needed later {@see done}. For a match type from extensions
	 * dependency information and valid values are looked up as well.
	 * @param string $matchtype
	 */
	protected function matchTypeHook_($matchtype)
	{
		$this->matchType_ = substr($matchtype, 1);
		$xml = $this->registry_->matchtype($this->matchType_);

		if (isset($xml))
		{
			// Add possible value and depedency
			$this->addArguments_($this->makeArguments_($xml));
			$this->addDependency_('match type', $this->matchType_, $xml->requires);
		}
	}

	/**
	 * Hook function that is called after a comparator was found in
	 * a command. The comparator is remembered in case it's needed for
	 * comparsion later {@see done}. For a comparator from extensions
	 * dependency infomation is looked up as well.
	 * @param string $comparator
	 */
	protected function comparatorHook_($comparator)
	{
		$this->comparator_ = substr($comparator, 1, -1);
		$xml = $this->registry_->comparator($this->comparator_);

		if (isset($xml))
		{
			// Add possible dependency
			$this->addDependency_('comparator', $this->comparator_, $xml->requires);
		}
	}

	protected function validType_($token)
	{
		foreach ($this->s_['arguments'] as $arg)
		{
			if ($arg['occurrence'] == '0')
			{
				array_shift($this->s_['arguments']);
				continue;
			}

			if ($token->is($arg['type']))
			{
				return;
			}

			// Is the argument required
			if ($arg['occurrence'] != '?' && $arg['occurrence'] != '*')
			{
				throw new SieveException($token, $arg['type']);
			}

			array_shift($this->s_['arguments']);
		}

		// Check if command expects any (more) arguments
		if (empty($this->s_['arguments']))
		{
			throw new SieveException($token, Token::Semicolon);
		}

		throw new SieveException($token, 'unexpected '. Token::typeString($token->type) .' '. $token->text);
	}

	public function startStringList($token)
	{
		$this->validType_($token);
		$this->s_['arguments'][0]['occurrence'] = '+';
	}

	public function endStringList()
	{
		array_shift($this->s_['arguments']);
	}

	public function validateToken($token)
	{
		// Make sure the argument has a valid type
		$this->validType_($token);

		foreach ($this->s_['arguments'] as &$arg)
		{
			// Build regular expression according to argument type
			switch ( $arg['type'] )
			{
			case Token::String:
			case Token::StringList:
				$regex = '/^(text:[^\n]*\n'. $arg['regex'] .'\.\r?\n?|"'. $arg['regex'] .'")$/s';
				break;
			case Token::Tag:
				$regex = '/^:'. $arg['regex'] .'$/si';
				break;
			default:
				$regex = '/^'. $arg['regex'] .'$/si';
			}

			if (preg_match($regex, $token->text))
			{
				// Call extra processing function if defined
				if (isset($arg['call']))
				{
					$this->invoke_($token, $arg['call'], strtolower($token->text));
				}

				// Add argument(s) that may now appear after this one
				if (isset($arg['subArgs']))
					$this->addArguments_($arg['subArgs']);

				// Check if a possible value of this argument may occur
				if ($arg['occurrence'] == '?' || $arg['occurrence'] == '1')
				{
					$arg['occurrence'] = '0';
				}
				else if ($arg['occurrence'] == '+')
				{
					$arg['occurrence'] = '*';
				}

				return;
			}

			if ($token->is($arg['type']) && $arg['occurrence'] == 1)
			{
				throw new SieveException($token,
					Token::typeString($token->type) ." $token->text where ". $arg['name'] .' expected');
			}
		}

		throw new SieveException($token, 'unexpected '. Token::typeString($token->type) .' '. $token->text);
	}

	public function done($token)
	{
		// Check if there are required arguments left
		foreach ($this->s_['arguments'] as $arg)
		{
			if ($arg['occurrence'] == '+' || $arg['occurrence'] == '1')
			{
				throw new SieveException($token, $arg['type']);
			}
		}

		// Check if the command depends on use of a certain tag
		foreach ($this->deps_ as $d)
		{
			switch ($d['type'])
			{
			case 'addresspart':
				$value = $this->addressPart_;
				break;

			case 'matchtype':
				$value = $this->matchType_;
				break;

			case 'comparator':
				$value = $this->comparator_;
				break;
			}

			if (!preg_match('/^'. $d['regex'] .'$/mi', $value))
			{
				throw new SieveException($token,
					$d['o_type'] .' '. $d['o_name'] .' needs '. $d['type'] .' '. $d['name']);
			}
		}
	}
}

?>