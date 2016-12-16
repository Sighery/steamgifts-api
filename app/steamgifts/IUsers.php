<?php
require_once("functionality.php");

$app->get('/SteamGifts/IUsers/GetUserInfo/', function($request, $response) {
	$params = $request->getQueryParams();
	if (isset($params['id'])) {
		$id = $params['id'];
		if (preg_match("/^[0-9]+$/", $id) != 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 400,
				"message" => "The id contains non numeric characters")), 400, JSON_PRETTY_PRINT);
		}
		$page_req = get_sg_page("https://www.steamgifts.com/go/user/" . $id);
	} elseif (isset($params['user'])) {
		$user = $params['user'];
		if (preg_match("/^[A-Za-z0-9]+$/", $user) != 1) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 400,
				"message" => "The nick contains non alphanumeric characters")), 400, JSON_PRETTY_PRINT);
		}
		$page_req = get_sg_page("https://www.steamgifts.com/user/" . $user);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 400,
			"message" => "Missing or invalid required parameters")), 400, JSON_PRETTY_PRINT);
	}

	// I turn the filters' value into an array to filter the output data. I use
	//strpos to check if there are commas on the string value, and if there are
	//split it with the comma as separator to get an array of values if any
	if (isset($params['filters'])) {
		if (strpos($params['filters'], ',') != false) {
			$filters = explode(",", $params["filters"]);
		} else {
			$filters = array($params["filters"]);
		}
	}

	// Parsing the html file
	$html = str_get_html($page_req);

	$data = array();

	// Role translation dictionary
	$role_numbers = array(
		"Guest" => 0,
		"Member" => 1,
		"Bundler" => 2,
		"Developer" => 3,
		"Support" => 4,
		"Moderator" => 5,
		"Super Mod" => 6,
		"Admin" => 7
	);

	// Get SteamID64
	preg_match("/(\d+)/", $html->find(".sidebar__shortcut-inner-wrap a[href*='steamcommunity.com']")[0]->href, $steam_id);
	if (isset($filters) == false || in_array('steamid64', $filters)) {
		$data['steamid64'] = intval($steam_id[0]);
	}

	unset($steam_id);

	// Get nickname
	if (isset($filters) == false || in_array('nickname', $filters)) {
		$data['nickname'] = $html->find(".featured__heading__medium")[0]->innertext;
	}

	// Get info of the rows next to the avatar
	foreach($html->find(".featured__table__row") as $elem) {
		switch($elem->children(0)->innertext) {
			case 'Role':
				if (isset($filters) == false || in_array('role', $filters)) {
					$data['role'] = $role_numbers[$elem->children(1)->children(0)->innertext];
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
				if (isset($filters) == false || in_array('gifts_won_value', $filters)) {
					$index = strpos($elem->children(1), " ");
					$data['gifts_won_value'] = floatval(str_replace(array(",", ")"), "", substr($elem->children(1)->plaintext, $index + 3)));

					unset($index);
				}
				break;
			case 'Gifts Sent':
				if (isset($filters) == false || in_array('gifts_sent', $filters)) {
					$data['gifts_sent'] = intval(str_replace(",", "", $elem->children(1)->children(0)->children(0)->innertext));
				}
				if (isset($filters) == false || in_array('gifts_sent_value', $filters)) {
					$index = strpos($elem->children(1)->children(0)->plaintext, " ");
					$data['gifts_sent_value'] = floatval(str_replace(array(",", ")"), "", substr($elem->children(1)->children(0)->plaintext, $index + 3)));

					unset($index);
				}

				$gifts_feedback_matches;
				preg_match("/(\d+).+(\d+)/", str_replace(",", "", $elem->children(1)->children(0)->title), $gifts_feedback_matches);

				if (isset($filters) == false || in_array('gifts_awaiting_feedback', $filters)) {
					$data['gifts_awaiting_feedback'] = intval($gifts_feedback_matches[0]);
				}
				if (isset($filters) == false || in_array('gifts_not_sent', $filters)) {
					$data['gifts_not_sent'] = intval($gifts_feedback_matches[0]);
				}

				unset($gifts_feedback_matches);
				break;
			case 'Contributor Level':
				if (isset($filters) == false || in_array('contributor_level', $filters)) {
					$data['contributor_level'] = floatval($elem->children(1)->children(0)->title);
				}
				break;
		}
	}

	// Get suspension info if any
	$suspension_info = $html->find('.sidebar__suspension');
	if (!empty($suspension_info) && (isset($filters) == false || in_array('suspension', $filters))) {
		// Suspension translation numbers
		$suspension_numbers = array(
			"Suspended" => 0,
			"Banned" => 1
		);

		$data['suspension'] = array();
		$data['suspension']['type'] = $suspension_numbers[trim($suspension_info[0]->plaintext)];
		$data['suspension']['start_time'] = null;
		$data['suspension']['end_time'] = null;

		$suspension_time = $html->find('.sidebar__suspension-time');
		if ($data['suspension']['type'] == 0 && !empty($suspension_time) && $suspension_time[0]->first_child() !== null) {
			$data['suspension']['end_time'] = intval($suspension_time[0]->children(0)->getAttribute('data-timestamp'));
			$data['suspension']['start_time'] = time();
		} elseif ($data['suspension']['type'] == 1) {
			$data['suspension']['start_time'] = time();
		}

		unset($suspension_time);
	}
	unset($suspension_info);

	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')->withJson($data, 200, JSON_PRETTY_PRINT);
});
?>
