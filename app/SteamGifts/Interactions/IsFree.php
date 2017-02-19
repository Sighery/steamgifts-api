<?php
require_once(__DIR__ . '/../utils/utilities.php');
require_once(__DIR__ . '/../utils/dbconn.php');

$app->get('/SteamGifts/Interactions/IsFree/', function($request, $response) {
	// Define the constant for the max difference of time for cached data
	define('MAX_TIME_TITLE_CACHE', 86400); //24h
	define('MAX_TIME_FREE_CACHE', 21600); //6h

	global $db;

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
			"description" => "Missing or invalid required id argument"
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
			"description" => "Missing or invalid required type argument"
		)), 400);
	}

	// Retrieve the local data of the game title if it exists
	$stmt = $db->prepare("SELECT COUNT(*) AS count, id, game_title, unavailable, UNIX_TIMESTAMP(last_checked) AS last_checked FROM GamesInfo WHERE game_type=:game_type AND game_id=:game_id");
	$stmt->execute(array(
		':game_type' => $type,
		':game_id' => $id
	));
	$gamesinfo_row = $stmt->fetch(PDO::FETCH_ASSOC);

	unset($stmt);


	$title;
	$inserted_id;
	$brecord_title = false;
	if ($gamesinfo_row['count'] === 0 || (time() - $gamesinfo_row['last_checked']) >= MAX_TIME_TITLE_CACHE) {
		// There's either no local data or is outdated
		$json;

		if ($type === 0) {
			$json = APIRequests::generic_get_request("http://store.steampowered.com/api/appdetails?appids=" . $id . "&filters=basic");

		} else {
			$json = APIRequests::generic_get_request("http://store.steampowered.com/api/packagedetails/?packageids=" . $id);
		}

		if ($json->status_code !== 200) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 0,
				"description" => "The request to Steam was unsuccessful"
			)), 500);
		}

		$json = json_decode($json->body, true);

		// If the Steam API response is false we return a 500 error and store
		//that it's unavailable if there was no record of given game on the DB yet
		if ($json[$str_id]['success'] === false) {
			if ($gamesinfo_row['count'] === 0) {
				$stmt = $db->prepare("INSERT INTO GamesInfo (game_type, game_id, unavailable) VALUES (:game_type, :game_id, :unavailable)");
				$stmt->execute(array(
					':game_type' => $type,
					':game_id' => $id,
					':unavailable' => 1
				));
			} else {
				$stmt = $db->prepare("UPDATE GamesInfo SET unavailable=:unavailable, last_checked=NULL WHERE game_type=:game_type AND game_id=:game_id");
				$stmt->execute(array(
					':unavailable' => 1,
					':game_type' => $type,
					':game_id' => $id
				));
			}

			unset($stmt);

			if ($gamesinfo_row['count'] === 0 || is_null($gamesinfo_row['game_title'])) {
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
			$title = $gamesinfo_row['game_title'];
		} else {
			$title = $json[$str_id]['data']['name'];
		}


		if ($gamesinfo_row['count'] == 1 && ($gamesinfo_row['game_title'] == $title || $gamesinfo_row['unavailable'] == 1)) {
			// There was that info but the data was outdated. Title is still the
			//same or unavailable was 1
			$db->query("UPDATE GamesInfo SET unavailable=0, last_checked=NULL WHERE id=" . $gamesinfo_row['id']);
		} elseif ($gamesinfo_row['count'] == 1 && ($gamesinfo_row['game_title'] != $title || $gamesinfo_row['unavailable'] == 1)) {
			// There was that info but the data was outdated. Title isn't the
			//same or unavailable was 1
			$stmt = $db->prepare("UPDATE GamesInfo SET game_title=:game_title, last_checked=NULL, unavailable=:unavailable WHERE id=:id");
			$stmt->execute(array(
				':game_title' => $title,
				':id' => $gamesinfo_row['id'],
				':unavailable' => 0
			));

			unset($stmt);
		} else {
			// There was no data, INSERT.
			$stmt = $db->prepare("INSERT INTO GamesInfo (game_type, game_id, game_title, unavailable) VALUES (:game_type, :game_id, :game_title, :unavailable)");
			$stmt->execute(array(
				':game_type' => $type,
				':game_id' => $id,
				':game_title' => $json[$str_id]['data']['name'],
				':unavailable' => 0
			));
			$inserted_id = $db->query("SELECT LAST_INSERT_ID() AS inserted_id");
			$inserted_id = $inserted_id->fetch(PDO::FETCH_ASSOC);

			unset($stmt);
		}

		unset($json);
	} else {
		// There was data and wasn't outdated, if unavailable is 1 we return 500
		//else we store the stored title
		if ((bool)$gamesinfo_row['unavailable'] && is_null($gamesinfo_row['game_title'])) {
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 1,
				"description" => "Game inexistant or not available in the server's region",
				"game_title" => $gamesinfo_row['game_title']
			)), 500);
		}
		$title = $gamesinfo_row['game_title'];
	}

	// Template of the successful response JSON
	$data = array(
		"id" => $id,
		"type" => $type,
		"title" => $title,
		"free" => true
	);


	// Data to send with the POST request to SG
	$sg_post_data = array(
		"search_query" => $title,
		"page_number" => 1,
		"do" => "autocomplete_game"
	);


	// Code to get the id from the GamesInfo row and use it on the FreeGamesSG
	//table to locate the row we want if it exists, or create it otherwise
	if (isset($inserted_id)) {
		$inserted_id = $inserted_id['inserted_id'];
	} else {
		$inserted_id = $gamesinfo_row['id'];
	}
	$stmt = $db->query("SELECT COUNT(*) AS count, is_free, UNIX_TIMESTAMP(last_checked) AS last_checked FROM FreeGamesSG WHERE gamesinfo_id=" . $inserted_id);

	$gamesfree_row = $stmt->fetch(PDO::FETCH_ASSOC);
	unset($stmt);
	if ($gamesfree_row['count'] === 0 || (time() - $gamesfree_row['last_checked']) >= MAX_TIME_FREE_CACHE) {
		// There was either no data or it was outdated
		$html = APIRequests::sg_post_request('https://www.steamgifts.com/ajax.php', $sg_post_data);
		if ($html->status_code !== 200) {
			// If response was false stop here and return 500
			return $response->withHeader('Access-Control-Allow-Origin', '*')
			->withHeader('Content-type', 'application/json')->withJson(array(
			"errors" => array(
				"code" => 2,
				"description" => "The request to SG was unsuccessful"
			)), 500);
		}

		unset($sg_post_data);

		$html = json_decode($html->body, true);
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
					$data['free'] = false;
					break;
				}
			}
		}


		if ($gamesfree_row['count'] == 1 && $gamesfree_row['is_free'] == $data['free']) {
			// There was that info but the data was outdated. The game is still
			//free/not free so we just update last_checked
			$db->query("UPDATE FreeGamesSG SET last_checked=NULL WHERE gamesinfo_id=" . $inserted_id);
		} elseif ($gamesfree_row['count'] == 1 && $gamesfree_row['is_free'] != $data['free']) {
			// There was that info but the data was outdated. The game isn't
			//free/not free anymore so we update is_free and last_checked
			$db->query("UPDATE FreeGamesSG SET last_checked=NULL, is_free=" . (int)$data['free'] . " WHERE gamesinfo_id=" . $inserted_id);
		} else {
			// There wasn't that info, we INSERT it
			$db->query("INSERT INTO FreeGamesSG (gamesinfo_id, is_free) VALUES (" . $inserted_id . ", " . (int)$data['free'] . ")");
		}

	} else {
		// There was that info and the data wasn't outdated, we just save it on
		//$data and later return it
		$data['free'] = (bool)$gamesfree_row['is_free'];
	}

	// We finally return $data with a 200 code
	return $response->withHeader('Access-Control-Allow-Origin', '*')
	->withHeader('Content-type', 'application/json')
	->withJson($data, 200);
});
?>
