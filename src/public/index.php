<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/../vendor/autoload.php';

$config['displayErrorDetails'] = true;

$config['db']['host']   = getenv('DB_HOST_NAME');
//$config['db']['db'] = 'mysql';
//$config['db']['user']   = getenv('DB_MYSQL_USER');
//$config['db']['pass']   = getenv('DB_MYSQL_PASS');
$config['db']['db'] = 'pgsql';
$config['db']['user'] = getenv('DB_POSTGRESQL_USER');
$config['db']['pass'] = getenv('DB_POSTGRESQL_PASS');
$config['db']['dbname'] = getenv('DB_NAME');

$app = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();

$container['view'] = new \Slim\Views\PhpRenderer(__DIR__ . "/../templates/");

$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO("{$db['db']}:host={$db['host']};dbname={$db['dbname']}",
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

$app->get('/tickets', function (Request $request, Response $response) {
    $this->logger->addInfo("Ticket list");
    $mapper = new TicketMapper($this->db);
    $tickets = $mapper->getTickets();

    $response = $this->view->render($response, "tickets.phtml", ["tickets" => $tickets, "router" => $this->router]);
    return $response;
});

$app->get('/ticket/new', function (Request $request, Response $response) {
    $component_mapper = new ComponentMapper($this->db);
    $components = $component_mapper->getComponents();
    $response = $this->view->render($response, "ticketadd.phtml", ["components" => $components]);
    return $response;
});

$app->post('/ticket/new', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $ticket_data = [];
    //$ticket_data['title'] = filter_var($data['title'], FILTER_SANITIZE_STRING);
    //$ticket_data['description'] = filter_var($data['description'], FILTER_SANITIZE_STRING);
    $ticket_data['title'] = filter_var($data['title']);
    $ticket_data['description'] = filter_var($data['description']);


    // work out the component
    $component_id = (int)$data['component'];
    $component_mapper = new ComponentMapper($this->db);
    $component = $component_mapper->getComponentById($component_id);
    $ticket_data['component'] = $component->getName();

    $ticket = new TicketEntity($ticket_data);
    $ticket_mapper = new TicketMapper($this->db);
    $ticket_mapper->save($ticket);

    $response = $response->withRedirect("/tickets");
    return $response;
});

$app->get('/ticket/search', function (Request $request, Response $response){
    $query = $request->getAttribute('q', $_GET)['q'];

    $this->logger->addInfo("ticket search by {$query}");
    $mapper = new TicketMapper($this->db);
    $tickets = $mapper->getTicketsByName($query);

    $response = $this->view->render($response, "tickets.phtml", ["tickets" => $tickets, "router" => $this->router, "query" => $query]);
    return $response;
});

$app->get('/ticket/{id}', function (Request $request, Response $response, $args) {
    $ticket_id = (int)$args['id'];
    $mapper = new TicketMapper($this->db);
    $ticket = $mapper->getTicketById($ticket_id);

    $response = $this->view->render($response, "ticketdetail.phtml", ["ticket" => $ticket]);
    return $response;
})->setName('ticket-detail');

$app->run();
