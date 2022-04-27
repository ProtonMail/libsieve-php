<?php

declare(strict_types=1);

namespace Sieve;

use SimpleXMLElement;
use Throwable;

class SieveSemantics
{
    protected static array $requiredExtensions = [];

    protected ?string $comparator = null;
    protected ?string $matchType = null;
    protected ?string $addressPart = null;
    protected array $tags = [];
    protected array $arguments;
    protected array $deps = [];
    protected int $followupToken;

    /**
     * SieveSemantics constructor.
     *
     * @throws SieveException
     */
    public function __construct(protected SieveKeywordRegistry $registry, SieveToken $token, ?SieveToken $prevToken)
    {
        $command = strtolower($token->text);

        // Check the registry for $command
        if ($this->registry->isCommand($command)) {
            $xml = $this->registry->command($command);
            $this->arguments = $this->makeArguments($xml);
            $this->followupToken = SieveToken::SEMICOLON;
        } elseif ($this->registry->isTest($command)) {
            $xml = $this->registry->test($command);
            $this->arguments = $this->makeArguments($xml);
            $this->followupToken = SieveToken::BLOCK_START;
        } else {
            throw new SieveException($token, 'unknown command ' . $command);
        }

        // Check if command may appear at this position within the script
        if ($this->registry->isTest($command)) {
            if ($prevToken === null) {
                throw new SieveException($token, $command . ' may not appear as first command');
            }
            if (!preg_match('/^(if|elsif|anyof|allof|not)$/i', $prevToken->text)) {
                throw new SieveException($token, $command . ' may not appear after ' . $prevToken->text);
            }
        } elseif (isset($prevToken) || in_array($command, ['elsif', 'else'])) {
            $validAfter = match ($command) {
                'require' => 'require',
                'elsif', 'else' => '(if|elsif)',
                default => $this->commandsRegex(),
            };

            if (isset($prevToken)) {
                if (!preg_match('/^' . $validAfter . '$/i', $prevToken->text)) {
                    throw new SieveException($token, "$command may not appear after $prevToken->text");
                }
            } else {
                throw new SieveException($token, "$command cannot be the first token");
            }
        }

        // Check for extension arguments to add to the command
        foreach ($this->registry->arguments($command) as $arg) {
            if ((string) $arg['type'] === 'tag') {
                array_unshift(
                    $this->arguments,
                    [
                        'type' => SieveToken::TAG,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->regex($arg),
                        'call' => 'tagHook',
                        'name' => $this->name($arg),
                        'subArgs' => $this->makeArguments($arg->children()),
                    ]
                );
            }
        }
    }

    // TODO: the *Regex functions could possibly also be static properties

    /**
     * Get the require strings regex.
     */
    protected function requireStringsRegex(): string
    {
        return '(' . implode('|', $this->registry->requireStrings()) . ')';
    }

    /**
     * Get the match type regex.
     */
    protected function matchTypeRegex(): string
    {
        return '(' . implode('|', $this->registry->matchTypes()) . ')';
    }

    /**
     * Get the address part regex.
     */
    protected function addressPartRegex(): string
    {
        return '(' . implode('|', $this->registry->addressParts()) . ')';
    }

    /**
     * Get the commands regex.
     */
    protected function commandsRegex(): string
    {
        return '(' . implode('|', $this->registry->commands()) . ')';
    }

    /**
     * Get the tests regex.
     */
    protected function testsRegex(): string
    {
        return '(' . implode('|', $this->registry->tests()) . ')';
    }

    /**
     * Comparator regex.
     */
    protected function comparatorRegex(): string
    {
        return '(' . implode('|', $this->registry->comparators()) . ')';
    }

