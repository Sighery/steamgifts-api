<?php
require_once('utilities.php');
require_once('dbconn.php');

$app->get('/SteamGifts/Interactions/GetGameTitle/', function($request, $response) {
	// Define the constant for max difference of time for cached data
	define('MAX_TIME_TITLE_CACHE', 86400); //24h

	global $db;

	$params = $request->getQueryParams();

	// Check if there's id argument and is valid
	if (isset($params['id']) && preg_match("/^[0-9]+$/", $params['id']) === 1) {
		$str_id = $params['id'];
		$id = intval($params['id']);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 0,
			"description" => "Missing or invalid id argument"
		)), 400);
	}

	// Lists of valid type values
	$valid_values_type = array(
		'0' => null,
		'1' => null
	);
	// Check if there's type argument and is valid
	if (isset($params['type']) && array_key_exists($params['type'], $valid_values_type)) {
		$type = intval($params['type']);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 1,
			"description" => "Missing or invalid type argument"
		)), 400);
	}

	// Retrieve the local data of the game title if it exists
	$sql_string = "SELECT COUNT(*) AS count, id, game_title, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GameTitles WHERE game_type=:game_type AND game_id=:game_id";
	$stmt = $db->prepare($sql_string);
	$stmt->execute(array(
		':game_type' => $type,
		':game_id' => $id
	));
	$gametitles_row = $stmt->fetch(PDO::FETCH_ASSOC);

	unset($sql_string);
	unset($stmt);
	//print_r($gametitles_row);
	//echo "\n";


	// Template of the successful JSON response
	$data = array(
		'id' => null,
		'game_id' => $id,
		'game_type' => $type,
		'game_title' => null
	);


	if ($gametitles_row['count'] === 0 || (time() - $gametitles_row['last_checked']) >= MAX_TIME_TITLE_CACHE) {
		// There's either no local data or is outdated
		//echo "1 if";
		//echo "\n";
		$json;
		if ($type == 0) {
			//echo "1 if. 1 if";
			//echo "\n";
			$json = get_page("http://store.steampowered.com/api/appdetails?appids=" . $id . "&filters=basic");

		} else {
			//echo "1 if. 1 else";
			//echo "\n";
			$json = get_page("http://store.steampowered.com/api/packagedetails/?packageids=" . $id);
		}

		if ($json === false) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 0,
				"description" => "Steam is most likely down"
			)), 500);
		}

		$json = json_decode($json, true);

		// If the Steam API response is false we return a 500 error and store
		//that it's unavailable
		if ($json[$str_id]['success'] === false) {
			//echo "1 if. 2 if";
			//echo "\n";
			if ($gametitles_row['count'] === 0) {
				$stmt = $db->prepare("INSERT INTO GameTitles (game_type, game_id, unavailable) VALUES (:game_type, :game_id, :unavailable)");
				$stmt->execute(array(
					':game_type' => $type,
					':game_id' => $id,
					':unavailable' => 1
				));
				unset($stmt);
			} else {
				$stmt = $db->prepare("UPDATE GameTitles SET unavailable=:unavailable, last_checked=NULL WHERE game_id=:game_id AND game_type=:game_type");
				$stmt->execute(array(
					':unavailable' => 1,
					':game_type' => $type,
					':game_id' => $id
				));
				unset($stmt);
			}

			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 1,
				"description" => "Game inexistant or not available in the server's region",
				"game_title" => $gametitles_row['game_title']
			)), 500);
		}

		// At this point the Steam API response was successful
		$data['game_title'] = $json[$str_id]['data']['name'];
		$title = $data['game_title'];
		//echo "title is: " . $title;
		//echo "\n";
		//echo "gamtitles_row title is: " . $gametitles_row['game_title'];


		if ($gametitles_row['count'] === 1 && ($gametitles_row['game_title'] == $title || $gametitles_row['unavailable'] === 1)) {
			// There was that info but the data was outdated. Title is still the
			//same or unavailable was 1
			//echo "1 if. 3 if";
			//echo "\n";
			$db->query("UPDATE GameTitles SET unavailable=0, last_checked=NULL WHERE id=" . $gametitles_row['id']);

			$data['id'] = $gametitles_row['id'];
		} elseif ($gametitles_row['count'] === 1 && ($gametitles_row['game_title'] != $title || $gametitles_row['unavailable'] === 1)) {
			// There was that info but the data was outdated. Title isn't the
			//same or unavailable was 1
			//echo "1 if. 3 elseif";
			//echo "\n";
			$sql_string = "UPDATE GameTitles SET game_title=:game_title, last_checked=NULL, unavailable=:unavailable WHERE id=:id";
			$stmt = $db->prepare($sql_string);
			$stmt->execute(array(
				':game_title' => $title,
				':id' => $gametitles_row['id'],
				':unavailable' => 0
			));

			$data['id'] = $gametitles_row['id'];

			unset($sql_string);
			unset($stmt);
		} else {
			// There was no data, INSERT.
			//echo "1 if. 3 else";
			//echo "\n";
			$sql_string = "INSERT INTO GameTitles (game_type, game_id, game_title, unavailable) VALUES (:game_type, :game_id, :game_title, :unavailable)";
			$stmt = $db->prepare($sql_string);
			$stmt->execute(array(
				':game_type' => $type,
				':game_id' => $id,
				':game_title' => $title,
				':unavailable' => 0
			));

			$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);
			$data['id'] = $inserted_id['inserted_id'];

			unset($sql_string);
			unset($stmt);
		}

		unset($json);
	} else {
		// There was data and wasn't outdated, if unavailable is 1 we return 500
		//else we store the stored title
		if ((bool)$gametitles_row['unavailable']) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 1,
				"description" => "Game inexistant or not available in the server's region",
				"game_title" => $gametitles_row['game_title']
			)), 500);
		}

		$data['id'] = $gametitles_row['id'];
		$data['game_title'] = $gametitles_row['game_title'];
	}

	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});

