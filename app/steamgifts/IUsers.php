<?php
$app->get('/SteamGifts/IUsers/GetUserInfo/', function($request, $response) {
	$params = $request->getQueryParams();
	if (isset($params['id'])) {
		$id = $params['id'];
		if (preg_match("/^[0-9]+$/", $id) != 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')->withJson(array(
			"errors" => array(
				"code" => 400,
				"message" => "The id contains non numeric characters")), 400, JSON_PRETTY_PRINT);
		}
		$page_req = get_page("https://www.steamgifts.com/go/user/" . $id);
	} elseif (isset($params['user'])) {
		$user = $params['user'];
		if (preg_match("/^[A-Za-z0-9]+$/", $user) != 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')->withJson(array(
			"errors" => array(
				"code" => 400,
				"message" => "The nick contains non alphanumeric characters")), 400, JSON_PRETTY_PRINT);
		}
		$page_req = get_page("https://www.steamgifts.com/user/" . $user);
	}

	if (isset($params['filters'])) {
		if (strpos($params['filters'], ',') != false) {
			$filters = explode(",", str_replace(" ", "", $params["filters"]));
		} else {
			$filters = array(str_replace(" ", "", $params["filters"]));
		}
	}

	$html = str_get_html($page_req);

	$data = array();

	preg_match("/(\d+)/", $html->find(".sidebar__shortcut-inner-wrap a[href*='steamcommunity.com']")[0]->href, $steam_id);
	if (isset($filters) == false || in_array('steamid64', $filters)) {
		$data['steamid64'] = intval($steam_id[0]);
	}

	if (isset($filters) == false || in_array('nickname', $filters)) {
		$data['nickname'] = $html->find(".featured__heading__medium")[0]->innertext;
	}

	foreach($html->find(".featured__table__row") as $elem) {
		switch($elem->children(0)->innertext) {
			case 'Role':
				if (isset($filters) == false || in_array('role', $filters)) {
					$data['role'] = strtolower($elem->children(1)->children(0)->innertext);
				}
				break;
			case 'Registered':
				if (isset($filters) == false || in_array('registered', $filters)) {
					$data['registered'] = intval($elem->children(1)->children(0)->getAttribute('data-timestamp'));
				}
				break;
			case 'Comments':
				if (isset($filters) == false || in_array('comments', $filters)) {
					$data['comments'] = intval(str_replace(",", "", $elem->children(1)->innertext));
				}
				break;
			case 'Giveaways Entered':
				if (isset($filters) == false || in_array('gibs_entered', $filters)) {
					$data['gibs_entered'] = intval(str_replace(",", "", $elem->children(1)->innertext));
				}
				break;
			case 'Gifts Won':
				if (isset($filters) == false || in_array('gifts_won', $filters)) {
					$data['gifts_won'] = intval(str_replace(",", "", $elem->children(1)->children(0)->innertext));
				}
				break;
			case 'Gifts Sent':
				if (isset($filters) == false || in_array('gifts_sent', $filters)) {
					$data['gifts_sent'] = intval(str_replace(",", "", $elem->children(1)->children(0)->children(0)->innertext));
				}
				break;
			case 'Contributor Level':
				if (isset($filters) == false || in_array('contributor_level', $filters)) {
					$data['contributor_level'] = floatval($elem->children(1)->children(0)->title);
				}
				break;
		}
	}


	return $response->withHeader('Access-Control-Allow-Origin', '*')->withJson($data, 200, JSON_PRETTY_PRINT);
});
?>
