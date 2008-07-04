<?php

class Tree
{
	protected $childs_;
	protected $parents_;
	protected $nodes_;
	protected $maxId_;
	protected $dumpFn_;
	protected $dump_;
	protected $name_;

	public function __construct(Dumpable $root, $name = null)
	{
		$this->childs_ = array();
		$this->parents_ = array();
		$this->nodes_ = array();
		$this->maxId_ = 0;
		$this->name_ = $name;

		$this->parents_[0] = null;
		$this->nodes_[0] = $root;
	}

	public function addChild(Dumpable $child)
	{
		return $this->addChildTo($this->maxId_, $child);
	}

	public function addChildTo($parent_id, Dumpable $child)
	{
		if (!is_int($parent_id) ||
		    !isset($this->nodes_[$parent_id]))
		{
			return null;
		}

		if (!isset($this->childs_[$parent_id]))
		{
			$this->childs_[$parent_id] = array();
		}

		$child_id = ++$this->maxId_;
		$this->nodes_[$child_id] = $child;
		$this->parents_[$child_id] = $parent_id;
		array_push($this->childs_[$parent_id], $child_id);

		return $child_id;
	}

	public function root()
	{
		if (!isset($this->nodes_[0]))
		{
			return null;
		}

		return 0;
	}

	public function getParent($node_id)
	{
		if (!is_int($node_id) ||
		    !isset($this->nodes_[$node_id]))
		{
			return null;
		}

		return $this->parents_[$node_id];
	}

	public function getChilds($node_id)
	{
		if (!is_int($node_id) ||
		    !isset($this->nodes_[$node_id]))
		{
			return null;
		}

		if (!isset($this->childs_[$node_id]))
		{
			return array();
		}

		return $this->childs_[$node_id];
	}

	public function getNode($node_id)
	{
		if (!is_int($node_id) ||
		    !isset($this->nodes_[$node_id]))
		{
			return null;
		}

		return $this->nodes_[$node_id];
	}

	public function dump()
	{
		$this->dump_ = (is_null($this->name_) ? 'tree' : $this->name_) ."\n";
		$this->doDump_(0, '', true);
		return $this->dump_;
	}

	protected function doDump_($node_id, $prefix, $last)
	{
		if ($last)
		{
			$infix = '`--- ';
			$child_prefix = $prefix .'   ';
		}
		else
		{
			$infix = '|--- ';
			$child_prefix = $prefix .'|  ';
		}

		$node = $this->nodes_[$node_id];
		$this->dump_ .= $prefix . $infix . $node->dump() ."\n";

		if (isset($this->childs_[$node_id]))
		{
			$childs = $this->childs_[$node_id];
			$last_child = count($childs);

			for ($i=1; $i <= $last_child; ++$i)
			{
				$this->doDump_($childs[$i-1], $child_prefix, ($i == $last_child ? true : false));
			}
		}
	}
}

?>