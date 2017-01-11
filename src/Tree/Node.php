<?php

namespace Donmonkey777\Tree;

class Node implements INestedSet
{
    public $id;
    public $parentId;
    public $treeId;
    public $title;

    public function setPrimaryKey($pk)
    {
        $this->id = $pk;
        return $this;
    }

    public function setTreeKey($tk)
    {
        $this->treeId = $tk;
        return $this;
    }

    public function setParentPrimaryKey($ppk)
    {
        $this->parentId = $ppk;
        return $this;
    }

    public function setAttributes(array $attributes)
    {
        $this->title = $attributes["title"] ?? null;
        return $this;
    }

    public function getPrimaryKey()
    {
        return $this->id;
    }

    public function getTreeKey()
    {
        return $this->treeId;
    }

    public function getParentPrimaryKey()
    {
        return $this->parentId;
    }

    public function getAttributes()
    {
        return ["title" => $this->title];
    }

    public function __clone()
    {
    }
}