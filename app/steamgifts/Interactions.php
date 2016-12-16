<?php
require_once("functionality.php");

$app->get('/SteamGifts/Interactions/GetMessagesCount/', function($request, $response) {
	$key = $request->getQueryParam("sgsid");
	if (isset($key)) {
		$page_req = get_sg_page("https://www.steamgifts.com/about/brand-assets", $key);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 400,
			"message" => "Required phpsessid argument missing or invalid")), 400, JSON_PRETTY_PRINT);
	}

	$html = str_get_html($page_req);

	$possible_count = $html->find("a[href='/messages']")[0]->lastChild();
	if ($possible_count->class == "nav__notification") {
		$possible_count = intval($possible_count->innertext);
	} else {
		$possible_count = 0;
	}

	$data = array("count" => $possible_count);
	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')->withJson($data);
});
?>