    /**
     * Get the occurrence.
     */
    protected function occurrence(SimpleXMLElement $arg): string
    {
        if (isset($arg['occurrence'])) {
            switch ((string) $arg['occurrence']) {
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

    /**
     * Get the name from arg.
     */
    protected function name(SimpleXMLElement $arg): string
    {
        return (string) ($arg['name'] ?? $arg['type']);
    }

    /**
     * get Regex from arg.
     */
    protected function regex(SimpleXMLElement $arg): string
    {
        return (string) ($arg['regex'] ?? '.*');
    }

    /**
     * Get case from arg.
     */
    protected function getCase(SimpleXMLElement $arg): string
    {
        return (string) ($arg['case'] ?? 'adhere');
    }

    /**
     * Get follows from Args.
     */
    protected function follows(SimpleXMLElement $arg): string
    {
        return (string) ($arg['follows'] ?? '.*');
    }

    /**
     * Make value from Args.
     */
    protected function makeValue(SimpleXMLElement $arg): mixed
    {
        if (isset($arg->value)) {
            $res = $this->makeArguments($arg->value);

            return array_shift($res);
        }

        return null;
    }

    /**
     * Convert an extension (test) commands parameters from XML to
     * a PHP array the {@see Semantics} class understands.
     */
    protected function makeArguments(SimpleXMLElement|array $parameters): array
    {
        $arguments = [];

        foreach ($parameters as $arg) {
            // Ignore anything not a <parameter>
            if ($arg->getName() !== 'parameter') {
                continue;
            }

            switch ((string) $arg['type']) {
                case 'addresspart':
                    $arguments[] = [
                        'type' => SieveToken::TAG,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->addressPartRegex(),
                        'call' => 'addressPartHook',
                        'name' => 'address part',
                        'subArgs' => $this->makeArguments($arg),
                    ];
                    break;

                case 'block':
                    $arguments[] = [
                        'type' => SieveToken::BLOCK_START,
                        'occurrence' => '1',
                        'regex' => '{',
                        'name' => 'block',
                        'subArgs' => $this->makeArguments($arg),
                    ];
                    break;

                case 'comparator':
                    $arguments[] = [
                        'type' => SieveToken::TAG,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => 'comparator',
                        'name' => 'comparator',
                        'subArgs' => [
                            [
                                'type' => SieveToken::STRING,
                                'occurrence' => '1',
                                'call' => 'comparatorHook',
                                'case' => 'adhere',
                                'regex' => $this->comparatorRegex(),
                                'name' => 'comparator string',
                                'follows' => 'comparator',
                            ],
                        ],
                    ];
                    break;

                case 'matchtype':
                    $arguments[] = [
                        'type' => SieveToken::TAG,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->matchTypeRegex(),
                        'call' => 'matchTypeHook',
                        'name' => 'match type',
                        'subArgs' => $this->makeArguments($arg),
                    ];
                    break;

                case 'number':
                    $arguments[] = [
                        'type' => SieveToken::NUMBER,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->regex($arg),
                        'name' => $this->name($arg),
                        'follows' => $this->follows($arg),
                    ];
                    break;

                case 'requirestrings':
                    $arguments[] = [
                        'type' => SieveToken::STRING_LIST,
                        'occurrence' => $this->occurrence($arg),
                        'call' => 'setRequire',
                        'case' => 'adhere',
                        'regex' => $this->requireStringsRegex(),
                        'name' => $this->name($arg),
                    ];
                    break;

                case 'string':
                    $arguments[] = [
                        'type' => SieveToken::STRING,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->regex($arg),
                        'case' => $this->getCase($arg),
                        'name' => $this->name($arg),
                        'follows' => $this->follows($arg),
                    ];
                    break;

                case 'stringlist':
                    $arguments[] = [
                        'type' => SieveToken::STRING_LIST,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->regex($arg),
                        'case' => $this->getCase($arg),
                        'name' => $this->name($arg),
                        'follows' => $this->follows($arg),
                    ];
                    break;

                case 'tag':
                    $arguments[] = [
                        'type' => SieveToken::TAG,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->regex($arg),
                        'call' => 'tagHook',
                        'name' => $this->name($arg),
                        'subArgs' => $this->makeArguments($arg->children()),
                        'follows' => $this->follows($arg),
                    ];
                    break;

                case 'test':
                    $arguments[] = [
                        'type' => SieveToken::IDENTIFIER,
                        'occurrence' => $this->occurrence($arg),
                        'regex' => $this->testsRegex(),
                        'name' => $this->name($arg),
                        'subArgs' => $this->makeArguments($arg->children()),
                    ];
                    break;

                case 'testlist':
                    $arguments[] = [
                        'type' => SieveToken::LEFT_PARENTHESIS,
                        'occurrence' => '1',
                        'regex' => '\(',
                        'name' => $this->name($arg),
                        'subArgs' => null,
                    ];
                    $arguments[] = [
                        'type' => SieveToken::IDENTIFIER,
                        'occurrence' => '+',
                        'regex' => $this->testsRegex(),
                        'name' => $this->name($arg),
                        'subArgs' => $this->makeArguments($arg->children()),
                    ];
                    break;
            }
        }

        return $arguments;
    }

    /**
     * Add argument(s) expected / allowed to appear next.
     */
    protected function addArguments(string $identifier, array $subArgs): void
    {
        for ($i = count($subArgs); $i > 0; $i--) {
            $arg = $subArgs[$i - 1];
            if (preg_match('/^' . $arg['follows'] . '$/si', $identifier)) {
                array_unshift($this->arguments, $arg);
            }
        }
    }

    /**
     * Add dependency that is expected to be fulfilled when parsing
     * of the current command is {@see done}.
     */
    protected function addDependency(string $type, string $name, SimpleXMLElement $dependencies): void
    {
        foreach ($dependencies as $d) {
            $this->deps[] = [
                'o_type' => $type,
                'o_name' => $name,
                'type' => $d['type'],
                'name' => $d['name'],
                'regex' => $d['regex'],
            ];
        }
    }

    /**
     * Invoke.
     *
     * @throws SieveException
     */
    protected function invoke(SieveToken $token, string $func, mixed $arg = []): void
    {
        if (!is_array($arg)) {
            $arg = [$arg];
        }

        $err = $this->$func(...$arg);

        if ($err) {
            throw new SieveException($token, $err);
        }
    }

    /**
     * Add a require extension.
     */
    protected function setRequire(string $extension): ?string
    {
        self::$requiredExtensions[] = $extension;
        try {
            $this->registry->activate($extension);
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return null;
    }

    /**
     * Called after a address part match was found in a command.
     *
     * The kind of address part is remembered in case it's
     * needed later {@see done}. For address parts from a extension
     * dependency information and valid values are looked up as well.
     */
    protected function addressPartHook(string $addressPart): void
    {
        $this->addressPart = $addressPart;
        $xml = $this->registry->addressPart($this->addressPart);

        if (isset($xml)) {
            // Add possible value and dependency
            $this->addArguments($this->addressPart, $this->makeArguments($xml));
            $this->addDependency('address part', $this->addressPart, $xml->requires);
        }
    }

    /**
     * Called after a match type was found in a command.
     *
     * The kind of match type is remembered in case it's
     * needed later {@see done}. For a match type from extensions
     * dependency information and valid values are looked up as well.
     */
    protected function matchTypeHook(string $matchType): void
    {
        $this->matchType = $matchType;
        $xml = $this->registry->matchType($this->matchType);

        if (isset($xml)) {
            // Add possible value and dependency
            $this->addArguments($this->matchType, $this->makeArguments($xml));
            $this->addDependency('match type', $this->matchType, $xml->requires);
        }
    }

    /**
     * Called after a comparator was found in a command.
     *
     * The comparator is remembered in case it's needed for
     * comparison later {@see done}. For a comparator from extensions
     * dependency information is looked up as well.
     */
    protected function comparatorHook(string $comparator): void
    {
        $this->comparator = $comparator;
        $xml = $this->registry->comparator($this->comparator);

        if (isset($xml)) {
            // Add possible dependency
            $this->addDependency('comparator', $this->comparator, $xml->requires);
        }
    }

    /**
     * Called after a tag was found in a command.
     *
     * The tag is remembered in case it's needed for
     * comparison later {@see done}. For a tags from extensions
     * dependency information is looked up as well.
     */
    protected function tagHook(string $tag): void
    {
        $this->tags[] = $tag;
        $xml = $this->registry->argument($tag);

        // Add possible dependencies
        if (isset($xml)) {
            $this->addDependency('tag', $tag, $xml->requires);
        }
    }

    /**
     * Validates type.
     *
     * @throws SieveException
     */
    protected function validType(SieveToken $token): void
    {
        foreach ($this->arguments as $arg) {
            if ($arg['occurrence'] === '0') {
                array_shift($this->arguments);
                continue;
            }

            if ($token->is($arg['type'])) {
                return;
            }

            // Is the argument required
            if ($arg['occurrence'] !== '?' && $arg['occurrence'] !== '*') {
                throw new SieveException($token, $arg['type']);
            }
            array_shift($this->arguments);
        }

        // Check if command expects any (more) arguments
        if (empty($this->arguments)) {
            throw new SieveException($token, $this->followupToken);
        }
        throw new SieveException($token, 'unexpected ' . SieveToken::typeString($token->type) . ' ' . $token->text);
    }

    /**
     * Start string list.
     *
     * @throws SieveException
     */
    public function startStringList(SieveToken $token): void
    {
        $this->validType($token);
        $this->arguments[0]['type'] = SieveToken::STRING;
        $this->arguments[0]['occurrence'] = '+';
    }

    /**
     * Continue string list.
     */
    public function continueStringList(): void
    {
        $this->arguments[0]['occurrence'] = '+';
    }

    /**
     * End string list.
     */
    public function endStringList(): void
    {
        array_shift($this->arguments);
    }

    /**
     * Validates a token.
     *
     * @throws SieveException
     */
    public function validateToken(SieveToken $token): void
    {
        // Make sure the argument has a valid type
        $this->validType($token);

        foreach ($this->arguments as &$arg) {
            // Build regular expression according to argument type
            switch ($arg['type']) {
                case SieveToken::STRING:
                case SieveToken::STRING_LIST:
                    $regex =
                        '/^(?:text:[^\n]*\n(?P<one>' . $arg['regex'] . ')\.\r?\n?|"(?P<two>' . $arg['regex'] . ')")$/'
                        . ((string) $arg['case'] === 'ignore' ? 'si' : 's');
                    break;
                case SieveToken::TAG:
                    $regex = '/^:(?P<one>' . $arg['regex'] . ')$/si';
                    break;
                default:
                    $regex = '/^(?P<one>' . $arg['regex'] . ')$/si';
            }

            if (preg_match($regex, $token->text, $match)) {
                $text = (isset($match['one']) && $match['one'] !== '' ? $match['one'] : $match['two']);

                // Add argument(s) that may now appear after this one
                if (isset($arg['subArgs'])) {
                    $this->addArguments($text, $arg['subArgs']);
                }

                // Call extra processing function if defined
                if (isset($arg['call'])) {
                    $this->invoke($token, $arg['call'], $text);
                }

                // Check if a possible value of this argument may occur
                if ($arg['occurrence'] === '?' || $arg['occurrence'] === '1') {
                    $arg['occurrence'] = '0';
                } elseif ($arg['occurrence'] === '+') {
                    $arg['occurrence'] = '*';
                }

                return;
            }

            if ($token->is($arg['type']) && $arg['occurrence'] === '1') {
                throw new SieveException(
                    $token,
                    SieveToken::typeString($token->type) . " $token->text where " . $arg['name'] . ' expected'
                );
            }
        }

        throw new SieveException($token, 'unexpected ' . SieveToken::typeString($token->type) . ' ' . $token->text);
    }

    /**
     * Called when script parsing is done.
     *
     * @throws SieveException
     */
    public function done(SieveToken $token): void
    {
        // Check if there are required arguments left
        foreach ($this->arguments as $arg) {
            if ($arg['occurrence'] === '+' || $arg['occurrence'] === '1') {
                throw new SieveException($token, $arg['type']);
            }
        }

        // Check if the command depends on use of a certain tag
        foreach ($this->deps as $d) {
            switch ($d['type']) {
                case 'addresspart':
                    $values = [$this->addressPart];
                    break;

                case 'matchtype':
                    $values = [$this->matchType];
                    break;

                case 'comparator':
                    $values = [$this->comparator];
                    break;

                case 'tag':
                    $values = $this->tags;
                    break;
            }

            foreach ($values as $value) {
                if ($value !== null && preg_match('/^' . $d['regex'] . '$/mi', $value)) {
                    break 2;
                }
            }

            throw new SieveException(
                $token,
                $d['o_type'] . ' ' . $d['o_name'] . ' requires use of ' . $d['type'] . ' ' . $d['name']
            );
        }
    }
}
