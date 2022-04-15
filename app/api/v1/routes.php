<?php

/** @var FastRoute\RouteCollector $r */

$r->get('/', function() {
    return ['data' => 'teste de mensagem'];
});

$r->addRoute('GET', '/users', function () {
    return ['Users' => ['user 01', 'user 02', 'user 03']];
});

// {id} must be a number (\d+)
$r->addRoute('GET', '/user/{id:\d+}', function ($args) {
    return ['User' => $args['id']];
});

$r->get('/books/{id}', function ($args) {
    // Show book identified by $args['id']
    return ['Book' => $args['id']];
});

// The /{title} suffix is optional2
$r->addRoute('GET', '/articles/{id:\d+}[/{title}]', function ($args) {
    return [
        'Id' => $args['id'],
        'Title' => $args['title']
    ];
});
