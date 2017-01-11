<?php

use Phinx\Migration\AbstractMigration;

class CreateTableNodes extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $tree = $this->table("nodes");
        $tree
            ->addColumn("tree_id", "integer")
            ->addColumn("left", "integer")
            ->addColumn("right", "integer")
            ->addColumn("level", "integer")
            ->addColumn("title", "string", array("null" => true, "limit" => 100))
            ->create();
    }
}