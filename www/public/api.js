$(function () {
    var $tree = $("#tree1");
    var $title = $("#title");
    var $log = $("#log");

    $tree.fancytree({
        extensions: ["dnd", "edit"],
        dnd: {
            draggable: {
                scroll: false,
            },
            autoExpandMS: false,
            preventRecursiveMoves: true,
            preventVoidMoves: true,

            dragStart: function (sourceNode, data) {
                return true;
            },
            dragEnter: function (node, data) {
                return true;
            },
            dragDrop: function (n, d) {
                $.ajax({
                    type: "POST",
                    url: "/api.php",
                    data: JSON.stringify({
                        place: d.hitMode,
                        key: d.otherNode.key,
                        targetKey: n.key,
                        action: "move"
                    }),
                    contentType: "application/json",
                    dataType: "json",
                    cache: false
                }).then(function (result) {
                    $log.append($("<pre>").html(result.message));

                    if (result.error == 0) {
                        d.otherNode.moveTo(n, d.hitMode);
                    }
                }).catch(function () {
                    $log.append($("<pre>").html("Неизвестная ошибка"));
                });
            }
        },
        edit: {
            adjustWidthOfs: 4,
            inputCss: {minWidth: "3em"},
            triggerStart: ["f2", "dblclick", "shift+click", "mac+enter"],
            beforeEdit: $.noop,
            edit: $.noop,
            beforeClose: $.noop,
            close: $.noop,
            save: function (event, data) {
                var node = data.node;

                $.ajax({
                    type: "POST",
                    url: "/api.php",
                    contentType: "application/json",
                    dataType: "json",
                    cache: false,
                    data: JSON.stringify({key: node.key, title: data.input.val(), action: "edit"})
                }).then(function (result) {
                    $log.append($("<pre>").html(result.message));

                    if (result.error != 0) {
                        node.setTitle(data.orgTitle);
                    }
                }).catch(function () {
                    $log.append($("<pre>").html("Неизвестная ошибка"));
                    node.setTitle(data.orgTitle);
                });

                return true;
            }
        },
        source: $.ajax({
            type: "POST",
            url: "/api.php",
            data: JSON.stringify({action: "get"}),
            contentType: "application/json",
            dataType: "json",
            cache: false
        }).then(function (result) {
            $log.append($("<pre>").html(result.message));

            if (result.error == 0) {
                return result.nodes;
            }

            return [];
        }).catch(function () {
            $log.append($("<pre>").html("Неизвестная ошибка"));
            return [];
        }),
        lazyLoad: function (event, data) {
            var node = data.node;
            // Issue an ajax request to load child nodes
            data.result = $.ajax({
                type: "POST",
                url: "/api.php",
                data: JSON.stringify({parentKey: node.key, action: "get"}),
                contentType: "application/json",
                dataType: "json",
                cache: false
            }).then(function (result) {
                $log.append($("<pre>").html(result.message));

                if (result.error == 0) {
                    return result.nodes;
                }

                return [];
            }).catch(function () {
                $log.append($("<pre>").html("Неизвестная ошибка"));
                return [];
            });
        }
    });

    $("#create").click(function () {
        var node = $tree.fancytree("getTree").getActiveNode() || $tree.fancytree("getTree").getRootNode();
        var key = node.key;

        $.ajax({
            type: "POST",
            url: "/api.php",
            data: JSON.stringify({parentKey: key, action: "create", title: $title.val()}),
            contentType: "application/json",
            dataType: "json",
            cache: false
        }).then(function (result) {
            $log.append($("<pre>").html(result.message));

            if (result.error != 0) {
                return;
            }

            if (node.isExpanded()) {
                node.addChildren(result.node);
            } else {
                node.setExpanded(true);
            }
        }).catch(function () {
            $log.append($("<pre>").html("Неизвестная ошибка"));
        });
    });

    $("#delete").click(function () {
        var node = $tree.fancytree("getTree").getActiveNode();
        if (node) {
            $.ajax({
                type: "POST",
                url: "/api.php",
                data: JSON.stringify({key: node.key, action: "delete"}),
                contentType: "application/json",
                dataType: "json",
                cache: false
            }).then(function (result) {
                $log.append($("<pre>").html(result.message));

                if (result.error != 0) {
                    return;
                }

                node.remove();
            }).catch(function () {
                $log.append($("<pre>").html("Неизвестная ошибка"));
            });
        }
    });
});