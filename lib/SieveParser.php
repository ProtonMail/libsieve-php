<?php

declare(strict_types=1);

namespace Sieve;

class SieveParser
{
    /** @var SieveScanner the scanner */
    protected $scanner;
    protected $script;
    /** @var SieveTree */
    protected $tree;
    protected $status;
    protected $registry;

    /** @var array|null the enabled extensions */
    private $extensions_enabled;
    /** @var array the custom extensions */
    private $custom_extensions;

    /**
     * SieveParser constructor.
     *
     * @param array|null $extensions_enabled
     * @param array      $custom_extensions
     */
    public function __construct(?array $extensions_enabled = null, array $custom_extensions = [])
    {
        // just to check the errors in the constructor
        $this->registry = new SieveKeywordRegistry($extensions_enabled, $custom_extensions);
        $this->extensions_enabled = $extensions_enabled;
        $this->custom_extensions = $custom_extensions;
    }

    /**
     * Get parsed tree.
     *
     * @return SieveTree
     */
    public function getParseTree(): SieveTree
    {
        return $this->tree;
    }

    /**
     * Dump parse tree.
     *
     * @return string
     */
    public function dumpParseTree(): string
    {
        return $this->tree->dump();
    }

    /**
     * Get script text.
     *
     * @return string
     */
    public function getScriptText(): string
    {
        return $this->tree->getText();
    }

    /**
     * Get previous token.
     *
     * @param int $parent_id
     * @return SieveToken|null
     */
    protected function getPrevToken(int $parent_id): ?SieveToken
    {
        $children = $this->tree->getChildren($parent_id);

        for ($i = count($children); $i > 0; --$i) {
            $prev = $this->tree->getNode($children[$i - 1]);
            if ($prev->is(SieveToken::COMMENT | SieveToken::WHITESPACE)) {
                continue;
            }

            // use command owning a block or list instead of previous
            if ($prev->is(SieveToken::BLOCK_START | SieveToken::COMMA | SieveToken::LEFT_PARENTHESIS)) {
                $prev = $this->tree->getNode($parent_id);
            }

            return $prev;
        }

        return $this->tree->getNode($parent_id);
    }

    /*******************************************************************************
     * methods for recursive descent start below
     */

    /**
     * Passthrough whitespace comment.
     *
     * @param SieveToken $token
     */
    public function passthroughWhitespaceComment(SieveToken $token): void
    {
        if ($token->is(SieveToken::WHITESPACE)) {
            $this->tree->addChild($token);
        } elseif ($token->is(SieveToken::COMMENT)) {
            /** @var ?SieveToken $parent */

            $parent_id = $this->tree->getLastId();
            do {
                $parent_id = $this->tree->getParent($parent_id) ?? 0;
                $parent = $this->tree->getNode($parent_id);
            } while (isset($parent) && $parent->is(
                SieveToken::WHITESPACE | SieveToken::COMMENT | SieveToken::BLOCK_END | SieveToken::SEMICOLON
            ));

            if (isset($parent)) {
                $this->tree->addChildTo($parent_id, $token);
            } else {
                $this->tree->addChild($token);
            }
        }
    }

    /**
     * Passthrough function.
     *
     * @param SieveToken $token
     */
    public function passthroughFunction(SieveToken $token): void
    {
        $this->tree->addChild($token);
    }

    /**
     * Parse the given script.
     *
     * @param string $script
     * @throws SieveException
     */
    public function parse(string $script): void
    {
        // we reset the registry
        $this->registry = new SieveKeywordRegistry($this->extensions_enabled, $this->custom_extensions);

        $this->script = $script;

        $this->scanner = new SieveScanner($this->script);

        // Define what happens with passthrough tokens like whitespacs and comments
        $this->scanner->setPassthroughFunc(
            [
                $this, 'passthroughWhitespaceComment',
            ]
        );

        $this->tree = new SieveTree('tree');
        $this->commands($this->tree->getRoot());

        if (!$this->scanner->currentTokenIs(SieveToken::SCRIPT_END)) {
            $token = $this->scanner->nextToken();
            throw new SieveException($token, SieveToken::SCRIPT_END);
        }
    }

    /**
     * Get and check command token.
     *
     * @param int $parent_id
     * @throws SieveException
     */
    protected function commands(int $parent_id): void
    {
        $token = null;
        while (true) {
            if (!$this->scanner->nextTokenIs(SieveToken::IDENTIFIER)) {
                break;
            }

            // Get and check a command token
            $token = $this->scanner->nextToken();
            $semantics = new SieveSemantics($this->registry, $token, $this->getPrevToken($parent_id));

            // Process eventual arguments
            $this_node = $this->tree->addChildTo($parent_id, $token);
            $this->arguments($this_node, $semantics);

            $token = $this->scanner->nextToken();
            if (!$token->is(SieveToken::SEMICOLON)) {
                // TODO: check if/when semcheck is needed here
                $semantics->validateToken($token);

                if ($token->is(SieveToken::BLOCK_START)) {
                    $this->tree->addChildTo($this_node, $token);
                    $this->block($this_node, $semantics);
                    continue;
                }

                throw new SieveException($token, SieveToken::SEMICOLON);
            }

            $semantics->done($token);
            $this->tree->addChildTo($this_node, $token);
        }
        if ($this->scanner->nextTokenIs(SieveToken::SCRIPT_END)) {
            $this->scanner->nextToken(); // attach comment to ScriptEnd
            $this->done();
        }
    }

