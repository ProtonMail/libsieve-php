<?php

namespace Sieve;

class SieveKeywordRegistry
{
    protected $registry_ = [];
    protected $matchTypes_ = [];
    protected $comparators_ = [];
    protected $addressParts_ = [];
    protected $commands_ = [];
    protected $tests_ = [];
    protected $arguments_ = [];

    /**
     * @var array a map with the extension name as key.
     *
     * The value does not matter, to remove an extension, unset the key.
     */
    protected $loadedExtensions = [];

    /**
     * @var array a map defining which extensions are forbidden.
     *
     * The key is the forbidden extension's name, the value is an array
     * containing all the extensions that forbids the current extension
     * usage.
     */
    protected $forbiddenExtensions = [];

    /**
     * @var array extensions that are required by another extension.
     *
     * The key is the require extensions. The value is an array of extensions, which require the current extension.
     */
    protected $requiredExtensions = [];

    public function __construct($extensions_enabled, $custom_extensions)
    {
        $keywords = simplexml_load_file(dirname(__FILE__) . '/keywords.xml');
        foreach ($keywords->children() as $keyword) {
            switch ($keyword->getName()) {
                case 'matchtype':
                    $type = &$this->matchTypes_;
                    break;
                case 'comparator':
                    $type = &$this->comparators_;
                    break;
                case 'addresspart':
                    $type = &$this->addressParts_;
                    break;
                case 'test':
                    $type = &$this->tests_;
                    break;
                case 'command':
                    $type = &$this->commands_;
                    break;
                default:
                    trigger_error('Unsupported keyword type "' . $keyword->getName()
                    . '" in file "keywords.xml"');

                    return;
            }

            $name = (string) $keyword['name'];
            if (array_key_exists($name, $type)) {
                trigger_error("redefinition of $type $name - skipping");
            } else {
                $type[$name] = $keyword->children();
            }
        }

        foreach (glob(dirname(__FILE__) . '/extensions/*.xml') as $file) {
            $extension = simplexml_load_file($file);
            $name = (string) $extension['name'];

            if ($extensions_enabled !== null && !in_array($name, $extensions_enabled)) {
                continue;
            }

            if (array_key_exists($name, $this->registry_)) {
                trigger_error('overwriting extension "' . $name . '"');
            }
            $this->registry_[$name] = $extension;
        }

        foreach ($custom_extensions as $custom_extension) {
            $extension = simplexml_load_file($custom_extension);
            $name = (string) $extension['name'];

            if (array_key_exists($name, $this->registry_)) {
                trigger_error('overwriting extension "' . $name . '"');
            }
            $this->registry_[$name] = $extension;
        }
    }

    /**
     * Activates an extension.
     *
     * @param string $extension
     */
    public function activate(string $extension)
    {
        if (!isset($this->registry_[$extension])  // extension unknown
            || isset($this->loadedExtensions[$extension]) // already loaded
        ) {
            return;
        }

        $this->loadedExtensions[$extension] = true;

        // we can safely unset the required extension
        unset($this->requiredExtensions[$extension]);

        $xml = $this->registry_[$extension];

        if (isset($xml['require'])) {
            $requireExtensions = explode(',', $xml['require']);
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
                    $type = &$this->matchTypes_;
                    break;
                case 'comparator':
                    $type = &$this->comparators_;
                    break;
                case 'addresspart':
                    $type = &$this->addressParts_;
                    break;
                case 'test':
                    $type = &$this->tests_;
                    break;
                case 'command':
                    $type = &$this->commands_;
                    break;
                case 'tagged-argument':
                    $xml = $e->parameter[0];
                    $this->arguments_[(string) $xml['name']] = [
                    'extends' => (string) $e['extends'],
                    'rules' => $xml,
                    ];
                    continue;
                default:
                    trigger_error('Unsupported extension type \'' .
                    $e->getName() . "' in extension '$extension'");
            }

            $name = (string) $e['name'];
            if (!isset($type[$name]) ||
                (string) $e['overrides'] == 'true') {
                $type[$name] = $e->children();
            }
        }
    }

    public function isTest($name)
    {
        return isset($this->tests_[$name]);
    }

    public function isCommand($name)
    {
        return isset($this->commands_[$name]);
    }

    public function matchtype($name)
    {
        if (isset($this->matchTypes_[$name])) {
            return $this->matchTypes_[$name];
        }

        return null;
    }

    public function addresspart($name)
    {
        if (isset($this->addressParts_[$name])) {
            return $this->addressParts_[$name];
        }

        return null;
    }

    public function comparator($name)
    {
        if (isset($this->comparators_[$name])) {
            return $this->comparators_[$name];
        }

        return null;
    }

    public function test($name)
    {
        if (isset($this->tests_[$name])) {
            return $this->tests_[$name];
        }

        return null;
    }

    public function command($name)
    {
        if (isset($this->commands_[$name])) {
            return $this->commands_[$name];
        }

        return null;
    }

    public function arguments($command)
    {
        $res = [];
        foreach ($this->arguments_ as $arg) {
            if (preg_match('/' . $arg['extends'] . '/', $command)) {
                array_push($res, $arg['rules']);
            }
        }

        return $res;
    }

    public function argument($name)
    {
        if (isset($this->arguments_[$name])) {
            return $this->arguments_[$name]['rules'];
        }

        return null;
    }

    public function requireStrings()
    {
        return array_keys($this->registry_);
    }

    public function matchTypes()
    {
        return array_keys($this->matchTypes_);
    }

    public function comparators()
    {
        return array_keys($this->comparators_);
    }

    public function addressParts()
    {
        return array_keys($this->addressParts_);
    }

    public function tests()
    {
        return array_keys($this->tests_);
    }

    public function commands()
    {
        return array_keys($this->commands_);
    }

    public function validateRequires(SieveToken $sieveToken)
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
