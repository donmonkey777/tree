<?php

namespace Donmonkey777\Tree;

use PDOException;
use Exception;
use PDO;

class TreeViewer
{
    /**
     * @var int Идентификатор дерева
     */
    protected $treeId;

    /**
     * @var \Donmonkey777\Tree\NestedSetsMapper
     */
    protected $mapper;

    /**
     * @var mixed Результат выполнения запроса
     */
    protected $result;

    /**
     * @var string Текст ошибки
     */
    protected $errorMessage;

    /**
     * @var \Exception Ошибка
     */
    protected $error;

    public function __construct(PDO $pdo, $treeId)
    {
        $this->treeId = $treeId;
        $this->mapper = new NestedSetsMapper($pdo, Node::class);
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function getError()
    {
        return $this->error;
    }

    public function reset()
    {
        $this->result = null;
        $this->errorMessage = null;
        $this->error = null;
    }

    /**
     * Получение узлов дерева
     *
     * @param int $parentKey
     * @return bool Статус операции
     */
    public function get($parentKey = null)
    {
        $this->reset();

        try {
            $this->result = [];

            if (is_null($parentKey)) {
                // Получаем корневые узлы дерева
                foreach ($this->mapper->findRoots($this->treeId) as $root) {
                    $this->result[] = [
                        "key" => $root->id,
                        "title" => $root->title,
                        "folder" => true,
                        "expanded" => false,
                        "lazy" => true,
                    ];
                }
            } else {
                // Получаем дочерние узлы
                foreach ($this->mapper->findChildrenByParentPrimaryKey($parentKey) as $child) {
                    $this->result[] = [
                        "key" => $child->id,
                        "title" => $child->title,
                        "folder" => true,
                        "expanded" => false,
                        "lazy" => true,
                    ];
                }
            }
        } catch (PDOException $e) {
            $this->errorMessage = "Произошла неизвестная ошибка";
            $this->error = $e;
            return false;
        } catch (Exception $e) {
            $this->errorMessage = "Неверные параметры запроса";
            $this->error = $e;
            return false;
        }

        return true;
    }

    /**
     * Перемещение узла по дереву
     *
     * @param int $key
     * @param int $targetKey
     * @param string $place Тип перемещения.
     * Допустимые значения `over`, `before`, `after` (вставка в конец родителя, вставка до узла и вставка после узла соответственно)
     * @return bool Статус операции
     */
    public function move($key, $targetKey, $place)
    {
        $this->reset();

        try {
            if ($place == "over") {
                $this->mapper->moveIntoParent($key, $targetKey);
            } else {
                $this->mapper->moveToNeighbor($key, $targetKey, $place);
            }
        } catch (PDOException $e) {
            $this->errorMessage = "Произошла неизвестная ошибка";
            $this->error = $e;
            return false;
        } catch (Exception $e) {
            $this->errorMessage = "Неверные параметры запроса";
            $this->error = $e;
            return false;
        }

        return true;
    }

    /**
     * Добавление нового узла к дереву
     *
     * @param string $title
     * @param int $parentKey
     * @return bool Статус операции
     */
    public function create($title, $parentKey)
    {
        $this->reset();

        try {
            if (strlen($title) > 100) {
                throw new Exception("Слишком длинный заголовок узла");
            }

            $node = new Node();
            $node->title = $title;

            if ($parentKey > 0) {
                $this->result = $this->mapper->insertIntoParent($node, $parentKey);
            } else {
                $this->result = $this->mapper->insertIntoTree($node, 0);
            }
        } catch (PDOException $e) {
            $this->errorMessage = "Произошла неизвестная ошибка";
            $this->error = $e;
            return false;
        } catch (Exception $e) {
            $this->errorMessage = "Неверные параметры запроса";
            $this->error = $e;
            return false;
        }

        return true;
    }

    /**
     * Удаление узла
     *
     * @param int $key
     * @return bool Статус операции
     */
    public function delete($key)
    {
        $this->reset();

        try {
            $this->mapper->delete($key);
        } catch (PDOException $e) {
            $this->errorMessage = "Произошла неизвестная ошибка";
            $this->error = $e;
            return false;
        } catch (Exception $e) {
            $this->errorMessage = "Неверные параметры запроса";
            $this->error = $e;
            return false;
        }

        return true;
    }

    /**
     * Редактирование узла
     *
     * @param int $key
     * @param string $title
     * @return bool Статус операции
     */
    public function edit($key, $title)
    {
        $this->reset();

        try {
            if (strlen($title) > 100) {
                throw new Exception("Слишком длинный заголовок узла");
            }

            $node = new Node();
            $node->id = $key;
            $node->title = $title;

            $this->mapper->updateAttributes($node);
        } catch (PDOException $e) {
            $this->errorMessage = "Произошла неизвестная ошибка";
            $this->error = $e;
            return false;
        } catch (Exception $e) {
            $this->errorMessage = "Неверные параметры запроса";
            $this->error = $e;
            return false;
        }

        return true;
    }
}