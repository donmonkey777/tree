<?php

namespace Donmonkey777\Tree;

use \PDOException;
use \Exception;
use \PDO;

class NestedSetsMapper
{
    const BEFORE = "before";
    const AFTER = "after";

    protected $pdo;
    protected $nodeClassName;
    protected $tableName;
    protected $primaryKeyColumnName;
    protected $treeKeyColumnName;
    protected $leftKeyColumnName;
    protected $rightKeyColumnName;
    protected $levelColumnName;

    public function __construct(
        PDO $pdo,
        $nodeClassName,
        $tableName = "nodes",
        $primaryKeyColumnName = "id",
        $treeKeyColumnName = "tree_id",
        $leftKeyColumnName = "left",
        $rightKeyColumnName = "right",
        $levelColumnName = "level"
    )
    {
        $this->pdo = $pdo;
        $this->nodeClassName = $nodeClassName;
        $this->tableName = $tableName;
        $this->primaryKeyColumnName = $primaryKeyColumnName;
        $this->treeKeyColumnName = $treeKeyColumnName;
        $this->leftKeyColumnName = $leftKeyColumnName;
        $this->rightKeyColumnName = $rightKeyColumnName;
        $this->levelColumnName = $levelColumnName;

        // Переводим PDO в режим
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Возвращает корни дерева.
     *
     * @param int $treeId
     * @param array $attributes
     * @return \Donmonkey777\Tree\INestedSet[]
     * @throws \PDOException
     */
    public function findRoots($treeId = 0, array $attributes = ["title"])
    {
        // Добавляем поля для выборки
        $columns = $this->getColumns($attributes);

        $statement = $this->pdo->prepare(sprintf(
            "select %s from %s where %s = ? and %s = 1 order by %s asc",
            implode(", ", array_map([$this, "prepareName"], $columns)),
            $this->prepareName($this->tableName),
            $this->prepareName($this->treeKeyColumnName),
            $this->prepareName($this->levelColumnName),
            $this->prepareName($this->leftKeyColumnName)
        ));
        $statement->execute([$treeId]);

        $result = [];
        while (($data = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            /** @var \Donmonkey777\Tree\INestedSet $node */
            $node = new $this->nodeClassName;
            $node->setPrimaryKey($data[$this->primaryKeyColumnName]);
            $node->setParentPrimaryKey(null);
            $node->setTreeKey($data[$this->treeKeyColumnName]);

            // Устанавливаем значения атрибутов
            $node->setAttributes(array_filter($data, function ($key) use ($attributes) {
                return in_array($key, $attributes);
            }, ARRAY_FILTER_USE_KEY));

            $result[] = $node;
        }
        return $result;
    }

    /**
     * Возвращает произвольный узел дерева по его ключу.
     * Если узел не найден - возвращает null.
     *
     * @param int $pk
     * @param array $attributes
     * @return \Donmonkey777\Tree\INestedSet|null
     * @throws \PDOException
     */
    public function findByPrimaryKey($pk, array $attributes = ["title"])
    {
        // Добавляем поля для выборки
        $columns = $this->getColumns($attributes);

        // Получаем синонимы для дочерней и родительской записей
        $childTableNameSyn = $this->prepareName($this->tableName . "_1");
        $parentTableNameSyn = $this->prepareName($this->tableName . "_2");

        $statement = $this->pdo->prepare(sprintf(
            "select %s, (select %s from %s as %s where %s < %s and %s > %s and %s = %s and %s = %s - 1) as %s from %s as %s where %s = ?",
            implode(", ", array_map([$this, "prepareName"], $columns)),
            $parentTableNameSyn . "." . $this->prepareName($this->primaryKeyColumnName),
            $this->prepareName($this->tableName),
            $parentTableNameSyn,
            $parentTableNameSyn . "." . $this->prepareName($this->leftKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->leftKeyColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->rightKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->rightKeyColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->treeKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->treeKeyColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->levelColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->levelColumnName),
            $this->prepareName("parent_primary_key"),
            $this->prepareName($this->tableName),
            $childTableNameSyn,
            $this->prepareName($this->primaryKeyColumnName)
        ));
        $statement->execute([$pk]);

        // Если узел не найден - возвращаем `null`
        if (($data = $statement->fetch(PDO::FETCH_ASSOC)) === false) {
            return null;
        }

        /** @var \Donmonkey777\Tree\INestedSet $node */
        $node = new $this->nodeClassName;
        $node->setPrimaryKey($data[$this->primaryKeyColumnName]);
        $node->setParentPrimaryKey($data["parent_primary_key"]);
        $node->setTreeKey($data[$this->treeKeyColumnName]);

        // Устанавливаем значения атрибутов
        $node->setAttributes(array_filter($data, function ($key) use ($attributes) {
            return in_array($key, $attributes);
        }, ARRAY_FILTER_USE_KEY));

        return $node;
    }

    /**
     * Возвращает дочерние узлы родителя.
     * Если родительский узел не найден - возвращает пустой массив.
     *
     * @param int $parentPk
     * @param array $attributes
     * @return \Donmonkey777\Tree\INestedSet[]
     * @throws \PDOException
     */
    public function findChildrenByParentPrimaryKey($parentPk, array $attributes = ["title"])
    {
        // Добавляем поля для выборки
        $columns = $this->getColumns($attributes);

        // Получаем синонимы для дочерней и родительской записей
        $childTableNameSyn = $this->prepareName($this->tableName . "_1");
        $parentTableNameSyn = $this->prepareName($this->tableName . "_2");

        $statement = $this->pdo->prepare(sprintf(
            "select %s, %s as %s from %s as %s inner join %s as %s on %s < %s and %s > %s and %s + 1 = %s and %s = %s where %s = ? order by %s asc",
            implode(", ", array_map(function ($columnName) use ($childTableNameSyn) {
                return $childTableNameSyn . "." . $this->prepareName($columnName);
            }, $columns)),
            $parentTableNameSyn . "." . $this->prepareName($this->primaryKeyColumnName),
            $this->prepareName("parent_primary_key"),
            $this->prepareName($this->tableName),
            $parentTableNameSyn,
            $this->prepareName($this->tableName),
            $childTableNameSyn,
            $parentTableNameSyn . "." . $this->prepareName($this->leftKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->leftKeyColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->rightKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->rightKeyColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->levelColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->levelColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->treeKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->treeKeyColumnName),
            $parentTableNameSyn . "." . $this->prepareName($this->primaryKeyColumnName),
            $childTableNameSyn . "." . $this->prepareName($this->leftKeyColumnName)
        ));
        $statement->execute([$parentPk]);

        $result = [];
        while (($data = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            /** @var \Donmonkey777\Tree\INestedSet $node */
            $node = new $this->nodeClassName;
            $node->setPrimaryKey($data[$this->primaryKeyColumnName]);
            $node->setParentPrimaryKey($data["parent_primary_key"]);
            $node->setTreeKey($data[$this->treeKeyColumnName]);

            // Устанавливаем значения атрибутов
            $node->setAttributes(array_filter($data, function ($key) use ($attributes) {
                return in_array($key, $attributes);
            }, ARRAY_FILTER_USE_KEY));

            $result[] = $node;
        }
        return $result;
    }

    /**
     * Создает узел и добавляет его в конец родительского узла.
     * Если родитель не найден - выбрасывает исключение {@see \Exception}
     *
     * @param \Donmonkey777\Tree\INestedSet $node
     * @param int $parentPk
     * @return \Donmonkey777\Tree\INestedSet
     * @throws \PDOException
     * @throws \Exception
     */
    public function insertIntoParent(INestedSet $node, $parentPk)
    {
        // Дополнительные атрибуты для заполнения
        $attributes = $node->getAttributes();

        // Получаем правый ключ, уровень и идентификатор дерева родительского узла.
        $selectStatement = $this->pdo->prepare(sprintf(
        // Лочим строку для того, чтобы не позволить поменять ключи родительскому узлу
            "select %s, %s, %s from %s where %s = ? for update",
            $this->prepareName($this->tableName, $this->rightKeyColumnName),
            $this->prepareName($this->tableName, $this->levelColumnName),
            $this->prepareName($this->tableName, $this->treeKeyColumnName),
            $this->prepareName($this->tableName),
            $this->prepareName($this->tableName, $this->primaryKeyColumnName)
        ));

        // Запрос для обновления левых и правых ключей дерева
        $updateStatement = $this->pdo->prepare(sprintf(
            'update %1$s set %2$s = %2$s + 2, %3$s = case when %3$s > :right_key then %3$s + 2 else %3$s end where %2$s > :right_key and %4$s = :tree_key',
            $this->prepareName($this->tableName),
            $this->prepareName($this->tableName, $this->rightKeyColumnName),
            $this->prepareName($this->tableName, $this->leftKeyColumnName),
            $this->prepareName($this->tableName, $this->treeKeyColumnName)
        ));

        // Запрос для вставки нового узла
        $insertStatement = $this->pdo->prepare(sprintf(
            "insert into %s (%s, %s, %s, %s, %s) values (?, ?, ?, ?, %s)",
            $this->prepareName($this->tableName),
            $this->prepareName($this->leftKeyColumnName),
            $this->prepareName($this->rightKeyColumnName),
            $this->prepareName($this->levelColumnName),
            $this->prepareName($this->treeKeyColumnName),
            implode(", ", array_map([$this, "prepareName"], array_keys($attributes))),
            implode(", ", array_fill(0, count($attributes), "?"))
        ));

        $this->pdo->beginTransaction();
        try {
            // Получаем необходимые поля родительского узла
            $selectStatement->execute([$parentPk]);
            if (($data = $selectStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $parentTreeKey = $data[$this->treeKeyColumnName];
                $parentRightKey = $data[$this->rightKeyColumnName];
                $parentLevel = $data[$this->levelColumnName];
            } else {
                throw new Exception("Родительский узел не найден");
            }

            // Подготавливаем дерево для вставки нового узла
            $updateStatement->execute([":right_key" => $parentRightKey - 1, ":tree_key" => $parentTreeKey]);

            // Добавляем новый узел
            $insertStatement->execute(array_merge([$parentRightKey, $parentRightKey + 1, $parentLevel + 1, $parentTreeKey], array_values($attributes)));

            // Получаем ID добавленного узла
            $pk = $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $node = clone $node;
        $node->setPrimaryKey($pk);
        $node->setParentPrimaryKey($parentPk);
        $node->setTreeKey($parentTreeKey);
        return $node;
    }

    /**
     * Создает узел и добавляет его в конец дерева.
     * Если дерево не найдено - выбрасывает исключение {@see \Exception}
     *
     * @param \Donmonkey777\Tree\INestedSet $node
     * @param int $treeKey
     * @return \Donmonkey777\Tree\INestedSet
     * @throws \PDOException
     * @throws \Exception
     */
    public function insertIntoTree(INestedSet $node, $treeKey)
    {
        // Дополнительные атрибуты для заполнения
        $attributes = $node->getAttributes();

        // Получаем максимальный правый ключ дерева
        $selectStatement = $this->pdo->prepare(strtr(
            // Ищем среди корней дерева максимальное значение правого ключа
            // Лочим строку для того, чтобы не позволить поменять корневым узлам ключи, при этом остальные узлы
            // остаются доступными для перемещения по дереву начиная со второго уровня.
            "select max(:right_key) as `max_right_key` from :table where :tree_key = ? and :level = 1 for update", [
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":table" => $this->prepareName($this->tableName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
        ]));

        // Запрос для вставки нового узла
        $insertStatement = $this->pdo->prepare(strtr(
            "insert into :table (:left_key, :right_key, :level, :tree_key, :attributes) values (?, ?, ?, ?, :attributes_placeholder)", [
            ":table" => $this->prepareName($this->tableName),
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
            ":attributes" => implode(", ", array_map([$this, "prepareName"], array_keys($attributes))),
            ":attributes_placeholder" => implode(", ", array_fill(0, count($attributes), "?")),
        ]));

        $this->pdo->beginTransaction();
        try {
            // Получаем необходимые поля родительского узла
            $selectStatement->execute([$treeKey]);
            if (($data = $selectStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $rightKey = $data["max_right_key"] + 1;
                $level = 1;
            } else {
                throw new Exception("Дерево не найдено");
            }

            // Добавляем новый узел
            $insertStatement->execute(array_merge([$rightKey, $rightKey + 1, $level, $treeKey], array_values($attributes)));

            // Получаем ID добавленного узла
            $pk = $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $node = clone $node;
        $node->setPrimaryKey($pk);
        $node->setTreeKey($treeKey);
        return $node;
    }

    /**
     * Обновляет атрибуты узла.
     * Если узел не найден - НЕ выбрасывает исключение.
     *
     * @param \Donmonkey777\Tree\INestedSet $node
     * @throws \PDOException
     */
    public function updateAttributes(INestedSet $node)
    {
        // Атрибуты для обновления
        $attributes = $node->getAttributes();

        $that = $this;
        $attributesKeys = array_keys($attributes);
        $params = [":param_primary_key" => $node->getPrimaryKey()];

        // Запрос на обновление узла
        $updateStatement = $this->pdo->prepare(strtr(
            "update :table set :attributes where :primary_key = :param_primary_key", [
            ":table" => $this->prepareName($this->tableName),
            ":primary_key" => $this->prepareName($this->tableName, $this->primaryKeyColumnName),
            ":attributes" => implode(", ", array_map(function ($value) use ($that, &$params, $attributes) {
                $param = ":param_" . $value;
                $params[$param] = $attributes[$value];
                return $that->prepareName($value) . " = " . $param;
            }, $attributesKeys)),
        ]));

        $updateStatement->execute($params);
    }

    /**
     * Удаляет узел.
     * Если узел не найден - исключение НЕ выбрасывается.
     *
     * @param int $pk
     * @return void
     * @throws \PDOException
     */
    public function delete($pk)
    {
        // Получаем ключ дерева, а так же левый и правый ключ узла
        $selectStatement = $this->pdo->prepare(sprintf(
            // Лочим строку для того, чтобы не позволить поменять ключи
            "select %s, %s, %s from %s where %s = ? for update",
            $this->prepareName($this->tableName, $this->leftKeyColumnName),
            $this->prepareName($this->tableName, $this->rightKeyColumnName),
            $this->prepareName($this->tableName, $this->treeKeyColumnName),
            $this->prepareName($this->tableName),
            $this->prepareName($this->tableName, $this->primaryKeyColumnName)
        ));

        // Запрос для удаления узла и его детей
        $deleteStatement = $this->pdo->prepare(sprintf(
            "delete from %s where %s >= ? and %s <= ? and %s = ?",
            $this->prepareName($this->tableName),
            $this->prepareName($this->tableName, $this->leftKeyColumnName),
            $this->prepareName($this->tableName, $this->rightKeyColumnName),
            $this->prepareName($this->tableName, $this->treeKeyColumnName)
        ));

        // Запрос для обновления левых и правых ключей дерева
        $updateStatement = $this->pdo->prepare(sprintf(
            'update %1$s set %2$s = %2$s - :val, %3$s = case when %3$s > :right_key then %3$s - :val else %3$s end where %2$s > :right_key and %4$s = :tree_key',
            $this->prepareName($this->tableName),
            $this->prepareName($this->tableName, $this->rightKeyColumnName),
            $this->prepareName($this->tableName, $this->leftKeyColumnName),
            $this->prepareName($this->tableName, $this->treeKeyColumnName)
        ));

        $this->pdo->beginTransaction();
        try {
            // Получаем необходимые поля узла
            $selectStatement->execute([$pk]);

            // Если узел не найден - просто выходим
            if (($data = $selectStatement->fetch(PDO::FETCH_ASSOC)) === false) {
                return;
            }

            $leftKey = $data[$this->leftKeyColumnName];
            $rightKey = $data[$this->rightKeyColumnName];
            $treeKey = $data[$this->treeKeyColumnName];

            // Удаляем узел
            $deleteStatement->execute([$leftKey, $rightKey, $treeKey]);

            // Подготавливаем дерево для вставки нового узла
            $updateStatement->execute([":right_key" => $rightKey - 1, ":tree_key" => $treeKey, ":val" => $rightKey - $leftKey + 1]);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function moveIntoParent($pk, $parentPk)
    {
        if ($pk == $parentPk) {
            throw new Exception("Неподдерживаемая операция");
        }

        // Получаем левый ключ, правый ключ, уровень и идентификатор дерева родительского узла.
        $selectParentStatement = $this->pdo->prepare(strtr(
            "select :left_key, :right_key, :level, :tree_key from :table where :primary_key = ? for update", [
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
            ":table" => $this->prepareName($this->tableName),
            ":primary_key" => $this->prepareName($this->tableName, $this->primaryKeyColumnName),
        ]));

        // Получаем левый ключ, правый ключ, уровень и идентфикатор дерева перемещаемого узла
        $selectStatement = $this->pdo->prepare(strtr(
            "select :left_key, :right_key, :level, :tree_key from :table where :primary_key = ? for update", [
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
            ":table" => $this->prepareName($this->tableName),
            ":primary_key" => $this->prepareName($this->tableName, $this->primaryKeyColumnName),
        ]));

        $this->pdo->beginTransaction();
        try {
            // Получаем родительский узел
            $selectParentStatement->execute([$parentPk]);

            if (($dataParent = $selectParentStatement->fetch(PDO::FETCH_ASSOC)) === false) {
                throw new Exception("Родительский узел не найден");
            }

            // Получаем перемещаемый узел
            $selectStatement->execute([$pk]);

            if (($data = $selectStatement->fetch(PDO::FETCH_ASSOC)) === false) {
                throw new Exception("Узел не найден");
            }

            if ($dataParent[$this->treeKeyColumnName] !== $data[$this->treeKeyColumnName]) {
                throw new Exception("Узлы принадлежат разным дервьям");
            }

            if ($dataParent[$this->leftKeyColumnName] > $data[$this->leftKeyColumnName] && $dataParent[$this->rightKeyColumnName] < $data[$this->rightKeyColumnName]) {
                throw new Exception("Узел не может быть перемещен в свой дочерний узел");
            }

            // Если узел не нуждается в перемещении
            if ($dataParent[$this->rightKeyColumnName] == ($data[$this->rightKeyColumnName] + 1)) {
                $this->pdo->commit();
                return;
            }

            if ($dataParent[$this->rightKeyColumnName] < $data[$this->rightKeyColumnName]) {
                $this->moveDownIntoParent($data, $dataParent);
            } else {
                $this->moveUpIntoParent($data, $dataParent);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    protected function moveDownIntoParent($data, $dataParent)
    {
        /** Можно переместить узел в четыре запроса или воспользоваться {@link http://www.getinfo.ru/article610.html оптимизированным вариантом} */
        $updateStatement = $this->pdo->prepare(strtr(
            <<< 'UPDATE_STATEMENT'
update
	:table
set
	:right_key =
	    case when 
	        :left_key >= :param_left_key
	    then
	        :right_key + :param_skew_edit
	    else
	        case when
	            :right_key < :param_left_key
	        then
	            :right_key + :param_skew_tree
	        else
	            :right_key
	        end
	    end,
	:level = 
        case when
            :left_key >= :param_left_key
        then
            :level + :param_skew_level
        else
            :level
        end,
	:left_key =
	    case when 
	        :left_key >= :param_left_key
	    then
	        :left_key + :param_skew_edit
	    else
	        case when
	            :left_key >= :param_right_key_near
	        then
	            :left_key + :param_skew_tree
	        else
	            :left_key
	        end
	    end
where
	:right_key >= :param_right_key_near and
	:left_key < :param_right_key and
    :tree_key = :param_tree_key
UPDATE_STATEMENT
            , [
            ":table" => $this->prepareName($this->tableName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
        ]));

        $updateStatement->execute([
            ":param_left_key" => $data[$this->leftKeyColumnName],
            ":param_right_key" => $data[$this->rightKeyColumnName],
            ":param_right_key_near" => $dataParent[$this->rightKeyColumnName],
            ":param_skew_edit" => $dataParent[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName],
            ":param_skew_tree" => $data[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] + 1,
            ":param_skew_level" => $dataParent[$this->levelColumnName] - $data[$this->levelColumnName] + 1,
            ":param_tree_key" => $dataParent[$this->treeKeyColumnName],
        ]);
    }

    protected function moveUpIntoParent($data, $dataParent)
    {
        /** Можно переместить узел в четыре запроса или воспользоваться {@link http://www.getinfo.ru/article610.html оптимизированным вариантом} */
        $updateStatement = $this->pdo->prepare(strtr(
            <<< 'UPDATE_STATEMENT'
update
    :table
set
    :left_key = 
        case when
            :right_key <= :param_right_key
        then
            :left_key + :param_skew_edit
        else
            case when
                :left_key > :param_right_key
            then
                :left_key - :param_skew_tree
            else
                :left_key
            end
        end,
    :level =
        case when
            :right_key <= :param_right_key
        then
            :level + :param_skew_level
        else
            :level
        end,
    :right_key =
        case when
            :right_key <= :param_right_key
        then
            :right_key + :param_skew_edit
        else
            case when
                :right_key < :param_right_key_near
            then
                :right_key - :param_skew_tree
            else
                :right_key
            end
        end
where
    :right_key > :param_left_key and
    :left_key < :param_right_key_near and
    :tree_key = :param_tree_key
UPDATE_STATEMENT
            , [
            ":table" => $this->prepareName($this->tableName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
        ]));

        $updateStatement->execute([
            ":param_left_key" => $data[$this->leftKeyColumnName],
            ":param_right_key" => $data[$this->rightKeyColumnName],
            ":param_right_key_near" => $dataParent[$this->rightKeyColumnName],
            ":param_skew_edit" => $dataParent[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] - ($data[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] + 1),
            ":param_skew_tree" => $data[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] + 1,
            ":param_skew_level" => $dataParent[$this->levelColumnName] - $data[$this->levelColumnName] + 1,
            ":param_tree_key" => $dataParent[$this->treeKeyColumnName],
        ]);
    }

    public function moveToNeighbor($pk, $neighborPk, $place = NestedSetsMapper::BEFORE)
    {
        // Получаем левый ключ, правый ключ, уровень и идентификатор дерева соседнего узла.
        $selectNeighborStatement = $this->pdo->prepare(strtr(
            "select :left_key, :right_key, :level, :tree_key from :table where :primary_key = ? for update", [
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
            ":table" => $this->prepareName($this->tableName),
            ":primary_key" => $this->prepareName($this->tableName, $this->primaryKeyColumnName),
        ]));

        // Получаем левый ключ, правый ключ, уровень и идентфикатор дерева перемещаемого узла
        $selectStatement = $this->pdo->prepare(strtr(
            "select :left_key, :right_key, :level, :tree_key from :table where :primary_key = ? for update", [
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
            ":table" => $this->prepareName($this->tableName),
            ":primary_key" => $this->prepareName($this->tableName, $this->primaryKeyColumnName),
        ]));

        $this->pdo->beginTransaction();
        try {
            // Получаем соседний узел
            $selectNeighborStatement->execute([$neighborPk]);

            if (($dataNeighbor = $selectNeighborStatement->fetch(PDO::FETCH_ASSOC)) === false) {
                throw new Exception("Соседний узел не найден");
            }

            // Получаем перемещаемый узел
            $selectStatement->execute([$pk]);

            if (($data = $selectStatement->fetch(PDO::FETCH_ASSOC)) === false) {
                throw new Exception("Узел не найден");
            }

            if ($dataNeighbor[$this->treeKeyColumnName] !== $data[$this->treeKeyColumnName]) {
                throw new Exception("Узлы принадлежат разным дервьям");
            }

            if ($dataNeighbor[$this->leftKeyColumnName] > $data[$this->leftKeyColumnName] && $dataNeighbor[$this->rightKeyColumnName] < $data[$this->rightKeyColumnName]) {
                throw new Exception("Узел не может быть перемещен в свой дочерний узел");
            }

            // Если узел не нуждается в перемещении
            if ($place === NestedSetsMapper::BEFORE) {
                if ($dataNeighbor[$this->leftKeyColumnName] == ($data[$this->rightKeyColumnName] + 1)) {
                    $this->pdo->commit();
                    return;
                }
            } else {
                if ($dataNeighbor[$this->rightKeyColumnName] == ($data[$this->leftKeyColumnName] - 1)) {
                    $this->pdo->commit();
                    return;
                }
            }

            $rightKeyNear = ($place === NestedSetsMapper::BEFORE) ? $dataNeighbor[$this->leftKeyColumnName] : $dataNeighbor[$this->rightKeyColumnName] + 1;
            if ($rightKeyNear < $data[$this->rightKeyColumnName]) {
                $this->moveDownToNeighbor($data, $dataNeighbor, $place);
            } else {
                $this->moveUpToNeighbor($data, $dataNeighbor, $place);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    protected function moveDownToNeighbor($data, $dataNeighbor, $place)
    {
        /** Можно переместить узел в четыре запроса или воспользоваться {@link http://www.getinfo.ru/article610.html оптимизированным вариантом} */
        $updateStatement = $this->pdo->prepare(strtr(
            <<< 'UPDATE_STATEMENT'
update
	:table
set
	:right_key =
	    case when 
	        :left_key >= :param_left_key
	    then
	        :right_key + :param_skew_edit
	    else
	        case when
	            :right_key < :param_left_key
	        then
	            :right_key + :param_skew_tree
	        else
	            :right_key
	        end
	    end,
	:level = 
        case when
            :left_key >= :param_left_key
        then
            :level + :param_skew_level
        else
            :level
        end,
	:left_key =
	    case when 
	        :left_key >= :param_left_key
	    then
	        :left_key + :param_skew_edit
	    else
	        case when
	            :left_key >= :param_right_key_near
	        then
	            :left_key + :param_skew_tree
	        else
	            :left_key
	        end
	    end
where
	:right_key >= :param_right_key_near and
	:left_key < :param_right_key and
    :tree_key = :param_tree_key
UPDATE_STATEMENT
            , [
            ":table" => $this->prepareName($this->tableName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
        ]));

        $rightKeyNear = $place == NestedSetsMapper::BEFORE ? $dataNeighbor[$this->leftKeyColumnName] : $dataNeighbor[$this->rightKeyColumnName] + 1;

        $updateStatement->execute([
            ":param_left_key" => $data[$this->leftKeyColumnName],
            ":param_right_key" => $data[$this->rightKeyColumnName],
            ":param_right_key_near" => $rightKeyNear,
            ":param_skew_edit" => $rightKeyNear - $data[$this->leftKeyColumnName],
            ":param_skew_tree" => $data[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] + 1,
            ":param_skew_level" => $dataNeighbor[$this->levelColumnName] - $data[$this->levelColumnName],
            ":param_tree_key" => $dataNeighbor[$this->treeKeyColumnName],
        ]);
    }

    protected function moveUpToNeighbor($data, $dataNeighbor, $place)
    {
        /** Можно переместить узел в четыре запроса или воспользоваться {@link http://www.getinfo.ru/article610.html оптимизированным вариантом} */
        $updateStatement = $this->pdo->prepare(strtr(
            <<< 'UPDATE_STATEMENT'
update
    :table
set
    :left_key = 
        case when
            :right_key <= :param_right_key
        then
            :left_key + :param_skew_edit
        else
            case when
                :left_key > :param_right_key
            then
                :left_key - :param_skew_tree
            else
                :left_key
            end
        end,
    :level =
        case when
            :right_key <= :param_right_key
        then
            :level + :param_skew_level
        else
            :level
        end,
    :right_key =
        case when
            :right_key <= :param_right_key
        then
            :right_key + :param_skew_edit
        else
            case when
                :right_key < :param_right_key_near
            then
                :right_key - :param_skew_tree
            else
                :right_key
            end
        end
where
    :right_key > :param_left_key and
    :left_key < :param_right_key_near and
    :tree_key = :param_tree_key
UPDATE_STATEMENT
            , [
            ":table" => $this->prepareName($this->tableName),
            ":right_key" => $this->prepareName($this->tableName, $this->rightKeyColumnName),
            ":left_key" => $this->prepareName($this->tableName, $this->leftKeyColumnName),
            ":level" => $this->prepareName($this->tableName, $this->levelColumnName),
            ":tree_key" => $this->prepareName($this->tableName, $this->treeKeyColumnName),
        ]));

        $rightKeyNear = $place == NestedSetsMapper::BEFORE ? $dataNeighbor[$this->leftKeyColumnName] : $dataNeighbor[$this->rightKeyColumnName] + 1;

        $updateStatement->execute([
            ":param_left_key" => $data[$this->leftKeyColumnName],
            ":param_right_key" => $data[$this->rightKeyColumnName],
            ":param_right_key_near" => $rightKeyNear,
            ":param_skew_edit" => $rightKeyNear - $data[$this->leftKeyColumnName] - ($data[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] + 1),
            ":param_skew_tree" => $data[$this->rightKeyColumnName] - $data[$this->leftKeyColumnName] + 1,
            ":param_skew_level" => $dataNeighbor[$this->levelColumnName] - $data[$this->levelColumnName],
            ":param_tree_key" => $dataNeighbor[$this->treeKeyColumnName],
        ]);
    }

    protected function getColumns($attributes)
    {
        return array_merge($attributes, [
            $this->primaryKeyColumnName,
            $this->treeKeyColumnName,
            $this->leftKeyColumnName,
            $this->rightKeyColumnName,
            $this->levelColumnName,
        ]);
    }

    protected function prepareName(... $parts)
    {
        return implode(".", array_map(function ($part) {
            return "`{$part}`";
        }, $parts));
    }
}