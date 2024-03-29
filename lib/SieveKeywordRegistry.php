<?php

declare(strict_types=1);

namespace Sieve;

use SimpleXMLElement;

class SieveKeywordRegistry
{
    protected array $registry = [];
    protected array $matchTypes = [];
    protected array $comparators = [];
    protected array $addressParts = [];
    protected array $commands = [];
    protected array $tests = [];
    protected array $arguments = [];

    /**
     * @var array a map with the extension name as key.
     *
     * The value does not matter, to remove an extension, unset the key.
     */
    protected array $loadedExtensions = [];

    /**
     * @var array a map defining which extensions are forbidden.
     *
     * The key is the forbidden extension's name, the value is an array
     * containing all the extensions that forbids the current extension
     * usage.
     */
    protected array $forbiddenExtensions = [];

    /**
     * @var array extensions that are required by another extension.
     *
     * The key is the require extensions. The value is an array of extensions, which require the current extension.
     */
    protected array $requiredExtensions = [];

    /**
     * SieveKeywordRegistry constructor.
     */
    public function __construct(?array $extensionsEnabled, array $customExtensions)
    {
        $keywords = simplexml_load_file(__DIR__ . '/keywords.xml');
        foreach ($keywords->children() as $keyword) {
            switch ($keyword->getName()) {
                case 'matchtype':
                    $type = &$this->matchTypes;
                    break;
                case 'comparator':
                    $type = &$this->comparators;
                    break;
                case 'addresspart':
                    $type = &$this->addressParts;
                    break;
                case 'test':
                    $type = &$this->tests;
                    break;
                case 'command':
                    $type = &$this->commands;
                    break;
                default:
                    trigger_error("Unsupported keyword type \"{$keyword->getName()}\" in file \"keywords.xml\"");
                    return;
            }

            $name = (string) $keyword['name'];
            if (array_key_exists($name, $type)) {
                trigger_error("redefinition of array $name - skipping");
            } else {
                $type[$name] = $keyword->children();
            }
        }

        foreach (glob(__DIR__ . '/extensions/*.xml') as $file) {
            $extension = simplexml_load_file($file);
            $name = (string) $extension['name'];

            if ($extensionsEnabled !== null && !in_array($name, $extensionsEnabled, true)) {
                continue;
            }

            if (array_key_exists($name, $this->registry)) {
                trigger_error("overwriting extension \"$name\"");
            }
            $this->registry[$name] = $extension;
        }

        foreach ($customExtensions as $customExtension) {
            $extension = simplexml_load_file($customExtension);
            $name = (string) $extension['name'];

            if (array_key_exists($name, $this->registry)) {
                trigger_error("overwriting extension \"$name\"");
            }
            $this->registry[$name] = $extension;
        }
    }

    /**
     * Activates an extension.
     */
    public function activate(string $extension): void
    {
        if (!isset($this->registry[$extension])  // extension unknown
            || isset($this->loadedExtensions[$extension]) // already loaded
        ) {
            return;
        }

        $this->loadedExtensions[$extension] = true;

        // we can safely unset the required extension
        unset($this->requiredExtensions[$extension]);

        $xml = $this->registry[$extension];

        if (isset($xml['require'])) {
            $requireExtensions = explode(',', (string) $xml['require']);
            foreach ($requireExtensions as $require) {
                if ($require[0] !== '!') {
                    if (!isset($this->loadedExtensions[$require])) {
                        // $require is needed, but not yet loaded.
                        $this->requiredExtensions[$require] = $this->requiredExtensions[$require] ?? [];
                        $this->requiredExtensions[$require][] = $extension;
                    }
                } else {
                    $forbidden = ltrim($require, '!');
                    $this->forbiddenExtensions[$forbidden] = $this->forbiddenExtensions[$forbidden] ?? [];
                    $this->forbiddenExtensions[$forbidden][] = $extension;
                }
            }
        }

        foreach ($xml->children() as $e) {
            switch ($e->getName()) {
                case 'matchtype':
                    $type = &$this->matchTypes;
                    break;
                case 'comparator':
                    $type = &$this->comparators;
                    break;
                case 'addresspart':
                    $type = &$this->addressParts;
                    break;
                case 'test':
                    $type = &$this->tests;
                    break;
                case 'command':
                    $type = &$this->commands;
                    break;
                case 'tagged-argument':
                    $xml = $e->parameter[0];
                    $this->arguments[(string) $xml['name']] = [
                        'extends' => (string) $e['extends'],
                        'rules' => $xml,
                    ];
                    break;
                default:
                    trigger_error('Unsupported extension type \'' .
                    $e->getName() . "' in extension '$extension'");
            }

            $name = (string) $e['name'];
            if (!isset($type[$name]) || (string) $e['overrides'] === 'true') {
                $type[$name] = $e->children();
            }
        }
    }

