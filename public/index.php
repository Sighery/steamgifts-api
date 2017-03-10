<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require '../../libraries/simple_html_dom.php';


$app = new \Slim\App;

// IGiveaways endpoint methods
include_once(__DIR__ . '/../app/SteamGifts/IGiveaways/GetGivInfo.php');
include_once(__DIR__ . '/../app/SteamGifts/IGiveaways/GetGivWinners.php');

// IUsers endpoint methods
include_once(__DIR__ . '/../app/SteamGifts/IUsers/GetUserInfo.php');

// Interactions endpoint methods
include_once(__DIR__ . '/../app/SteamGifts/Interactions/GetGameTitle.php');
include_once(__DIR__ . '/../app/SteamGifts/Interactions/IsFree.php');
include_once(__DIR__ . '/../app/SteamGifts/Interactions/GetMessagesCount.php');


$app->run();