$app->get('/SteamGifts/Interactions/IsFree/', function($request, $response) {
	// Define the constant for the max difference of time for cached data
	define('MAX_TIME_TITLE_CACHE', 86400); //24h
	define('MAX_TIME_FREE_CACHE', 21600); //6h

	global $db;

	$private_data = parse_ini_file('private.ini');

	// Check if there's id argument and is valid
	$params = $request->getQueryParams();
	if (isset($params['id']) && preg_match("/^[0-9]+$/", $params['id']) === 1) {
		$str_id = $params['id'];
		$id = intval($params['id']);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 0,
			"description" => "Missing or invalid id argument"
		)), 400);
	}

	// Lists of valid type values
	$valid_values_type = array(
		'0' => null,
		'1' => null
	);
	// Check if there's type argument and is valid
	if (isset($params['type']) && array_key_exists($params['type'], $valid_values_type)) {
		$type = intval($params['type']);
	} else {
		return $response->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Content-type', 'application/json')->withJson(array(
		"errors" => array(
			"code" => 1,
			"description" => "Missing or invalid type argument"
		)), 400);
	}

	// Retrieve the local data of the game title if it exists
	$sql_string = "SELECT COUNT(*) AS count, id, game_title, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GameTitles WHERE game_type=:game_type AND game_id=:game_id";
	$stmt = $db->prepare($sql_string);
	$stmt->execute(array(
		':game_type' => $type,
		':game_id' => $id
	));
	$gametitles_row = $stmt->fetch(PDO::FETCH_ASSOC);

	unset($sql_string);
	unset($stmt);
	//print_r($gametitles_row);
	//echo "\n";


	$title;
	$inserted_id;
	$brecord_title = false;
	if ($gametitles_row['count'] === 0 || (time() - $gametitles_row['last_checked']) >= MAX_TIME_TITLE_CACHE) {
		// There's either no local data or is outdated
		//echo "1 if";
		//echo "\n";
		$json;
		if ($type === 0) {
			//echo "1 if. 1 if";
			//echo "\n";
			$json = get_page("http://store.steampowered.com/api/appdetails?appids=" . $id . "&filters=basic");

		} else {
			//echo "1 if. 1 else";
			//echo "\n";
			$json = get_page("http://store.steampowered.com/api/packagedetails/?packageids=" . $id);
		}

		if ($json === false) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 0,
				"description" => "Steam is most likely down"
			)), 500);
		}

		$json = json_decode($json, true);

		// If the Steam API response is false we return a 500 error and store
		//that it's unavailable if there was no record of given game on the DB yet
		if ($json[$str_id]['success'] === false) {
			//echo "1 if. 2 if";
			//echo "\n";
			if ($gametitles_row['count'] === 0) {
				$stmt = $db->prepare("INSERT INTO GameTitles (game_type, game_id, unavailable) VALUES (:game_type, :game_id, :unavailable)");
				$stmt->execute(array(
					':game_type' => $type,
					':game_id' => $id,
					':unavailable' => 1
				));
				unset($stmt);

			} else {
				$stmt = $db->prepare("UPDATE GameTitles SET unavailable=:unavailable, last_checked=NULL WHERE game_type=:game_type AND game_id=:game_id");
				$stmt->execute(array(
					':unavailable' => 1,
					':game_type' => $type,
					':game_id' => $id
				));
				unset($stmt);
			}


			if ($gametitles_row['count'] === 0 || is_null($gametitles_row['game_title'])) {
				return $response->withHeader('Access-Control-Allow-Origin', '*')
				->withHeader('Content-type', 'application/json')->withJson(array(
				"errors" => array(
					"code" => 1,
					"description" => "Game inexistant or not available in the server's region",
					"game_title" => null
				)), 500);
			} else {
				$brecord_title = true;
			}
		}

		// At this point the Steam API response was successful or there was
		//a stored record of given game when it was still available
		if ($brecord_title) {
			$title = $gametitles_row['game_title'];
		} else {
			$title = $json[$str_id]['data']['name'];
		}
		//echo "title is: " . $title;
		//echo "\n";
		//echo "gamtitles_row title is: " . $gametitles_row['game_title'];


		if ($gametitles_row['count'] == 1 && ($gametitles_row['game_title'] == $title || $gametitles_row['unavailable'] == 1)) {
			// There was that info but the data was outdated. Title is still the
			//same or unavailable was 1
			//echo "1 if. 3 if";
			//echo "\n";
			$db->query("UPDATE GameTitles SET unavailable=0, last_checked=NULL WHERE id=" . $gametitles_row['id']);
		} elseif ($gametitles_row['count'] == 1 && ($gametitles_row['game_title'] != $title || $gametitles_row['unavailable'] == 1)) {
			// There was that info but the data was outdated. Title isn't the
			//same or unavailable was 1
			//echo "1 if. 3 elseif";
			//echo "\n";
			$sql_string = "UPDATE GameTitles SET game_title=:game_title, last_checked=NULL, unavailable=:unavailable WHERE id=:id";
			$stmt = $db->prepare($sql_string);
			$stmt->execute(array(
				':game_title' => $title,
				':id' => $gametitles_row['id'],
				':unavailable' => 0
			));

			unset($sql_string);
			unset($stmt);
		} else {
			// There was no data, INSERT.
			//echo "1 if. 3 else";
			//echo "\n";
			$sql_string = "INSERT INTO GameTitles (game_type, game_id, game_title, unavailable) VALUES (:game_type, :game_id, :game_title, :unavailable)";
			$stmt = $db->prepare($sql_string);
			$stmt->execute(array(
				':game_type' => $type,
				':game_id' => $id,
				':game_title' => $json[$str_id]['data']['name'],
				':unavailable' => 0
			));
			$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);

			unset($sql_string);
			unset($stmt);
		}

		unset($json);
	} else {
		// There was data and wasn't outdated, if unavailable is 1 we return 500
		//else we store the stored title
		if ((bool)$gametitles_row['unavailable'] && is_null($gametitles_row['game_title'])) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 1,
				"description" => "Game inexistant or not available in the server's region",
				"game_title" => $gametitles_row['game_title']
			)), 500);
		}
		$title = $gametitles_row['game_title'];
	}

	// Template of the successful response JSON
	$data = array(
		"id" => $id,
		"type" => $type,
		"title" => $title,
		"free" => true
	);
	//echo "\n";
	//print_r($data);
	//echo "\n";

	// Data to send with the POST request to SG
	$sg_post_data = array(
		"search_query" => $title,
		"page_number" => 1,
		"do" => "autocomplete_game"
	);
	//print_r($sg_post_data);


	// Code to get the id from the GameTitles row and use it on the SGGamesFree
	//table to locate the row we want if it exists, or create it otherwise
	if (isset($inserted_id)) {
		//echo "2 if. If";
		//echo "\n";
		$inserted_id = $inserted_id['inserted_id'];
	} else {
		//echo "2 if. Else";
		//echo "\n";
		$inserted_id = $gametitles_row['id'];
	}
	//echo "SELECT COUNT(*) AS count, is_free, UNIX_TIMESTAMP(last_checked) AS last_checked FROM SGGamesFree WHERE gametitles_id=" . $inserted_id;
	//echo "\n";
	//return "Nope";
	$stmt = $db->query("SELECT COUNT(*) AS count, is_free, UNIX_TIMESTAMP(last_checked) AS last_checked FROM SGGamesFree WHERE gametitles_id=" . $inserted_id);

	$gamesfree_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);
	if ($gamesfree_row['count'] === 0 || (time() - $gamesfree_row['last_checked']) >= MAX_TIME_FREE_CACHE) {
		// There was either no data or it was outdated
		//echo "3 if";
		//echo "\n";
		$html = post_sg_page('https://www.steamgifts.com/ajax.php', $sg_post_data);
		if ($html === false) {
			// If response was false stop here and return 500
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 2,
				"description" => "There was an error with the request to SG"
			)), 500);
		}

		unset($sg_post_data);

		$html = json_decode($html, true);
		$html = str_get_html($html['html']);

		// Find and loop through the rows of the table response when searching
		//a game to create a giveaway of
		forEach($html->find('.table__column--width-fill') as $elem) {
			// Get the link, if any, from each row
			$link = $elem->find('.table__column__secondary-link', 0);
			if (is_null($link)) {
				continue;
			}
			$link = $link->href;

			$type_id_matches;
			preg_match("/http:\/\/store\.steampowered\.com\/(app|sub)\/([0-9]+)/", $link, $type_id_matches);
			if (!empty($type_id_matches)) {

				// Game types translation
				$game_type_numbers = array(
					"app" => 0,
					"sub" => 1
				);

				if ($game_type_numbers[$type_id_matches[1]] == $type && intval($type_id_matches[2]) == $id) {
					//echo "186";
					//print_r($data);
					//echo "\n";
					$data['free'] = false;
					//echo "190";
					//var_dump($data);
					//echo "\n";
					break;
				}
			}
		}

		//echo "195";
		//echo "\n";
		//print_r($data);
		//echo "\n";

		if ($gamesfree_row['count'] == 1 && $gamesfree_row['is_free'] == $data['free']) {
			// There was that info but the data was outdated. The game is still
			//free/not free so we just update last_checked
			//echo "3 if. 1 if";
			//echo "\n";
			$db->query("UPDATE SGGamesFree SET last_checked=NULL WHERE gametitles_id=" . $inserted_id);
		} elseif ($gamesfree_row['count'] == 1 && $gamesfree_row['is_free'] != $data['free']) {
			// There was that info but the data was outdated. The game isn't
			//free/not free anymore so we update is_free and last_checked
			//echo "3 if. 1 elseif";
			//echo "\n";
			//print_r($data);
			//echo "\n";
			//echo "UPDATE SGGamesFree SET last_checked=NULL, is_free=" . (int)$data['free'] . " WHERE gametitles_id=" . $inserted_id;
			//echo "\n";
			$db->query("UPDATE SGGamesFree SET last_checked=NULL, is_free=" . (int)$data['free'] . " WHERE gametitles_id=" . $inserted_id);
		} else {
			// There wasn't that info, we INSERT it
			//echo "3 if. 1 else";
			//echo "\n";
			//echo "218";
			//var_dump($data);
			$db->query("INSERT INTO SGGamesFree (gametitles_id, is_free) VALUES (" . $inserted_id . ", " . (int)$data['free'] . ")");
		}

	} else {
		// There was that info and the data wasn't outdated, we just save it on
		//$data and later return it
		//echo "3 else";
		//echo "\n";
		$data['free'] = (bool)$gamesfree_row['is_free'];
		//echo "230";
		//var_dump($gamesfree_row['is_free']);
	}

	// We finally return $data with a 200 code
	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});



$app->get('/SteamGifts/Interactions/GetMessagesCount/', function($request, $response) {
	/** This endpoint and method will stay private and protected for now
	  * until Cg allows me (if) to ask users for their PHPSESSID cookies to get
	  * and serve their info. This is a check to allow it, if you are hosting
	  * your own version on your server just remove these lines.
	  */
	$private_data = parse_ini_file('private.ini');
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
		$page_req = get_sg_page("https://www.steamgifts.com/about/brand-assets", $key);

		if ($page_req === false) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 0,
				"description" => "There was an error with the request to SG"
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

	$html = str_get_html($page_req);


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
