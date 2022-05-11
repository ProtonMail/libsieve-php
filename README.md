# libsieve-php

[![Build Status](https://img.shields.io/travis/ProtonMail/libsieve-php.svg?style=flat-square)](https://travis-ci.org/ProtonMail/libsieve-php)
[![Coverage](https://img.shields.io/codecov/c/github/ProtonMail/libsieve-php.svg?style=flat-square)](https://codecov.io/gh/ProtonMail/libsieve-php)
[![License](https://img.shields.io/github/license/ProtonMail/libsieve-php.svg?style=flat-square)](https://github.com/ProtonMail/libsieve-php/blob/master/LICENSE)

libsieve-php is a library to manage and modify sieve (RFC5228) scripts. It contains a parser for the sieve language (including extensions) and a client for the managesieve protocol.

This project is adopted from the discontinued PHP sieve library available at https://sourceforge.net/projects/libsieve-php.

## Changes from the RFC

 - The `date` and the `currentdate` both allow for `zone` parameter any string to be passed.
   This allows the user to enter zone names like `Europe/Zurich` instead of `+0100`. 
   The reason we allow this is because offsets like `+0100` don't encode information about the
   daylight saving time, which is often needed.

## Usage examples

The libsieve parses a sieve script into a tree. This tree can then be used to interpret its meaning.

Two example will be provided: one basic and one more complex.
### Basic Example: Check if an extension is loaded
In this first example, we will check if a specific extension was loaded through a require node:

```php
<?php
use Sieve\SieveParser;

class ExtensionCheckExample
{
    /** @var \Sieve\SieveTree the tree, obtained from the SieveParser */
    protected $tree;

    /**
     * Parser constructor.
     *
     * @param string $sieve the sieve script to read.
     */
    public function __construct(string $sieve)
    {
        $parser = new SieveParser();
        try {
            $parser->parse($sieve);
        } catch (\Sieve\SieveException $se) {
            throw new \Exception("The provided sieve script is invalid!");
        }

        // we store the tree, because it contains all the information.
        $this->tree = $parser->GetParseTree();
    }

    /**
     * Checks if an extension is loaded.
     *
     * @param string $extension
     * @return bool
     */
    public function isLoaded(string $extension)
    {
        /** @var int $root_node_id */
        $root_node_id = $this->tree->getRoot();
        // The root is not a node, we can only access its children
        $children = $this->tree->getChildren($root_node_id);
        foreach ($children as $child) {
            // The child is an id to a node, which can be access using the following:
            $node = $this->tree->getNode($child);

            // is can be used to check the type of node.
            if ($node->is(\Sieve\SieveToken::IDENTIFIER) && $node->text === "require") {
                if ($this->checkChildren($this->tree->getChildren($child), $extension)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks the arguments given to a require node, to know if it includes
     *
     * @param $children
     * @param string $extension
     * @return bool
     */
    private function checkChildren($children, string $extension): bool
    {
        if (is_array($children)) {
            // it's a string list, let's loop over them.
            foreach ($children as $child) {
                if ($this->checkChildren($child, $extension)) {
                    return true;
                }
            }
            return false;
        }

        $node = $this->tree->getNode($children);
        return $node->is(\Sieve\SieveToken::QUOTED_STRING) && $extension === trim($node->text, '"');
    }
}

// load a script, from the tests folder.
$sieve = file_get_contents(__DIR__ . './tests/good/currentdate.siv');

$runner = new ExtensionCheckExample($sieve);
var_dump($runner->isLoaded("variables"));
```

### Complex Example: Dumping the tree

This second example will print back the sieve script from a parsed tree (note this could simply be done through
the method `$tree->dump()`).

```php
<?php
use Sieve\SieveParser;
use Sieve;

class UsageExample
{
    /** @var \Sieve\SieveTree the tree, obtained from the SieveParser */
    protected $tree;

    /**
     * Parser constructor.
     *
     * @param string $sieve the sieve script to read.
     */
    public function __construct(string $sieve)
    {
        $parser = new SieveParser();
        try {
            $parser->parse($sieve);
        } catch (\Sieve\SieveException $se) {
            throw new \Exception("The provided sieve script is invalid!");
        }

        // we store the tree, because it contains all the information.
        $this->tree = $parser->GetParseTree();
    }

    /**
     * Displays the tree
     */
    public function display()
    {
        /** @var int $root_node_id */
        $root_node_id = $this->tree->getRoot();
        // The root is not a node, we can only access its children
        $children = $this->tree->getChildren($root_node_id);
        $this->displayNodeList($children);
    }

    /**
     * Loop over a list of nodes, and display them.
     *
     * @param int[] $nodes a list of node ids.
     * @param string $indent
     */
    private function displayNodeList(array $nodes, string $indent = '')
    {
        foreach ($nodes as $node) {
            $this->displayNode($node, $indent);
        }
    }

    /**
     * Display a node and its children.
     *
     * @param int $node_id the current node id.
     * @param string $indent
     */
    private function displayNode(int $node_id, string $indent)
    {
        /**
         * @var \Sieve\SieveToken $node can be used to get info about a specific node.
         */
        $node = $this->tree->getNode($node_id);

        // All the possible node types are listed as constants in the class SieveToken...
        switch ($node->type) {
            case \Sieve\SieveToken::SCRIPT_END:
                printf($indent . "EOS");
                break;
            case Sieve\SieveToken::WHITESPACE:
            case Sieve\SieveToken::COMMENT:
                break;
            default:
                // the $node->type is a integer. It can be turned into an explicit string this way...
                $type = \Sieve\SieveToken::typeString($node->type);

                $open_color = '';
                $end_color = '';

                // The type of a node can be checked with the is method. Mask can be used to match several types.
                if ($node->is(\Sieve\SieveToken::QUOTED_STRING | Sieve\SieveToken::MULTILINE_STRING)) {
                    // we want to put a specific color arround strings...
                    $open_color = "\e[1;32;47m";
                    $end_color = "\e[0m";
                }

                // The children of a node can be obtain through this method:
                $children = $this->tree->getChildren($node_id);

                // do whatever you want with a node and its children :) Here we are going to display them.
                printf("[%4d, %-10.10s (%5d) ]%s ${open_color}%s$end_color" . PHP_EOL, $node->line, $type, $node->type, $indent, $node->text);
                $this->displayNodeList($children, $indent . "\t");
        }
    }
}

$sieve = file_get_contents(__DIR__ . '/tests/good/currentdate.siv');

$parser = new UsageExample($sieve);
$parser->display();
```
