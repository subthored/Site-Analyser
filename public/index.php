<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Hexlet\Code\PgsqlActions;
use PostgreSQL\Connection;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Valitron\Validator;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use DiDom\Document;
use Hexlet\Code\CreatorTables;

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

$app->get('/', function ($request, $response) {
    $tableCreator = new CreatorTables($this->get('connection'));
    $tables = $tableCreator->createTables();
    $tablesCheck = $tableCreator->createTablesChecks();
    $params = [];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('mainPage');

$app->get('/urls', function ($request, $response) {
    $dataBase = new PgsqlActions($this->get('connection'));
    $urlsFromDb = $dataBase->query(
        'SELECT MAX(url_checks.created_at) AS created_at, url_checks.status_code, urls.id, urls.name
            FROM urls
            LEFT OUTER JOIN url_checks ON url_checks.url_id = urls.id
            GROUP BY url_checks.url_id, urls.id, url_checks.status_code
            ORDER BY urls.id DESC'
    );
    $params = ['data' => $urlsFromDb];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, $args) {
    $messages = $this->get('flash')->getMessages();

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
        $id = $database->query('SELECT MAX(id) FROM urls');

        $redirectUrl = $router->urlFor('urlsId', ['id' => $id[0]['max']]);
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
    $checkedUrl['url_id'] = $args['url_id'];
    $database = new PgsqlActions($this->get('connection'));
    $urlForTest = $database->query('SELECT name FROM urls WHERE id = :url_id', $checkedUrl);
    $error = [];

    try {
        $client = new Client();
        $testResponse = $client->request('GET', $urlForTest[0]['name']);
        $checkedUrl['status'] = $testResponse->getStatusCode();
    } catch (ConnectException $e) {
        $messages = $this->get('flash')->
                    addMessage('failure', 'Произошла ошибка при проверке, не удалось подключиться');
        $url = $router->urlFor('urlsId', ['id' => $checkedUrl['url_id']]);
        return $response->withRedirect($url);
    } catch (ClientException $e) {
        if ($e->getResponse()->getStatusCode() != 200) {
            $checkedUrl['status'] = $e->getResponse()->getStatusCode();
            $checkedUrl['title'] = 'Доступ ограничен проблема с IP';
            $checkedUrl['h1'] = 'Доступ ограничен проблема с IP';
            $checkedUrl['meta'] = 'Доступ ограничен проблема с IP';
            $checkedUrl['time'] = Carbon::now();
            $database->query('INSERT INTO url_checks (url_id, status_code, title, h1, description, created_at)
                                    VALUES (:url_id, :status, :title, :h1, :meta, :time)', $checkedUrl);
            $this->get('flash')->addMessage('warning', 'Проверка была выполнена успешно, но сервер ответил с ошибкой');
            $url = $router->urlFor('urlsId', ['id' => $checkedUrl['url_id']]);
            return $response->withRedirect($url);
        }
    }

    $client = new Client();
    $testResponse = $client->request('GET', $urlForTest[0]['name']);
    $parsedHtml = new Document($testResponse->getBody()->getContents(), false);
    $title = ($parsedHtml->first('title'));
    $h1 = ($parsedHtml->first('h1'));
    $meta = ($parsedHtml->first('meta[name="description"]'));
    $checkedUrl['time'] = Carbon::now();

    if ($title?->text()) {
        $title = mb_substr($title->text(), 0, 255);
        $checkedUrl['title'] = $title;
    } else {
        $checkedUrl['title'] = '';
    }

    if ($h1?->text()) {
        $h1 = mb_substr($h1->text(), 0, 255);
        $checkedUrl['h1'] = $h1;
    } else {
        $checkedUrl['h1'] = '';
    }

    if ($meta?->getAttribute('content')) {
        $meta = mb_substr($meta->getAttribute('content'), 0, 255);
        $checkedUrl['meta'] = $meta;
    } else {
        $checkedUrl['meta'] = '';
    }

    if (isset($checkedUrl['status'])) {
        try {
            $insertInTable = $database->query('INSERT INTO url_checks (url_id, status_code, title, h1, 
                                            description, created_at)
                                            VALUES (:url_id, :status, :title, :h1, :meta, :time)', $checkedUrl);
        } catch (\PDOException $e) {
            echo $e->getMessage();
        }
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    }

    $url = $router->urlFor('urlsId', ['id' => $checkedUrl['url_id']]);
    return $response->withRedirect($url, 302);
})->setName('urlsChecks');

$app->run();
