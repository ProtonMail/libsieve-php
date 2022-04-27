<?php

declare(strict_types=1);

namespace Sieve;

use Exception;

class SieveParser
{
    protected ?SieveScanner $scanner = null;
    protected ?string $script = null;
    protected ?SieveTree $tree = null;
    protected ?SieveKeywordRegistry $registry = null;

    private ?array $extensionsEnabled;
    private array $customExtensions;

    public function __construct(?array $extensionsEnabled = null, array $customExtensions = [])
    {
        // just to check the errors in the constructor
        $this->registry = new SieveKeywordRegistry($extensionsEnabled, $customExtensions);
        $this->extensionsEnabled = $extensionsEnabled;
        $this->customExtensions = $customExtensions;
    }

    /**
     * Get parsed tree.
     *
     * @throws Exception
     */
    public function getParseTree(): SieveTree
    {
        if ($this->tree === null) {
            throw new Exception('Tree not initialized');
        }

        return $this->tree;
    }

    /**
     * Dump parse tree.
     */
    public function dumpParseTree(): string
    {
        return $this->tree->dump();
    }

    /**
     * Get script text.
     */
    public function getScriptText(): string
    {
        return $this->tree->getText();
    }

    /**
     * Get previous token.
     */
    protected function getPrevToken(int $parentId): ?SieveToken
    {
        $children = $this->tree->getChildren($parentId);

        for ($i = count($children); $i > 0; --$i) {
            $prev = $this->tree->getNode($children[$i - 1]);
            if ($prev->is(SieveToken::COMMENT | SieveToken::WHITESPACE)) {
                continue;
            }

            // use command owning a block or list instead of previous
            if ($prev->is(SieveToken::BLOCK_START | SieveToken::COMMA | SieveToken::LEFT_PARENTHESIS)) {
                $prev = $this->tree->getNode($parentId);
            }

            return $prev;
        }

        return $this->tree->getNode($parentId);
    }

    /*******************************************************************************
     * methods for recursive descent start below
     */

    /**
     * PassThrough whitespace comment.
     */
    public function passThroughWhitespaceComment(SieveToken $token): void
    {
        if ($token->is(SieveToken::WHITESPACE)) {
            $this->tree->addChild($token);
        } elseif ($token->is(SieveToken::COMMENT)) {
            /** @var ?SieveToken $parent */

            $parentId = $this->tree->getLastId();
            do {
                $parentId = $this->tree->getParent($parentId) ?? 0;
                $parent = $this->tree->getNode($parentId);
            } while (isset($parent) &&
                $parent->is(
                    SieveToken::WHITESPACE | SieveToken::COMMENT | SieveToken::BLOCK_END | SieveToken::SEMICOLON
                )
            );

            if (isset($parent)) {
                $this->tree->addChildTo($parentId, $token);
            } else {
                $this->tree->addChild($token);
            }
        }
    }

    /**
     * Passthrough function.
     */
    public function passThroughFunction(SieveToken $token): void
    {
        $this->tree->addChild($token);
    }

