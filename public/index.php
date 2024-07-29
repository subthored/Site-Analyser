<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Hexlet\Code\CreatorTables;
use Hexlet\Code\PgsqlActions;
use PostgreSQL\Connection;
use Slim\Factory\AppFactory;
use Valitron\Validator;
use Carbon\Carbon;

try {
    Connection::get()->connect();
    echo 'A connection to PostgreSQL database server has been establish successfully.';
} catch (\PDOException $e) {
    echo $e->getMessage();
}

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new Slim\Flash\Messages();
});

$container->set('connection', function () {
    $pdo = Connection::get()->connect();
    return $pdo;
});

$app = AppFactory::createFromContainer($container);
$app->add(\Slim\Middleware\MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();
$app->get('/createTables', function ($request, $response) {
    $tableCreator = new CreatorTables($this->get('connection'));
    $tables = $tableCreator->createTables();
    $tablesCheck = $tableCreator->createTablesChecks();
    return $response;
});

$app->get('/', function ($request, $response) {
    $params = [];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('mainPage');

$app->get('/urls', function ($request, $response) use ($router) {
    $dataBase = new PgsqlActions($this->get('connection'));
    $urlsFromDb = $dataBase->query(
        'SELECT created_at, id, name
            FROM urls
            ORDER BY id DESC '
    );
    $params = ['data' => $urlsFromDb];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, $args) use ($router) {
   $id =  $args['id'];
   $messages = $this->get('flash')->getMessages;

   $database = new PgsqlActions($this->get('connection'));
   $urlFromDb = $database->query('SELECT * FROM urls WHERE id = :id', $args);
   $checkedUrlFromDb = $database->query('SELECT * FROM url_checks 
                                                WHERE url_id = :id ORDER BY id DESC', $args);
   $params = ['id' => $urlFromDb[0]['id'],
                'name' => $urlFromDb[0]['name'],
                'created_at' => $urlFromDb[0]['created_at'],
                'flash' => $messages,
                'urls' => $checkedUrlFromDb
       ];
   return $this->get('renderer')->render($response, 'urlsId.phtml', $params);
})->setName('urlsId');

$app->post('/urls', function ($request, $response) use ($router) {
    $url = $request->getParsedBodyParam('url');
    $database = new PgsqlActions($this->get('connection'));
    $error = [];

    try {
        $tableCreator = new CreatorTables($this->get('connection'));
        $tables = $tableCreator->createTables();
        $tablesCheck = $tableCreator->createTablesChecks();
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $validator = new Validator(array('name' => $url['name'], 'count' => strlen($url['name'])));
    $validator->rule('required', 'name')
        ->rule('lengthMax', 'count.', 255)
        ->rule('url', 'name');

    if ($validator->validate()) {
        $parsedUrl = parse_url($url['name']);
        $url['name'] = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        $nameInDb = $database->query('SELECT id FROM urls WHERE name = :name', $url);

        if (count($nameInDb) !== 0) {
            $redirectUrl = $router->urlFor('urlsId', ['id' => $nameInDb[0]['id']]);
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            return $response->withRedirect($redirectUrl);
        }
        $url['time'] = Carbon::now();
        $insertUrlInTable = $database->query('INSERT INTO urls (name, created_at) VALUES (:name, :time)', $url);
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');

        $redirectUrl = $router->urlFor('urlsId', ['id' => $nameInDb[0]['id']]);
        return $response->withRedirect($redirectUrl);
    } else {
        if (isset($url) && strlen($url['name']) < 1) {
            $error['name'] = 'URL не может быть пустым';
        } elseif (isset($url)) {
            $error['name'] = 'Некорректный URL';
        }
    }
    $params = ['errors' => $error];
    return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
});

$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($router) {
    $checkedUrl['id'] = $args['url_id'];
    $checkedUrl['time'] = Carbon::now();
    $database = new PgsqlActions($this->get('connection'));
    $error = [];

    $insertInTable = $database->query('INSERT INTO url_checks (url_id, created_at)
                                            VALUES (:id, :time)', $checkedUrl);

    $url = $router->urlFor('urlsId', ['id' => $checkedUrl['id']]);
    return $response->withRedirect($url, 302);
});

$app->run();