    /**
     * Process arguments.
     *
     * @param int            $parent_id
     * @param SieveSemantics $semantics
     * @throws SieveException
     */
    protected function arguments(int $parent_id, SieveSemantics $semantics): void
    {
        while (true) {
            if ($this->scanner->nextTokenIs(SieveToken::NUMBER | SieveToken::TAG)) {
                // Check if semantics allow a number or tag
                $token = $this->scanner->nextToken();
                $semantics->validateToken($token);
                $this->tree->addChildTo($parent_id, $token);
            } elseif ($this->scanner->nextTokenIs(SieveToken::STRING_LIST)) {
                $this->stringlist($parent_id, $semantics);
            } else {
                break;
            }
        }

        if ($this->scanner->nextTokenIs(SieveToken::TEST_LIST)) {
            $this->testlist($parent_id, $semantics);
        }
    }

    /**
     * Parses a string list..
     *
     * @param int            $parent_id
     * @param SieveSemantics $semantics
     * @throws SieveException
     */
    protected function stringlist($parent_id, $semantics): void
    {
        if (!$this->scanner->nextTokenIs(SieveToken::LEFT_BRACKET)) {
            $this->string($parent_id, $semantics);

            return;
        }

        $token = $this->scanner->nextToken();
        $semantics->startStringList($token);
        $this->tree->addChildTo($parent_id, $token);

        if ($this->scanner->nextTokenIs(SieveToken::RIGHT_BRACKET)) {
            //allow empty lists
            $token = $this->scanner->nextToken();
            $this->tree->addChildTo($parent_id, $token);
            $semantics->endStringList();

            return;
        }

        do {
            $this->string($parent_id, $semantics);
            $token = $this->scanner->nextToken();

            if (!$token->is(SieveToken::COMMA | SieveToken::RIGHT_BRACKET)) {
                throw new SieveException($token, [SieveToken::COMMA, SieveToken::RIGHT_BRACKET]);
            }
            if ($token->is(SieveToken::COMMA)) {
                $semantics->continueStringList();
            }

            $this->tree->addChildTo($parent_id, $token);
        } while (!$token->is(SieveToken::RIGHT_BRACKET));

        $semantics->endStringList();
    }

    /**
     * Processes a string.
     *
     * @param int            $parent_id
     * @param SieveSemantics $semantics
     * @throws SieveException
     */
    protected function string(int $parent_id, SieveSemantics $semantics): void
    {
        $token = $this->scanner->nextToken();
        $semantics->validateToken($token);
        $this->tree->addChildTo($parent_id, $token);
    }

    /**
     * Process a test list.
     *
     * @param int            $parent_id
     * @param SieveSemantics $semantics
     * @throws SieveException
     */
    protected function testlist(int $parent_id, SieveSemantics $semantics): void
    {
        if (!$this->scanner->nextTokenIs(SieveToken::LEFT_PARENTHESIS)) {
            $this->test($parent_id, $semantics);

            return;
        }

        $token = $this->scanner->nextToken();
        $semantics->validateToken($token);
        $this->tree->addChildTo($parent_id, $token);

        do {
            $this->test($parent_id, $semantics);

            $token = $this->scanner->nextToken();
            if (!$token->is(SieveToken::COMMA | SieveToken::RIGHT_PARENTHESIS)) {
                throw new SieveException($token, [SieveToken::COMMA, SieveToken::RIGHT_PARENTHESIS]);
            }
            $this->tree->addChildTo($parent_id, $token);
        } while (!$token->is(SieveToken::RIGHT_PARENTHESIS));
    }

    /**
     * Process a test.
     *
     * @param int            $parent_id
     * @param SieveSemantics $semantics
     * @throws SieveException
     */
    protected function test($parent_id, $semantics): void
    {
        // Check if semantics allow an identifier
        $token = $this->scanner->nextToken();
        $semantics->validateToken($token);

        // Get semantics for this test command
        $this_semantics = new SieveSemantics($this->registry, $token, $this->getPrevToken($parent_id));
        $this_node = $this->tree->addChildTo($parent_id, $token);

        // Consume eventual argument tokens
        $this->arguments($this_node, $this_semantics);

        // Check that all required arguments were there
        $token = $this->scanner->peekNextToken();
        $this_semantics->done($token);
    }

    /**
     * Process a block.
     *
     * @param int $parent_id
     * @throws SieveException
     */
    protected function block(int $parent_id): void
    {
        $this->commands($parent_id);

        if ($this->scanner->currentTokenIs(SieveToken::SCRIPT_END)) {
            throw new SieveException($this->scanner->getCurrentToken(), SieveToken::BLOCK_END);
        }
        $token = $this->scanner->nextToken();
        if (!$token->is(SieveToken::BLOCK_END)) {
            throw new SieveException($token, SieveToken::BLOCK_END);
        }
        $this->tree->addChildTo($parent_id, $token);
    }

    /**
     * Process the last block.
     *
     * @throws SieveException
     */
    protected function done(): void
    {
        $this->registry->validateRequires($this->scanner->getCurrentToken());
    }
}
