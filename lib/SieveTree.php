<?php

namespace Sieve;

class SieveTree
{
    protected $children;
    protected $parents;
    protected $nodes;
    protected $max_id;
    protected $dump;

    /**
     * SieveTree constructor.
     *
     * @param string $name
     */
    public function __construct($name = 'tree')
    {
        $this->children = [];
        $this->parents = [];
        $this->nodes = [];
        $this->max_id = 0;

        $this->parents = [null];
        $this->nodes = [$name];
    }

    /**
     * Add child to last node.
     *
     * @param SieveToken $child
     * @return int
     */
    public function addChild(SieveToken $child): ?int
    {
        return $this->addChildTo($this->max_id, $child);
    }

    /**
     * Add child to given parent.
     *
     * @param int           $parent_id
     * @param SieveToken $child
     * @return int|null
     */
    public function addChildTo(int $parent_id, SieveToken $child): ?int
    {
        if (!is_int($parent_id) || !isset($this->nodes[$parent_id])) {
            return null;
        }

        if (!isset($this->children[$parent_id])) {
            $this->children[$parent_id] = [];
        }

        $child_id = ++$this->max_id;
        $this->nodes[$child_id] = $child;
        $this->parents[$child_id] = $parent_id;
        array_push($this->children[$parent_id], $child_id);

        return $child_id;
    }

    /**
     * Get root Id.
     *
     * @return int
     */
    public function getRoot(): int
    {
        return 0;
    }

    /**
     * Get children of a specific node.
     *
     * @param int $node_id
     * @return int[]|null the child ids or null, if parent node not found
     */
    public function getChildren(int $node_id): ?array
    {
        if (!isset($this->nodes[$node_id])) {
            return null;
        }

        return $this->children[$node_id] ?? [];
    }

    /**
     * Get node from Id.
     *
     * @param int $node_id
     * @return SieveToken|null
     */
    public function getNode(int $node_id): ?SieveToken
    {
        if ($node_id === 0 || !isset($this->nodes[$node_id])) {
            return null;
        }

        return $this->nodes[$node_id];
    }

    /**
     * Get parent from child id.
     *
     * @param int $node_id
     * @return int|null
     */
    public function getParent(int $node_id): ?int
    {
        return $this->parents[$node_id] ?? null;
    }

    /**
     * Get last id.
     *
     * @return int
     */
    public function getLastId(): int
    {
        return $this->max_id;
    }

    /**
     * Dump the tree.
     *
     * @return string
     */
    public function dump(): string
    {
        return $this->nodes[$this->getRoot()] . "\n" . $this->dumpChildren($this->getRoot(), ' ');
    }

    /**
     * Dump children of given node.
     *
     * @param int    $parent_id
     * @param string $prefix
     * @return string
     */
    protected function dumpChildren(int $parent_id, string $prefix): string
    {
        $children = $this->children[$parent_id] ?? [];
        $last_child = count($children);
        $dump = '';
        for ($i = 1; $i <= $last_child; ++$i) {
            $child_node = $this->nodes[$children[$i - 1]];
            $infix = ($i === $last_child ? '`--- ' : '|--- ');
            $dump .= $prefix . $infix . $child_node->dump() . ' (id:' . $children[$i - 1] . ")\n";

            $next_prefix = $prefix . ($i === $last_child ? '   ' : '|  ');
            $dump .= $this->dumpChildren($children[$i - 1], $next_prefix);
        }
        return $dump;
    }

    /**
     * Get text.
     *
     * @return string
     */
    public function getText(): string
    {
        return $this->childrenText($this->getRoot());
    }

    /**
     * Get child text.
     *
     * @param $parent_id
     * @return string
     */
    protected function childrenText($parent_id): string
    {
        $children = $this->children[$parent_id] ?? [];

        $dump = '';
        for ($i = 0; $i < count($children); ++$i) {
            $child_node = $this->nodes[$children[$i]];
            $dump .= $child_node->text();
            $dump .= $this->childrenText($children[$i]);
        }
        return $dump;
    }
}