    /**
     * Is test.
     */
    public function isTest(string $name): bool
    {
        return isset($this->tests[$name]);
    }

    /**
     * Is command.
     */
    public function isCommand(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    public function matchType(string $name): mixed
    {
        return $this->matchTypes[$name] ?? null;
    }

    public function addressPart(string $name): mixed
    {
        return $this->addressParts[$name] ?? null;
    }

    /**
     * Get comparator.
     */
    public function comparator(string $name): mixed
    {
        return $this->comparators[$name] ?? null;
    }

    /**
     * Get test.
     */
    public function test($name): mixed
    {
        return $this->tests[$name] ?? null;
    }

    /**
     * Get command.
     */
    public function command($name): mixed
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * Get arguments.
     *
     * @return SimpleXMLElement[]
     */
    public function arguments(string $command): array
    {
        $res = [];
        foreach ($this->arguments as $arg) {
            if (preg_match('/' . $arg['extends'] . '/', $command)) {
                $res[] = $arg['rules'];
            }
        }

        return $res;
    }

    /**
     * Get (single) argument.
     */
    public function argument(string $name): mixed
    {
        return $this->arguments[$name]['rules'] ?? null;
    }

    /**
     * Get require strings.
     *
     * @return string[]
     */
    public function requireStrings(): array
    {
        return array_keys($this->registry);
    }

    /**
     * Get match types.
     *
     * @return string[]
     */
    public function matchTypes(): array
    {
        return array_keys($this->matchTypes);
    }

    /**
     * Get comparators.
     *
     * @return string[]
     */
    public function comparators(): array
    {
        return array_keys($this->comparators);
    }

    /**
     * Get address parts.
     *
     * @return string[]
     */
    public function addressParts(): array
    {
        return array_keys($this->addressParts);
    }

    /**
     * Get tests.
     *
     * @return string[]
     */
    public function tests(): array
    {
        return array_keys($this->tests);
    }

    /**
     * Get commands.
     *
     * @return string[]
     */
    public function commands(): array
    {
        return array_keys($this->commands);
    }

    /**
     * Validate requires.
     *
     * @throws SieveException if invalid
     */
    public function validateRequires(SieveToken $sieveToken): void
    {
        $message = "Extensions requirement are not fulfilled: \n";
        $error = false;
        if (count($this->requiredExtensions)) {
            $error = true;
            foreach ($this->requiredExtensions as $required => $by) {
                $message .= "Extension `$required` is required by `" . implode(', ', $by) . "`.\n";
            }
        }

        foreach ($this->forbiddenExtensions as $forbiddenExtension => $by) {
            if (!empty($this->loadedExtensions[$forbiddenExtension])) {
                $error = true;
                $message .= "Extension $forbiddenExtension cannot be loaded together with " .
                    implode(', ', $by) . ".\n";
            }
        }

        if ($error) {
            throw new SieveException($sieveToken, $message);
        }
    }
}
