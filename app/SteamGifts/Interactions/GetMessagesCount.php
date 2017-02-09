<?php
require_once(__DIR__ . '/../utils/utilities.php');

$app->get('/SteamGifts/Interactions/GetMessagesCount/', function($request, $response) {
	/** This endpoint and method will stay private and protected for now
	  * until Cg allows me (if) to ask users for their PHPSESSID cookies to get
	  * and serve their info. This is a check to allow it, if you are hosting
	  * your own version on your server just remove these lines.
	  */
	$private_data = parse_ini_file(__DIR__ . '/../utils/private.ini');
	if ($request->getQueryParam('allowed') === null || $request->getQueryParam('allowed') != $private_data['allow_phpsessid_key']) {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 1,
			"description" => "Cg most likely won't allow me to ask for your PHPSESSID cookie, so this method is restricted to just my personal use for now"
		)), 400);
	}

	// All from here would be the proper code to get this info using your own
	// PHPSESSID cookie
	$key = $request->getQueryParam("sgsid");
	if (isset($key) && preg_match("/^[A-Za-z0-9]+$/", $key) === 1) {
		$page_req = APIRequests::sg_generic_get_request("https://www.steamgifts.com/about/brand-assets", false, $key);

		if ($page_req->status_code !== 200) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 0,
				"description" => "The request to SG was unsuccessful"
			)), 500);
		}

	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 0,
			"description" => "Required phpsessid argument missing or invalid"
		)), 400);
	}

	$data = array(
		"count" => null
	);

	$html = str_get_html($page_req->body);


	$possible_count = $html->find("a[href='/messages']", 0)->lastChild();
	if ($possible_count->class == "nav__notification") {
		if ($possible_count->innertext == "99+") {
			$data['count'] = 100;
		} else {
			$data['count'] = intval($possible_count->innertext);
		}
	} else {
		$data['count'] = 0;
	}


	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});
?>
