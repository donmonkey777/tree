<?php

use Phinx\Seed\AbstractSeed;
use Faker\Factory as Faker;

class NestedSetsSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $table = $this->table("nodes");
        $table
            ->insert(_gen(0, 1, 20, 1))
            ->insert(_gen(0, 2, 3, 2))
            ->insert(_gen(0, 4, 17, 2))
            ->insert(_gen(0, 5, 8, 3))
            ->insert(_gen(0, 6, 7, 4))
            ->insert(_gen(0, 9, 12, 3))
            ->insert(_gen(0, 10, 11, 4))
            ->insert(_gen(0, 13, 16, 3))
            ->insert(_gen(0, 14, 15, 4))
            ->insert(_gen(0, 18, 19, 2))
            ->insert(_gen(1, 1, 2, 1))
            ->save();
    }
}

function _gen($treeId, $left, $right, $level)
{
    return [
        "tree_id" => $treeId,
        "left" => $left,
        "right" => $right,
        "level" => $level,
        "title" => Faker::create()->unique()->company,
    ];
}