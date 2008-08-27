<?php

require_once('class.registry.php');
require_once('class.token.php');
require_once('class.exception.php');

class Semantics
{
	protected static $requiredExtensions_ = array();

	protected $comparator_;
	protected $matchType_;
	protected $addressPart_;
	protected $arguments_;
	protected $deps_ = array();

	public function __construct($token, $prevToken)
	{
		$this->registry_ = KeywordRegistry::get();
		$command = strtolower($token->text);

		// Check the registry for $command
		if ($this->registry_->isCommand($command))
		{
			$xml = $this->registry_->command($command);
			$this->arguments_ = $this->makeArguments_($xml);
		}
		else if ($this->registry_->isTest($command))
		{
			$xml = $this->registry_->test($command);
			$this->arguments_ = $this->makeArguments_($xml);
		}
		else
		{
			throw new SieveException($token, 'unknown command '. $command);
		}

		// Check if command may appear at this position within the script
		if ($this->registry_->isTest($command))
		{
			if (is_null($prevToken))
				throw new SieveException($token, $command .' may not appear as first command');
			
			if (!preg_match('/^(if|elsif|anyof|allof|not)$/i', $prevToken->text))
				throw new SieveException($token, $command .' may not appear after '. $prevToken->text);
		}
		else if (isset($prevToken))
		{
			switch ($command)
			{
			case 'require':
				$valid_after = 'require';
				break;
			case 'elsif':
			case 'else':
				$valid_after = '(if|elsif)';
				break;
			default:
				$valid_after = $this->commandsRegex_();
			}
			
			if (!preg_match('/^'. $valid_after .'$/i', $prevToken->text))
				throw new SieveException($token, $command .' may not appear after '. $prevToken->text);
		}

		// Check for extension arguments to add to the command
		foreach ($this->registry_->arguments($command) as $arguments)
		{
			foreach ($arguments->parameter as $p)
			{
				switch ((string) $p['type'])
				{
				case 'tag':
					array_unshift($this->arguments_, array(
						'type'       => Token::Tag,
						'occurrence' => $this->occurrence_($p),
						'regex'      => $this->regex_($p),
						'name'       => $this->name_($p),
						'subArgs'    => $this->makeArguments_($p->children())
					));
					break;

				default:
					trigger_error('not implemented');
				}
			}
		}
	}

	public function __destruct()
	{
		$this->registry_->put();
	}

	// TODO: the *Regex functions could possibly also be static properties
	protected function requireStringsRegex_()
	{
		return '('. implode('|', $this->registry_->requireStrings()) .')';
	}

	protected function matchTypeRegex_()
	{
		return '('. implode('|', $this->registry_->matchTypes()) .')';
	}

	protected function addressPartRegex_()
	{
		return '('. implode('|', $this->registry_->addressParts()) .')';
	}

	protected function commandsRegex_()
	{
		return '('. implode('|', $this->registry_->commands()) .')';
	}

	protected function testsRegex_()
	{
		return '('. implode('|', $this->registry_->tests()) .')';
	}

	protected function comparatorRegex_()
	{
		return '('. implode('|', $this->registry_->comparators()) .')';
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
			case 'addresspart':
				array_push($arguments, array(
					'type'       => Token::Tag,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->addressPartRegex_(),
					'call'       => 'addressPartHook_',
					'name'       => 'address part'
				));
				break;

			case 'block':
				array_push($arguments, array(
					'type'       => Token::BlockStart,
					'occurrence' => '1',
					'regex'      => '{',
					'name'       => 'block',
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

			case 'number':
				array_push($arguments, array(
					'type'       => Token::Number,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->regex_($arg),
					'name'       => $this->name_($arg)
				));
				break;

			case 'requirestrings':
				array_push($arguments, array(
					'type'       => Token::StringList,
					'occurrence' => $this->occurrence_($arg),
					'call'       => 'setRequire_',
					'regex'      => $this->requireStringsRegex_(),
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

			case 'tag':
				array_push($arguments, array(
					'type'       => Token::Tag,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->regex_($arg),
					'name'       => $this->name_($arg),
					'subArgs'    => $this->makeArguments_($arg->children())
				));
				break;

			case 'test':
				array_push($arguments, array(
					'type'       => Token::Identifier,
					'occurrence' => $this->occurrence_($arg),
					'regex'      => $this->testsRegex_(),
					'name'       => $this->name_($arg),
					'subArgs'    => $this->makeArguments_($arg->children())
				));
				break;

			case 'testlist':
				array_push($arguments, array(
					'type'       => Token::LeftParenthesis,
					'occurrence' => '1',
					'regex'      => '\(',
					'name'       => $this->name_($arg),
					'subArgs'    => null
				));
				array_push($arguments, array(
					'type'       => Token::Identifier,
					'occurrence' => '+',
					'regex'      => $this->testsRegex_(),
					'name'       => $this->name_($arg),
					'subArgs'    => $this->makeArguments_($arg->children())
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
			array_unshift($this->arguments_, $subArgs[$i-1]);
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
		foreach ($this->arguments_ as $arg)
		{
			if ($arg['occurrence'] == '0')
			{
				array_shift($this->arguments_);
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

			array_shift($this->arguments_);
		}

		// Check if command expects any (more) arguments
		if (empty($this->arguments_))
		{
			throw new SieveException($token, Token::Semicolon);
		}

		throw new SieveException($token, 'unexpected '. Token::typeString($token->type) .' '. $token->text);
	}

	public function startStringList($token)
	{
		$this->validType_($token);
		$this->arguments_[0]['occurrence'] = '+';
	}

	public function endStringList()
	{
		array_shift($this->arguments_);
	}

	public function validateToken($token)
	{
		// Make sure the argument has a valid type
		$this->validType_($token);

		foreach ($this->arguments_ as &$arg)
		{
			// Build regular expression according to argument type
			switch ($arg['type'])
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
					$this->invoke_($token, $arg['call'], strtolower($token->text));

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
		foreach ($this->arguments_ as $arg)
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