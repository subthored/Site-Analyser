<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

session_start();

$app->get('/', function ($request, $response){
    return $response->write("<h1>Hello</h1>");
});
