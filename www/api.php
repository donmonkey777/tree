<?php

require __DIR__ . "/../vendor/autoload.php";

// Получаем запрос
$data = json_decode(file_get_contents('php://input'), true);
if (!(is_array($data) && isset($data["action"]))) {
    header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    echo "Страница не найдена";
    exit();
}

// Работаем только с первым деревом `tree_id` = 0
$viewer = new \Donmonkey777\Tree\TreeViewer(new PDO("mysql:host=localhost;dbname=tree_prod", "root", ""), 0);

// "Роутинг"
switch ($data["action"]) {
    case "get":
        if ($viewer->get($data["parentKey"] ?? null)) {
            echo json_encode(["error" => 0, "message" => "Узлы успешно получены", "nodes" => $viewer->getResult()]);
            exit();
        }

        echo json_encode(["error" => 1, "message" => $viewer->getErrorMessage()]);
        exit();
    case "move":
        // Если заданы не все поля для перемещения узла
        if (!isset($data["place"]) || !isset($data["key"]) || !isset($data["targetKey"])) {
            break;
        }

        if ($viewer->move($data["key"], $data["targetKey"], $data["place"])) {
            echo json_encode(["error" => 0, "message" => "Узел успешно перемещен", "nodes" => $viewer->getResult()]);
            exit();
        }

        echo json_encode(["error" => 1, "message" => $viewer->getErrorMessage()]);
        exit();
    case "create":
        // Если заданы не все поля для создания узла
        if (!isset($data["title"])) {
            break;
        }

        if ($viewer->create($data["title"], $data["parentKey"] ?? 0)) {
            $createdNode = $viewer->getResult();
            echo json_encode(["error" => 0, "node" => [
                "key" => $createdNode->id,
                "title" => $createdNode->title,
                "folder" => true,
                "expanded" => false,
                "lazy" => true,
            ], "message" => "Узел успешно создан"]);
            exit();
        }

        echo json_encode(["error" => 1, "message" => $viewer->getErrorMessage()]);
        exit();
    case "delete":
        // Если заданы не все поля для удаления узла
        if (!isset($data["key"])) {
            break;
        }

        if ($viewer->delete($data["key"])) {
            echo json_encode(["error" => 0, "message" => "Узел успешно удален"]);
            exit();
        }

        echo json_encode(["error" => 1, "message" => $viewer->getErrorMessage()]);
        exit();
    case "edit":
        // Если заданы не все поля для изменения узла
        if (!isset($data["key"]) || !isset($data["title"])) {
            break;
        }

        if ($viewer->edit($data["key"], $data["title"])) {
            echo json_encode(["error" => 0, "message" => "Узел успешно изменен"]);
            exit();
        }

        echo json_encode(["error" => 1, "message" => $viewer->getErrorMessage()]);
        exit();
}

// Выводим "Страница не найдена"
header($_SERVER["SERVER_PROTOCOL"]." 400 Bad Request", true, 400);
echo "Неверные параметры запроса";
exit();