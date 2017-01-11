<?php

use Donmonkey777\Tree\NestedSetsMapper;
use Donmonkey777\Tree\TreeViewer;
use Donmonkey777\Tree\Node;

class TreeTest extends AbstractDbTest
{
    public function testFindRoots()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);
        $roots = $mapper->findRoots();
        $this->assertContainsOnlyInstancesOf(Node::class, $roots);
        $this->assertCount(1, $roots);
        $this->assertEquals(1, $roots[0]->getPrimaryKey());
    }

    public function testFindByPrimaryKey()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);
        $node = $mapper->findByPrimaryKey(6);
        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals(6, $node->getPrimaryKey());
        $this->assertEquals(3, $node->getParentPrimaryKey());
    }

    public function testFindChildrenByParentPrimaryKey()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);
        $nodes = $mapper->findChildrenByParentPrimaryKey(3);
        $this->assertContainsOnlyInstancesOf(Node::class, $nodes);
        $this->assertEquals([4, 6, 8], array_map(function (Node $node) {
            return $node->getPrimaryKey();
        }, $nodes));
    }

    public function testInsert()
    {
        // Узел для вставки
        $node = new Node();
        $node->setAttributes(["title" => "A new node of the tree"]);

        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);

        // Вставляем новый узел и проверяем, что ему установились верные значения
        $newNode = $mapper->insertIntoParent($node, 6);
        $this->assertNotEquals($node, $newNode);
        $this->assertEquals(12, $newNode->getPrimaryKey());
        $this->assertEquals(6, $newNode->getParentPrimaryKey());

        // Проверяем, что он действительно добавился в БД
        $newNode = $mapper->findByPrimaryKey(12);
        $this->assertEquals(12, $newNode->getPrimaryKey());
        $this->assertEquals(6, $newNode->getParentPrimaryKey());
        $this->assertEquals(["title" => "A new node of the tree"], $newNode->getAttributes());

        // Пробуем добавить новый корневой узел к дереву
        $newNode = $mapper->insertIntoTree($node, 0);
        $this->tester->seeInDatabase("nodes", ["id" => 13, "left" => 23, "right" => 24, "tree_id" => 0, "level" => 1]);

        // Пробуем вставить узел в несуществующий родительский
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Родительский узел не найден");
        $mapper->insertIntoParent($newNode, 100);
    }

    public function testUpdateAttributes()
    {
        $node = new Node();
        $node->id = 1;
        $node->title = "A new title";

        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);

        // Вставляем заголовок узла
        $mapper->updateAttributes($node);

        // Проверяем
        $this->tester->seeInDatabase("nodes", ["id" => 1, "title" => "A new title"]);
    }

    public function testDelete()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);

        // Удаляем узел
        $mapper->delete(6);

        // Проверяем, что он пропал из БД
        $this->tester->dontSeeInDatabase("nodes", ["id" => 6]);
        $this->tester->seeInDatabase("nodes", ["id" => 3, "left" => 4, "right" => 13]);
    }

    public function testMoveIntoParent()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);

        // Перемещаем узел вниз
        $mapper->moveIntoParent(9, 6);

        // Проверяем изменения в узлах
        $this->tester->seeInDatabase("nodes", ["id" => 9, "left" => 12, "right" => 13, "level" => 4]);
        $this->tester->seeInDatabase("nodes", ["id" => 6, "left" => 9, "right" => 14]);
        $this->tester->seeInDatabase("nodes", ["id" => 8, "left" => 15, "right" => 16]);
        $this->tester->seeInDatabase("nodes", ["id" => 3, "left" => 4, "right" => 17]);

        // Перемещаем узел вверх
        $mapper->moveIntoParent(4, 10);

        // Проверяем изменения в узлах
        $this->tester->seeInDatabase("nodes", ["id" => 4, "left" => 15, "right" => 18, "level" => 3]);
        $this->tester->seeInDatabase("nodes", ["id" => 5, "left" => 16, "right" => 17, "level" => 4]);
        $this->tester->seeInDatabase("nodes", ["id" => 3, "left" => 4, "right" => 13]);
        $this->tester->seeInDatabase("nodes", ["id" => 10, "left" => 14, "right" => 19]);
    }

    public function testMoveToNeighbor()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $mapper = new NestedSetsMapper($db->dbh, Node::class);

        // Перемещаем узел
        $mapper->moveToNeighbor(8, 6);

        // Проверяем изменения в узлах
        $this->tester->seeInDatabase("nodes", ["id" => 8, "left" => 9, "right" => 12, "level" => 3]);
        $this->tester->seeInDatabase("nodes", ["id" => 9, "left" => 10, "right" => 11, "level" => 4]);
        $this->tester->seeInDatabase("nodes", ["id" => 6, "left" => 13, "right" => 16]);
        $this->tester->seeInDatabase("nodes", ["id" => 7, "left" => 14, "right" => 15]);
        $this->tester->seeInDatabase("nodes", ["id" => 3, "left" => 4, "right" => 17]);

        // Перемещаем узел вверх
        $mapper->moveToNeighbor(4, 6);

        // Проверяем изменения в узлах
        $this->tester->seeInDatabase("nodes", ["id" => 4, "left" => 9, "right" => 12, "level" => 3]);
        $this->tester->seeInDatabase("nodes", ["id" => 5, "left" => 10, "right" => 11, "level" => 4]);
        $this->tester->seeInDatabase("nodes", ["id" => 6, "left" => 13, "right" => 16]);
        $this->tester->seeInDatabase("nodes", ["id" => 7, "left" => 14, "right" => 15]);

        // Перемещаем узел после
        $mapper->moveToNeighbor(8, 6, NestedSetsMapper::AFTER);

        // Проверяем изменения в узлах
        $this->tester->seeInDatabase("nodes", ["id" => 8, "left" => 13, "right" => 16, "level" => 3]);
        $this->tester->seeInDatabase("nodes", ["id" => 9, "left" => 14, "right" => 15, "level" => 4]);
        $this->tester->seeInDatabase("nodes", ["id" => 6, "left" => 9, "right" => 12]);
        $this->tester->seeInDatabase("nodes", ["id" => 7, "left" => 10, "right" => 11]);

        // Перемещаем узел в корень
        $mapper->moveToNeighbor(4, 1, NestedSetsMapper::AFTER);
        $this->tester->seeInDatabase("nodes", ["id" => 4, "left" => 17, "right" => 20, "level" => 1]);
        $this->tester->seeInDatabase("nodes", ["id" => 5, "left" => 18, "right" => 19, "level" => 2]);
        $this->tester->seeInDatabase("nodes", ["id" => 1, "left" => 1, "right" => 16]);

        // Перемещаем узел в корень
        $mapper->moveToNeighbor(5, 1, NestedSetsMapper::BEFORE);
        $this->tester->seeInDatabase("nodes", ["id" => 5, "left" => 1, "right" => 2, "level" => 1]);
        $this->tester->seeInDatabase("nodes", ["id" => 1, "left" => 3, "right" => 18]);
        $this->tester->seeInDatabase("nodes", ["id" => 4, "left" => 19, "right" => 20]);
    }

    public function testTitle()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $treeViewer = new TreeViewer($db->dbh, 0);

        // Слишком длинный заголовок для редактирования
        $this->assertFalse($treeViewer->edit(1, str_repeat("a", 255)));
        $this->assertNull($treeViewer->getResult());
        $this->assertEquals("Неверные параметры запроса", $treeViewer->getErrorMessage());
        $this->assertEquals(new Exception("Слишком длинный заголовок узла"), $treeViewer->getError());

        // Слишком длинный заголовок для создания
        $this->assertFalse($treeViewer->create(str_repeat("a", 255), 1));
        $this->assertNull($treeViewer->getResult());
        $this->assertEquals("Неверные параметры запроса", $treeViewer->getErrorMessage());
        $this->assertEquals(new Exception("Слишком длинный заголовок узла"), $treeViewer->getError());

        // Заголовок в 100 символов
        $title = str_repeat("a", 100);
        $this->assertTrue($treeViewer->edit(1, $title));
        $this->tester->seeInDatabase("nodes", ["id" => 1, "title" => $title]);
    }

    public function testMoveError()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $treeViewer = new TreeViewer($db->dbh, 0);

        // Разные деревья
        $this->assertFalse($treeViewer->move(11, 1, "before"));
        $this->assertNull($treeViewer->getResult());
        $this->assertEquals("Неверные параметры запроса", $treeViewer->getErrorMessage());
        $this->assertEquals(new Exception("Узлы принадлежат разным дервьям"), $treeViewer->getError());

        // Дочерние узлы
        $this->assertFalse($treeViewer->move(1, 2, "over"));
        $this->assertNull($treeViewer->getResult());
        $this->assertEquals("Неверные параметры запроса", $treeViewer->getErrorMessage());
        $this->assertEquals(new Exception("Узел не может быть перемещен в свой дочерний узел"), $treeViewer->getError());

        // Перемещаемый узел не найден
        $this->assertFalse($treeViewer->move(1000, 2, "after"));
        $this->assertNull($treeViewer->getResult());
        $this->assertEquals("Неверные параметры запроса", $treeViewer->getErrorMessage());
        $this->assertEquals(new Exception("Узел не найден"), $treeViewer->getError());
    }

    public function testSelectPlaceToCreate()
    {
        /** @var \Codeception\Module\Db $db */
        $db = $this->getModule("Db");

        $treeViewer = new TreeViewer($db->dbh, 0);

        // Создаем в родительском узле
        $treeViewer->create("Example 1", 1);
        $this->tester->seeInDatabase("nodes", ["title" => "Example 1", "level" => 2]);

        // Создаем корневой узел
        $treeViewer->create("Example 2", "just a random string or null");
        $this->tester->seeInDatabase("nodes", ["title" => "Example 2", "level" => 1]);
    }
}