    /**
     * Parse the given script.
     *
     * @throws SieveException
     */
    public function parse(string $script): void
    {
        // we reset the registry
        $this->registry = new SieveKeywordRegistry($this->extensionsEnabled, $this->customExtensions);

        $this->script = $script;

        $this->scanner = new SieveScanner($this->script);

        // Define what happens with passthrough tokens like whitespaces and comments
        $this->scanner->setPassthroughFunc([$this, 'passThroughWhitespaceComment']);

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
     * @throws SieveException
     */
    protected function commands(int $parentId): void
    {
        while (true) {
            if (!$this->scanner->nextTokenIs(SieveToken::IDENTIFIER)) {
                break;
            }

            // Get and check a command token
            $token = $this->scanner->nextToken();
            $semantics = new SieveSemantics($this->registry, $token, $this->getPrevToken($parentId));

            // Process eventual arguments
            $thisNode = $this->tree->addChildTo($parentId, $token);
            $this->arguments($thisNode, $semantics);

            $token = $this->scanner->nextToken();
            if (!$token->is(SieveToken::SEMICOLON)) {
                // TODO: check if/when semcheck is needed here
                $semantics->validateToken($token);

                if ($token->is(SieveToken::BLOCK_START)) {
                    $this->tree->addChildTo($thisNode, $token);
                    $this->block($thisNode);
                    continue;
                }

                throw new SieveException($token, SieveToken::SEMICOLON);
            }

            $semantics->done($token);
            $this->tree->addChildTo($thisNode, $token);
        }
        if ($this->scanner->nextTokenIs(SieveToken::SCRIPT_END)) {
            $this->scanner->nextToken(); // attach comment to ScriptEnd
            $this->done();
        }
    }

    /**
     * Process arguments.
     *
     * @throws SieveException
     */
    protected function arguments(int $parentId, SieveSemantics $semantics): void
    {
        while (true) {
            if ($this->scanner->nextTokenIs(SieveToken::NUMBER | SieveToken::TAG)) {
                // Check if semantics allow a number or tag
                $token = $this->scanner->nextToken();
                $semantics->validateToken($token);
                $this->tree->addChildTo($parentId, $token);
            } elseif ($this->scanner->nextTokenIs(SieveToken::STRING_LIST)) {
                $this->stringList($parentId, $semantics);
            } else {
                break;
            }
        }

        if ($this->scanner->nextTokenIs(SieveToken::TEST_LIST)) {
            $this->testList($parentId, $semantics);
        }
    }

    /**
     * Parses a string list.
     *
     * @throws SieveException
     */
    protected function stringList(int $parentId, SieveSemantics $semantics): void
    {
        if (!$this->scanner->nextTokenIs(SieveToken::LEFT_BRACKET)) {
            $this->string($parentId, $semantics);

            return;
        }

        $token = $this->scanner->nextToken();
        $semantics->startStringList($token);
        $this->tree->addChildTo($parentId, $token);

        if ($this->scanner->nextTokenIs(SieveToken::RIGHT_BRACKET)) {
            //allow empty lists
            $token = $this->scanner->nextToken();
            $this->tree->addChildTo($parentId, $token);
            $semantics->endStringList();

            return;
        }

        do {
            $this->string($parentId, $semantics);
            $token = $this->scanner->nextToken();

            if (!$token->is(SieveToken::COMMA | SieveToken::RIGHT_BRACKET)) {
                throw new SieveException($token, [SieveToken::COMMA, SieveToken::RIGHT_BRACKET]);
            }
            if ($token->is(SieveToken::COMMA)) {
                $semantics->continueStringList();
            }

            $this->tree->addChildTo($parentId, $token);
        } while (!$token->is(SieveToken::RIGHT_BRACKET));

        $semantics->endStringList();
    }

    /**
     * Processes a string.
     *
     * @throws SieveException
     */
    protected function string(int $parentId, SieveSemantics $semantics): void
    {
        $token = $this->scanner->nextToken();
        $semantics->validateToken($token);
        $this->tree->addChildTo($parentId, $token);
    }

    /**
     * Process a test list.
     *
     * @throws SieveException
     */
    protected function testList(int $parentId, SieveSemantics $semantics): void
    {
        if (!$this->scanner->nextTokenIs(SieveToken::LEFT_PARENTHESIS)) {
            $this->test($parentId, $semantics);

            return;
        }

        $token = $this->scanner->nextToken();
        $semantics->validateToken($token);
        $this->tree->addChildTo($parentId, $token);

        do {
            $this->test($parentId, $semantics);

            $token = $this->scanner->nextToken();
            if (!$token->is(SieveToken::COMMA | SieveToken::RIGHT_PARENTHESIS)) {
                throw new SieveException($token, [SieveToken::COMMA, SieveToken::RIGHT_PARENTHESIS]);
            }
            $this->tree->addChildTo($parentId, $token);
        } while (!$token->is(SieveToken::RIGHT_PARENTHESIS));
    }

    /**
     * Process a test.
     *
     * @throws SieveException
     */
    protected function test(int $parentId, SieveSemantics $semantics): void
    {
        // Check if semantics allow an identifier
        $token = $this->scanner->nextToken();
        $semantics->validateToken($token);

        // Get semantics for this test command
        $thisSemantics = new SieveSemantics($this->registry, $token, $this->getPrevToken($parentId));
        $thisNode = $this->tree->addChildTo($parentId, $token);

        // Consume eventual argument tokens
        $this->arguments($thisNode, $thisSemantics);

        // Check that all required arguments were there
        $token = $this->scanner->peekNextToken();
        $thisSemantics->done($token);
    }

    /**
     * Process a block.
     *
     * @throws SieveException
     */
    protected function block(int $parentId): void
    {
        $this->commands($parentId);

        if ($this->scanner->currentTokenIs(SieveToken::SCRIPT_END)) {
            throw new SieveException($this->scanner->getCurrentToken(), SieveToken::BLOCK_END);
        }
        $token = $this->scanner->nextToken();
        if (!$token->is(SieveToken::BLOCK_END)) {
            throw new SieveException($token, SieveToken::BLOCK_END);
        }
        $this->tree->addChildTo($parentId, $token);
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
