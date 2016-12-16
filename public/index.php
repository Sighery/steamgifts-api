<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require '../../libraries/simple_html_dom.php';


$app = new \Slim\App;

require_once("../app/steamgifts/IUsers.php");
require_once("../app/steamgifts/Interactions.php");

$app->run();
