<?php

require_once __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;

$rabbitMqServer = 'http://rabbitmq-server.local';
$client = new Client(['base_uri' => $rabbitMqServer ]);

$res = $client->get($rabbitMqServer);

var_dump($res->getHeaders());
echo '<br/> status code: ' . $res->getStatusCode();
echo $res->getBody();