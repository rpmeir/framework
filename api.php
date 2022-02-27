<?php

require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}

// When the API endpoint is not the main file
$endpoint = pathinfo(__FILE__, PATHINFO_BASENAME) . '/';

$uri = rawurldecode($uri);
$uri = str_replace($endpoint, '', $uri);

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addGroup('/v1', function() use ($r) {

        $r->get('/', function() {
            echo json_encode(['msg' => 'teste de mensagem']);
        });

        $r->addRoute('GET', '/users', function(){
            echo json_encode(["msg" => "getUsers function action goes here..."]);
        });

        $r->addRoute('GET', '/test', function(){
            echo "testFunction action goes here...";
        });
    });
    $r->addGroup('/v2', function() use ($r) {
        $r->get('/books/{id}', function ($args) {
            // Show book identified by $args['id']
            echo "Book #" . $args['id'];
        });

        // {id} must be a number (\d+)
        $r->addRoute('GET', '/user/{id:\d+}', function ($args) {
            echo "User #" . $args['id'];
        });
        // The /{title} suffix is optional2
        $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', function ($args) {
            echo "User #" . $args['id'] . PHP_EOL;
            echo "Title: " . $args['title'];
        });
    });
});

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        die('NOT_FOUND');
        // ... 404 Not Found
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        die('Not Allowed');
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        print $handler($vars);
        break;
}
