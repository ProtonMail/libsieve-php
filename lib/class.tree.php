<?php

class Tree
{
	var $childs_;
	var $parents_;
	var $nodes_;
	var $maxId_;
	var $dumpFn_;
	var $dump_;

	function Tree(&$root)
	{
		$this->_construct($root);
	}

	function _construct(&$root)
	{
		$this->childs_ = array();
		$this->parents_ = array();
		$this->nodes_ = array();
		$this->maxId_ = 0;

		$this->parents_[0] = null;
		$this->nodes_[0] = $root;
	}

	function addChild(&$child)
	{
		return $this->addChildTo($this->maxId_, $child);
	}

	function addChildTo($parent_id, &$child)
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

	function getRoot()
	{
		if (!isset($this->nodes_[0]))
		{
			return null;
		}

		return 0;
	}

	function getParent($node_id)
	{
		if (!is_int($node_id) ||
		    !isset($this->nodes_[$node_id]))
		{
			return null;
		}

		return $this->parents_[$node_id];
	}

	function getChilds($node_id)
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

	function getNode($node_id)
	{
		if (!is_int($node_id) ||
		    !isset($this->nodes_[$node_id]))
		{
			return null;
		}

		return $this->nodes_[$node_id];
	}

	function getLastNode($parent_id)
	{
		$childs = $this->getChilds($parent_id);

		for ($i=count($childs); $i>0; --$i)
		{
			$node = $this->getNode($childs[$i-1]);
			if ($node['text'] == '{')
			{
				// return command owning the block
				return $this->getNode($parent_id);
			}
			if ($node['class'] != 'comment')
			{
				return $node;
			}
		}

		return $this->getNode($parent_id);
	}

	function setDumpFunc($callback)
	{
		if ($callback == NULL || is_callable($callback))
		{
			$this->dumpFn_ = $callback;
		}
	}

	function dump()
	{
		$this->dump_ = "tree\n";
		$this->doDump_(0, '', true);
		return $this->dump_;
	}

	function doDump_($node_id, $prefix, $last)
	{
		if ($last)
		{
			$infix = '`--- ';
			$c_prefix = $prefix . '   ';
		}
		else
		{
			$infix = '|--- ';
			$c_prefix = $prefix . '|  ';
		}

		$node = $this->nodes_[$node_id];
		if ($this->dumpFn_ != NULL)
		{
			$this->dump_ .= $prefix . $infix . call_user_func($this->dumpFn_, $node) . "\n";
		}
		else
		{
			$this->dump_ .= "$prefix$infix$node\n";
		}

		$childs = $this->childs_[$node_id];
		for ($i=0; $i<count($childs); ++$i)
		{
			$c_last = false;
			if ($i+1 == count($childs))
			{
				$c_last = true;
			}

			$this->doDump_($childs[$i], $c_prefix, $c_last);
		}
	}
}

?>