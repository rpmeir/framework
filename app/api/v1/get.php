<?php

require_once __DIR__ . '/../route/Route.php';

Route::get('v1/teste', function () {
    echo json_encode(['teste' => 'teste']);
});
