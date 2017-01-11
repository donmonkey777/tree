<?php

namespace Donmonkey777\Tree;

interface INestedSet
{
    /**
     * @param int $pk
     * @return static
     */
    public function setPrimaryKey($pk);

    /**
     * @param int $tk
     * @return static
     */
    public function setTreeKey($tk);

    /**
     * @param int $ppk
     * @return static
     */
    public function setParentPrimaryKey($ppk);

    /**
     * @param mixed[] $attributes
     * @return static
     */
    public function setAttributes(array $attributes);

    /**
     * @return int
     */
    public function getPrimaryKey();

    /**
     * @return int
     */
    public function getTreeKey();

    /**
     * @return int
     */
    public function getParentPrimaryKey();

    /**
     * @return mixed[]
     */
    public function getAttributes();

    public function __clone();
}