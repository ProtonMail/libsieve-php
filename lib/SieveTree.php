<?php

declare(strict_types=1);

namespace Sieve;

class SieveTree
{
    protected array $children;
    protected array $parents;
    protected array $nodes;
    protected int $maxId;

    /**
     * SieveTree constructor.
     *
     * @param string $name
     */
    public function __construct(string $name = 'tree')
    {
        $this->children = [];
        $this->maxId = 0;

        $this->parents = [null];
        $this->nodes = [$name];
    }

    /**
     * Add child to last node.
     */
    public function addChild(SieveToken $child): ?int
    {
        return $this->addChildTo($this->maxId, $child);
    }

    /**
     * Add child to given parent.
     */
    public function addChildTo(int $parentId, SieveToken $child): ?int
    {
        if (!isset($this->nodes[$parentId])) {
            return null;
        }

        if (!isset($this->children[$parentId])) {
            $this->children[$parentId] = [];
        }

        $childId = ++$this->maxId;
        $this->nodes[$childId] = $child;
        $this->parents[$childId] = $parentId;
        $this->children[$parentId][] = $childId;

        return $childId;
    }

    /**
     * Get root Id.
     */
    public function getRoot(): int
    {
        return 0;
    }

    /**
     * Get children of a specific node.
     *
     * @return int[]|null the child ids or null, if parent node not found
     */
    public function getChildren(int $nodeId): ?array
    {
        if (!isset($this->nodes[$nodeId])) {
            return null;
        }

        return $this->children[$nodeId] ?? [];
    }

    /**
     * Get node from Id.
     */
    public function getNode(int $nodeId): ?SieveToken
    {
        if ($nodeId === 0 || !isset($this->nodes[$nodeId])) {
            return null;
        }

        return $this->nodes[$nodeId] ?? null;
    }

    /**
     * Get parent from child id.
     */
    public function getParent(int $nodeId): ?int
    {
        return $this->parents[$nodeId] ?? null;
    }

    /**
     * Get last id.
     */
    public function getLastId(): int
    {
        return $this->maxId;
    }

    /**
     * Dump the tree.
     */
    public function dump(): string
    {
        return $this->nodes[$this->getRoot()] . "\n" . $this->dumpChildren($this->getRoot(), ' ');
    }

    /**
     * Dump children of given node.
     */
    protected function dumpChildren(int $parentId, string $prefix): string
    {
        $children = $this->children[$parentId] ?? [];
        $lastChild = count($children);
        $dump = '';
        for ($i = 1; $i <= $lastChild; ++$i) {
            $childNode = $this->nodes[$children[$i - 1]];
            $infix = ($i === $lastChild ? '`--- ' : '|--- ');
            $dump .= $prefix . $infix . $childNode->dump() . ' (id:' . $children[$i - 1] . ")\n";

            $nextPrefix = $prefix . ($i === $lastChild ? '   ' : '|  ');
            $dump .= $this->dumpChildren($children[$i - 1], $nextPrefix);
        }
        return $dump;
    }

    /**
     * Get text.
     */
    public function getText(): string
    {
        return $this->childrenText($this->getRoot());
    }

    /**
     * Get child text.
     */
    protected function childrenText(int $parentId): string
    {
        $children = $this->children[$parentId] ?? [];

        $dump = '';
        foreach ($children as $iValue) {
            $childNode = $this->nodes[$iValue];
            $dump .= $childNode->text();
            $dump .= $this->childrenText($iValue);
        }
        return $dump;
    }
}
